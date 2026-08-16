<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Models\CommissionRecipient;
use App\Models\Customer;
use App\Models\OfficerDeposit;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Formula keuangan billing. Kunci: uang transfer langsung settled ke rekening
 * Pion Tech — TIDAK pernah masuk perhitungan "uang di petugas".
 */
class BillingService
{
    /** @var array<string, array{target: float, collected: float, deposited: float, remaining: float}> */
    private array $officerProgress = [];

    /** Dipanggil saat setoran/transaksi berubah — memo di bawah jadi basi. */
    public function forgetOfficerProgress(): void
    {
        $this->officerProgress = [];
    }

    /**
     * Ringkasan keuangan bulanan; $officerId membatasi ke satu petugas
     * (dipakai dashboard petugas — transfer jadi 0 karena officer_id null).
     *
     * @return array{cash: float, transfer: float, total_collected: float, total_deposited: float, held_by_officers: float}
     */
    public function monthlySummary(Carbon $period, ?int $officerId = null): array
    {
        $p = $period->copy()->startOfMonth();

        $cash = (float) Transaction::forPeriod($p)->cash()
            ->when($officerId, fn ($q) => $q->where('officer_id', $officerId))
            ->sum('paid_amount');
        $transfer = (float) Transaction::forPeriod($p)
            ->where('payment_method', PaymentMethod::Transfer)
            ->when($officerId, fn ($q) => $q->where('officer_id', $officerId))
            ->sum('paid_amount');
        $deposited = (float) OfficerDeposit::whereDate('period', $p)
            ->when($officerId, fn ($q) => $q->where('officer_id', $officerId))
            ->sum('amount');

        return [
            'cash' => $cash,
            'transfer' => $transfer,
            'total_collected' => $cash + $transfer,
            'total_deposited' => $deposited,
            // Formula kunci: hanya tunai yang lewat petugas, dikurangi setoran.
            'held_by_officers' => $cash - $deposited,
        ];
    }

    /**
     * Ringkasan keuangan rentang tanggal — basis timestamp aktual
     * (paid_at transaksi, deposited_at setoran), bukan bulan tagihan.
     * Dipakai laporan; formula sama dengan monthlySummary.
     *
     * @return array{cash: float, transfer: float, total_collected: float, total_deposited: float, held_by_officers: float}
     */
    public function rangeSummary(Carbon $from, Carbon $until, ?int $officerId = null): array
    {
        $range = [$from->copy()->startOfDay(), $until->copy()->endOfDay()];

        $cash = (float) Transaction::whereBetween('paid_at', $range)->cash()
            ->when($officerId, fn ($q) => $q->where('officer_id', $officerId))
            ->sum('paid_amount');
        $transfer = (float) Transaction::whereBetween('paid_at', $range)
            ->where('payment_method', PaymentMethod::Transfer)
            ->when($officerId, fn ($q) => $q->where('officer_id', $officerId))
            ->sum('paid_amount');
        $deposited = (float) OfficerDeposit::whereBetween('deposited_at', $range)
            ->when($officerId, fn ($q) => $q->where('officer_id', $officerId))
            ->sum('amount');

        return [
            'cash' => $cash,
            'transfer' => $transfer,
            'total_collected' => $cash + $transfer,
            'total_deposited' => $deposited,
            'held_by_officers' => $cash - $deposited,
        ];
    }

    /**
     * Progres penagihan satu periode — dasar ringkasan halaman Tagihan Bulanan.
     * $clusterId membatasi ke satu cluster, $officerId membatasi angka setoran.
     * Pelanggan sudah auto-terscope cluster petugas via global scope Customer.
     *
     * @return array{billed: int, paid: int, unpaid: int, billed_amount: float, paid_amount: float, outstanding: float, cash: float, transfer: float, must_deposit: float, deposited: float, not_deposited: float}
     */
    public function billingProgress(Carbon $period, ?string $clusterId = null, ?int $officerId = null): array
    {
        $p = $period->copy()->startOfMonth();

        $customers = fn (): Builder => Customer::query()->billable()
            ->when($clusterId, fn (Builder $q) => $q->where('cluster_id', $clusterId));
        $paidInPeriod = fn (Builder $q): Builder => $q->forPeriod($p)
            ->where('status', TransactionStatus::Paid);
        // Transaksi lunas periode ini, di-scope CLUSTER (bukan officer): transaksi
        // transfer punya officer_id null, scoping per-officer akan menelan angkanya.
        $paidTransactions = fn (): Builder => Transaction::forPeriod($p)
            ->where('status', TransactionStatus::Paid)
            ->whereHas('customer', fn (Builder $q) => $q->billable()
                ->when($clusterId, fn (Builder $q2) => $q2->where('cluster_id', $clusterId)));

        $billed = $customers()->count();
        $paid = $customers()->whereHas('transactions', $paidInPeriod)->count();

        $billedAmount = (float) $customers()->sum('billing_amount');
        $transfer = (float) $paidTransactions()
            ->where('payment_method', PaymentMethod::Transfer)
            ->sum('paid_amount');
        // Harus disetor = seluruh tagihan yang TIDAK dibayar transfer, termasuk yang
        // belum tertagih — uang yang wajib ditarik petugas lalu disetor ke admin.
        // Beda dari monthlySummary()['held_by_officers'] (tunai − setor) yang hanya
        // menghitung uang yang sudah tertagih; keduanya sengaja berdampingan.
        $mustDeposit = $billedAmount - $transfer;
        // Setoran hanya bisa di-scope per petugas (tidak punya kolom cluster). Kalau
        // satu petugas memegang beberapa cluster, angka ini adalah setoran petugasnya,
        // bukan setoran cluster itu saja.
        $deposited = $this->monthlySummary($p, $officerId)['total_deposited'];

        return [
            'billed' => $billed,
            'paid' => $paid,
            'unpaid' => $billed - $paid,
            'billed_amount' => $billedAmount,
            'paid_amount' => (float) $paidTransactions()->sum('paid_amount'),
            'outstanding' => (float) $customers()
                ->whereDoesntHave('transactions', $paidInPeriod)
                ->sum('billing_amount'),
            'cash' => (float) $paidTransactions()->cash()->sum('paid_amount'),
            'transfer' => $transfer,
            'must_deposit' => $mustDeposit,
            'deposited' => $deposited,
            'not_deposited' => $mustDeposit - $deposited,
        ];
    }

    /**
     * Penerima komisi + dasar hitungannya untuk satu periode: total paid_amount
     * transaksi LUNAS milik pelanggan yang direferalkan (alias paid_base).
     * Penerima tanpa transaksi tetap ikut dengan paid_base NULL → komisi 0.
     *
     * Dipakai bersama tabel halaman Komisi dan chart-nya — jangan duplikasi
     * formulanya di dua tempat.
     */
    public function commissionQuery(Carbon $period): Builder
    {
        $paidInPeriod = fn (Builder $query): Builder => $query
            ->forPeriod($period->copy()->startOfMonth())
            // Qualified: relasi through ikut menarik tabel customers yang juga
            // punya kolom status.
            ->where('transactions.status', TransactionStatus::Paid);

        return CommissionRecipient::query()
            ->withSum(['referredTransactions as paid_base' => $paidInPeriod], 'paid_amount')
            ->withCount(['referredTransactions as paid_count' => $paidInPeriod])
            // Dasar estimasi: tagihan pelanggan referal yang belum lunas periode ini —
            // komisi yang belum jadi hak karena uangnya belum masuk.
            ->withSum(['referredCustomers as estimated_base' => fn (Builder $query): Builder => $query
                ->billable()
                ->whereDoesntHave('transactions', $paidInPeriod)], 'billing_amount');
    }

    /**
     * Komisi versi rentang tanggal — basis paid_at aktual, sama seperti laporan
     * lain. Tanpa estimated_base: estimasi hanya bermakna per periode tagihan.
     */
    public function commissionRangeQuery(Carbon $from, Carbon $until): Builder
    {
        $paidInRange = fn (Builder $query): Builder => $query
            // Qualified: relasi through ikut menarik tabel customers.
            ->whereBetween('transactions.paid_at', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->where('transactions.status', TransactionStatus::Paid);

        return CommissionRecipient::query()
            ->with('customer')
            ->withSum(['referredTransactions as paid_base' => $paidInRange], 'paid_amount')
            ->withCount(['referredTransactions as paid_count' => $paidInRange]);
    }

    /** Total komisi seluruh penerima pada satu periode. */
    public function commissionTotal(Carbon $period): float
    {
        return (float) $this->commissionQuery($period)->get()->sum('commission_amount');
    }

    /** Total estimasi komisi (pelanggan referal yang belum bayar) satu periode. */
    public function commissionEstimateTotal(Carbon $period): float
    {
        return (float) $this->commissionQuery($period)->get()->sum('estimated_commission_amount');
    }

    /**
     * Progres satu petugas pada satu periode:
     * - target    : yang HARUS ditarik = tagihan pelanggan di cluster-nya − yang dibayar transfer
     * - collected : tunai yang sudah masuk ke petugas
     * - deposited : yang sudah disetor ke admin
     * - remaining : uang yang masih di tangan petugas (collected − deposited)
     *
     * @return array{target: float, collected: float, deposited: float, remaining: float}
     */
    public function officerProgress(int $officerId, Carbon $period): array
    {
        $p = $period->copy()->startOfMonth();
        $key = $officerId.'-'.$p->format('Y-m');

        // ponytail: memo per-request (service singleton). Panel per petugas dan
        // kolom tabel setoran memanggil pasangan yang sama berkali-kali.
        return $this->officerProgress[$key] ??= (function () use ($officerId, $p): array {
            $customers = fn (): Builder => Customer::query()->billable()
                ->whereHas('cluster', fn (Builder $q) => $q->where('officer_id', $officerId));

            // Transaksi transfer punya officer_id NULL — harus lewat relasi
            // customer→cluster, bukan officer_id, kalau tidak angkanya selalu 0.
            $transfer = (float) Transaction::forPeriod($p)
                ->where('transactions.status', TransactionStatus::Paid)
                ->where('payment_method', PaymentMethod::Transfer)
                ->whereHas('customer', fn (Builder $q) => $q->billable()
                    ->whereHas('cluster', fn (Builder $q2) => $q2->where('officer_id', $officerId)))
                ->sum('paid_amount');

            $collected = (float) Transaction::where('officer_id', $officerId)
                ->forPeriod($p)->cash()->sum('paid_amount');
            $deposited = (float) OfficerDeposit::where('officer_id', $officerId)
                ->whereDate('period', $p)->sum('amount');

            return [
                'target' => (float) $customers()->sum('billing_amount') - $transfer,
                'collected' => $collected,
                'deposited' => $deposited,
                'remaining' => $collected - $deposited,
            ];
        })();
    }

    public function officerRemainingBalance(int $officerId, Carbon $period): float
    {
        return $this->officerProgress($officerId, $period)['remaining'];
    }
}
