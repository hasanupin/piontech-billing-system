# Piontech Billing System

Laravel 13 + Filament 5 admin panel. **UI dibangun sepenuhnya dengan Filament** — bukan jQuery/AdminLTE/Blade manual.

## Architecture Reference

Struktur aplikasi ini mengadopsi pola dari project **sippw** (`/Users/hasanupin/www/freelance/sippw`). Dokumen desain lengkapnya ada di:

- `sippw/.plan/SYSTEM_DESIGN.md` — desain sistem menyeluruh
- `sippw/.plan/modules/*.md` — spesifikasi per modul (auth, master data, reports, dashboard)

Yang diadopsi adalah **strukturnya** (layering, konvensi, pola scoping), **bukan domain-nya**. Domain sippw (usbuiyah/pengamal/wilayah) tidak berlaku di sini; domain project ini adalah billing.

Saat membuat modul baru, ikuti konvensi di bawah dan lihat modul **Users** (`app/Filament/Resources/Users/`) sebagai contoh implementasi rujukan.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Admin UI | Filament 5 (panel `admin`, path `/admin`) |
| Database | MySQL 8 (docker, host port 3309) · SQLite `:memory:` (test) |
| Cache/Queue | Redis (docker, host port 6382) · database driver (queue/cache/session) |
| Dev Environment | Docker Compose (lihat `README-Docker.md`) atau `php artisan serve` terhubung ke MySQL/Redis docker |

## Commands

```bash
php artisan serve                    # dev server lokal (mysql & redis dari docker-compose, host port 3309/6382)
php artisan migrate:fresh --seed     # reset DB + super admin
php artisan test                     # test suite
php artisan make:filament-resource X --generate [--soft-deletes]  # scaffold resource
docker-compose up -d --build         # full stack: app :8002, mysql :3309, redis :6382

npm ci && npm run build              # WAJIB — panel pakai ->viteTheme(); tanpa build panel tampil tanpa CSS
npm run dev                          # hot reload saat menggarap theme.css
```

Login seeder: `admin@example.com` / `password` (role `super_admin`).

> **Asset build tidak opsional.** Panel `admin` mendaftarkan `->viteTheme('resources/css/filament/admin/theme.css')`,
> jadi setiap deploy harus menjalankan `npm ci && npm run build` (sudah masuk `Dockerfile`).
> Di test hal ini ditangani `withoutVite()` pada `tests/TestCase.php` — jangan dihapus.

## Struktur & Konvensi (diadopsi dari sippw)

### Layering

```
Filament Resource / Page     → HTTP + UI layer (setara Controller di sippw; tetap tipis)
     ↓
Service (app/Services)       → business logic non-trivial + DB transaction
     ↓
Model (app/Models)           → representasi tabel, relationships, casts
```

- Resource Filament = pengganti Controller+FormRequest+Blade+DataTables sippw. Validasi ada di form schema, listing di table schema.
- Logika bisnis yang lebih dari sekadar CRUD dipindah ke Service class. Interface hanya untuk service kompleks.
- Service di-bind di `AppServiceProvider::register()` (singleton bila stateless).

### Database & Migrations

- **Semua identifier database & enum dalam Bahasa Inggris** (tabel, kolom, nilai enum, class enum). Bahasa Indonesia hanya untuk display via i18n (`__()` + `lang/id.json`). Contoh: tabel `customers` bukan `pelanggan`, status `suspended` bukan `isolir`.
- Kolom status aktif: `is_active` boolean (default sesuai kebutuhan modul).
- Tabel domain billing memakai **ULID PK** (`$table->ulid('id')->primary()` + trait `HasUlids`); tabel `users` tetap bigint.
- Audit pembuat record: `created_by` FK → `users.id`, `nullOnDelete`, diisi otomatis via `mutateFormDataBeforeCreate`.
- Soft deletes untuk entitas penting (users, dan entitas transaksional nanti).
- Entitas yang tampil di URL publik memakai `uuid` (unique) sebagai route key — lihat pola `getRouteKeyName()` + generate di `booted()` (contoh di sippw: `Usbuiyah`, `Pengamal`).
- Kolom pilihan terbatas pakai `$table->enum(...)` dengan nilai UPPERCASE (`AKTIF`, `SUDAH`) atau snake_case untuk role.

### Enums

- Lokasi: `app/Enums/`, PHP backed enum (string).
- Implement `Filament\Support\Contracts\HasLabel` (dan `HasColor` bila tampil sebagai badge) supaya langsung dipakai `Select::options(Enum::class)` dan `TextColumn::badge()`.
- Contoh: `App\Enums\Role`.

### Models

- Laravel 13 style: atribut `#[Fillable([...])]` dan `#[Hidden([...])]`, bukan properti `$fillable`.
- Casts lewat method `casts()`; enum di-cast ke class enum-nya.
- Relationships selalu dengan return type (`BelongsTo`, `HasMany`, dst).
- Relasi ke user pembuat: `creator()` (`->withTrashed()`).
- Role helpers di `User`: `isRole(Role ...$roles)`, `isSuperAdmin()`.

### Roles & Scoping

Roles (sesuai PRD): `super_admin` (CEO), `admin` (admin penagihan), `field_officer` (petugas lapangan) di `App\Enums\Role`.

Pola sippw yang wajib dipertahankan:

1. **Semua data-scoping berbasis role terpusat di `App\Services\ScopeService`** (singleton). Resource/Service lain tidak boleh berisi logika scoping sendiri.
2. Setiap method scoping berbentuk `match ($user->role) { ... }` dengan default `whereRaw('1 = 0')` (deny by default).
3. Filament resource menerapkan scope di `getEloquentQuery()` — lihat `UserResource::getEloquentQuery()`.
4. Otorisasi tulis (create/update terhadap entitas di luar scope) juga lewat method `authorize*()` di ScopeService (`abort(403)`).
5. Matriks permission per-fitur × per-role didokumentasikan per modul — template: `sippw/.plan/modules/00-overview.md` bagian "Roles & Permissions Matrix".

### Filament Resources

Struktur hasil generator dipertahankan (jangan digabung ke satu file):

```
app/Filament/Resources/{Entity}/
├── {Entity}Resource.php          # model, nav, access gate, scoped query
├── Schemas/{Entity}Form.php      # form schema
├── Tables/{Entity}sTable.php     # table schema (kolom, filter, actions)
└── Pages/                        # List / Create / Edit
```

Konvensi resource:

- **Access gate**: `canAccess()` memeriksa role (contoh: UserResource hanya `super_admin`). Menu otomatis tersembunyi bila tidak punya akses.
- **Navigation group**: master data di grup `Master`; modul domain billing nanti pakai grup sendiri (mis. `Billing`).
- **Password field**: required saat create, kosong = tidak diubah saat edit (`->required(fn ($operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))`). Hash otomatis via cast `hashed`.
- **created_by**: diisi di `mutateFormDataBeforeCreate` pada page Create.
- **Soft-delete**: `TrashedFilter` + `Restore/ForceDelete` actions; `getEloquentQuery()` dan `getRecordRouteBindingEloquentQuery()` melepas `SoftDeletingScope`.
- **Proteksi diri**: user tidak bisa menghapus akunnya sendiri (lihat `UsersTable` dan `EditUser`).
- **Label kolom/field wajib lewat `__()`** dengan key Bahasa Inggris (`->label(__('Full Name'))`); terjemahan Indonesia di `lang/id.json`. Berlaku juga untuk `getLabel()` enum, `getModelLabel()`/`getPluralModelLabel()`/`getNavigationGroup()` resource. Ganti bahasa aplikasi = ubah `APP_LOCALE` di `.env`.

### Auth

- Login Filament bawaan (`/admin/login`), kredensial email + password.
- `User` implement `Filament\Models\Contracts\FilamentUser`; `canAccessPanel()` menolak user `is_active = false` (setara pengecekan login sippw). Bila nanti ada entitas organisasi (kantor/cabang), tambahkan pengecekan entitas nonaktif di sini juga.

### Testing

- Setiap modul minimal punya feature test kontrol akses: role yang boleh → 200, role lain → 403, guest → redirect login, user nonaktif → 403. Contoh: `tests/Feature/UserModuleTest.php`.
- **Nama method test camelCase** (`testSuperAdminCanAccessUsersResource`), bukan snake_case. Preset Laravel-nya Pint memaksa snake_case, jadi `pint.json` meng-override `php_unit_method_casing` ke `camel_case` — jangan dihapus.
- Factory menyediakan state per-role (`superAdmin()`, `admin()`, `fieldOfficer()`, `inactive()` di `UserFactory`) dan state per-status domain (mis. `suspended()` di `CustomerFactory`).

## Implemented Modules

| Module | Status | Files |
|---|---|---|
| Auth (Filament login + is_active gate) | ✅ | `app/Models/User.php` |
| Users (CRUD, role, soft delete) | ✅ | `app/Filament/Resources/Users/`, `app/Enums/Role.php`, `app/Services/ScopeService.php`, migration `2026_07_17_000003` |
| DB Schema billing — TASK-02 (packages, clusters, customers, transactions, officer_deposits + factories, seeders) | ✅ | migrations `2026_07_18_00000{1..5}`, `app/Models/{Package,Cluster,Customer,Transaction,OfficerDeposit}.php`, `database/seeders/`, `tests/Feature/DatabaseStructureTest.php` |
| Business rules — TASK-03 (enums status/payment, global scope cluster, auto-status + transfer nullify, BillingService) | ✅ | `app/Enums/{CustomerStatus,TransactionStatus,PaymentMethod}.php`, `app/Models/{Customer,Transaction}.php`, `app/Services/BillingService.php`, `ScopeService::scopeCustomersForUser`, `tests/Feature/{TransactionRules,CustomerScope,BillingService}Test.php` |
| RBAC — TASK-04 (super_admin full access via `Gate::before`, 6 policies) | ✅ | `app/Policies/`, `app/Providers/AppServiceProvider.php`, `tests/Feature/RoleAccessTest.php` |
| Master Paket — TASK-05 (CRUD, view-only nihil untuk super_admin/admin, petugas forbidden) | ✅ | `app/Filament/Resources/Packages/`, `app/Policies/PackagePolicy.php`, `tests/Feature/PackageModuleTest.php` |
| Master Cluster — TASK-06 (CRUD admin-only, officer PIC select, customers_count, CustomerRelationManager view-only, ganti PIC → scope pelanggan ikut) | ✅ | `app/Filament/Resources/Clusters/`, `tests/Feature/ClusterModuleTest.php` |
| Pelanggan — TASK-07 (CRUD 4 section, kolom setara Excel, WA/Maps clickable, quick action ISOLIR/Pulihkan admin-only, filter due_today, scope petugas) | ✅ | `app/Filament/Resources/Customers/`, `tests/Feature/CustomerModuleTest.php` |
| Pelanggan — petugas create+edit (di luar TASK; **menyimpang dari PRD §6** atas permintaan pemilik produk: petugas boleh daftar & edit pelanggan **di clusternya sendiri** tanpa lapor admin. Dropdown cluster difilter + prefill via `ScopeService::scopeClustersForUser`, guard server-side `authorizeCustomerCluster` di page Create/Edit. Field **status**, hapus, ISOLIR/Pulihkan, dan import Excel tetap admin-only) | ✅ | `app/Policies/CustomerPolicy.php`, `app/Services/ScopeService.php`, `app/Filament/Resources/Customers/{Schemas/CustomerForm,Pages/CreateCustomer,Pages/EditCustomer}.php`, `tests/Feature/{CustomerModuleTest,RoleAccessTest}.php` |
| Transaksi — TASK-08 (form reaktif prefill paket editable, metode per-role, officer hidden saat transfer, tolak duplikat, page Tagihan Bulan Ini) | ✅ | `app/Filament/Resources/Transactions/`, `app/Filament/Pages/MonthlyBilling.php`, `ScopeService::scopeTransactionsForUser`, `tests/Feature/TransactionModuleTest.php` |
| Tagihan Bulanan — revisi konteks lapangan (di luar TASK: kolom alamat/WA/status pelanggan, tombol ikon Foto Rumah (modal) & Cek Lokasi (Maps) yang **disabled** bila datanya kosong; filter Periode/Cluster/Status Bayar kini **filter halaman** (`HasFiltersForm`, seperti Dashboard) sehingga tata letaknya filter → chart → tabel dan satu filter menggerakkan keduanya; urutan konten diatur lewat `content()` schema (`EmbeddedSchema` + `Grid` widget + `EmbeddedTable`), bukan blade custom; widget doughnut + 3 kartu (lunas, belum bayar, sudah disetor) + **chart batang rekap uang 8 angka** dalam 3 kelompok (Tagihan: nominal/terbayar/belum · Metode: tunai/transfer · Setoran: harus/sudah/belum) ikut filter Periode & Cluster tapi **sengaja mengabaikan** filter Status Bayar. **Definisi "harus disetor" = nominal tagihan − transfer** (semua tagihan yang tidak masuk via transfer wajib ditarik petugas lalu disetor), sehingga "belum disetor" = uang yang belum sampai ke admin termasuk yang belum tertagih — **berbeda** dari `held_by_officers` (`tunai − disetor`) yang dipakai Dashboard/strip status/laporan; keduanya sengaja berdampingan, jangan disatukan) | ✅ | `app/Filament/Pages/MonthlyBilling.php`, `app/Filament/Widgets/{MonthlyBillingChart,MonthlyBillingSummary,MonthlyBillingDepositChart}.php`, `app/Filament/Widgets/Concerns/ReadsMonthlyBillingFilters.php`, `BillingService::billingProgress()`, `resources/views/filament/modals/house-photo.blade.php`, `tests/Feature/MonthlyBillingPageTest.php` |
| Setoran Petugas — TASK-09 (form info-panel sisa real-time, officer_id terkunci utk petugas, received_by admin-only, widget ringkasan) | ✅ | `app/Filament/Resources/OfficerDeposits/`, `ScopeService::scopeDepositsForUser`, `tests/Feature/OfficerDepositModuleTest.php` |
| Dashboard — TASK-10 (5 stat cards, doughnut chart, tabel setoran petugas sisa merah, due-today per cluster, filter periode global via HasFiltersForm, scope petugas) | ✅ | `app/Filament/Pages/Dashboard.php`, `app/Filament/Widgets/`, `BillingService::monthlySummary(±officerId)`, `tests/Feature/DashboardWidgetTest.php` |
| Import Excel — TASK-11 (phpspreadsheet langsung — maatwebsite tidak support PHP 8.5; cleaning WA scientific→62xxx, paket×1000→harga, status fallback; header action admin-only) | ✅ | `app/Imports/CustomerImport.php`, `ListCustomers::getHeaderActions()`, `tests/fixtures/sample_pelanggan.xlsx`, `tests/Feature/CustomerImportTest.php` |
| Setoran Petugas — revisi periode & target (di luar TASK: filter **bulan-tahun** di halaman list (`HasFiltersForm` di `ListOfficerDeposits`; `InteractsWithTable` ada di parent `ListRecords` jadi **tidak** perlu `insteadof`), urutan konten filter → kartu ringkasan + panel per petugas → tabel, baris tabel ikut periode via override `getTableQuery()`. **`BillingService::officerProgress()`** = satu sumber 4 angka per petugas: `target` (**Harus Ditarik = tagihan pelanggan di cluster-nya − yang dibayar transfer**; transaksi transfer `officer_id` NULL sehingga wajib lewat relasi customer→cluster), `collected` (tunai masuk), `deposited`, `remaining` (= `officerRemainingBalance()` yang kini mendelegasi ke sini). Di-memo per petugas+periode di service singleton; `OfficerDeposit`/`Transaction` memanggil `forgetOfficerProgress()` di `saved`/`deleted` supaya tidak basi dalam satu request. Kolom **Harus Ditarik** di samping Nominal (tabel + export) lewat `OfficerDeposit::mustCollect()`; `OfficerDepositWidget` jadi 4 kolom dan ikut dipakai di dashboard) | ✅ | `BillingService::officerProgress()`, `app/Filament/Resources/OfficerDeposits/{Pages/ListOfficerDeposits,Tables/OfficerDepositsTable,Widgets/OfficerDepositSummary}.php`, `app/Filament/Widgets/OfficerDepositWidget.php`, `app/Models/{OfficerDeposit,Transaction}.php`, `tests/Feature/OfficerDepositModuleTest.php` |
| Komisi — `Penagihan` (di luar TASK: halaman hitungan turunan, **tanpa tabel baru**. Filter periode di atas chart & tabel (pola sama dengan Tagihan Bulanan, termasuk `insteadof` untuk bentrok `normalizeTableFilterValuesFromQueryString`). **Basis komisi = `paid_amount` transaksi berstatus LUNAS** milik pelanggan yang direferalkan pada periode itu, dikali `commission_percent` penerima; **semua** penerima tampil, yang tanpa transaksi jadi 0. Query dipakai bersama tabel+chart lewat `BillingService::commissionQuery()` (`withSum`/`withCount` atas `CommissionRecipient::referredTransactions()` — HasManyThrough). Total periode ada di subheading halaman; chart = bar per penerima berkomisi > 0, tanpa cap. Kolom nama & WA mirror di-share dari `CommissionRecipientsTable::nameColumn()/whatsappColumn()`. `Transaction::scopeForPeriod()` kini `qualifyColumn` karena ikut dipakai di join. CEO+admin only) | ✅ | `app/Filament/Pages/Commission.php`, `app/Filament/Widgets/CommissionChart.php`, `BillingService::commissionQuery()`, `CommissionRecipient::{referredTransactions,commission_amount}`, `tests/Feature/CommissionPageTest.php` |
| Penerima Komisi + Referal pelanggan — `Master` (di luar TASK: master penerima komisi, jenis **Pelanggan / Non Pelanggan**. Jenis Pelanggan = **MIRROR**, bukan salinan: kolom `name`/`address`/`whatsapp_number` sengaja NULL, datanya dibaca hidup dari `customers` lewat `display_name`/`display_address`/`display_whatsapp` — ganti data pelanggan, penerima ikut. Field form-nya tetap tampil tapi `disabled` + `dehydrated(false)`; `EditCommissionRecipient::mutateFormDataBeforeFill()` mengisinya untuk tampilan. Relasi `customer()` pakai **`withoutGlobalScopes()`** supaya label opsi Referal tidak kosong saat petugas membuka form pelanggan (global scope cluster di `Customer::booted()`). `commission_percent` default dari `Setting::defaultCommissionPercent()` tapi **tersimpan per penerima** — ubah Pengaturan tidak menyentuh penerima lama. Pelanggan dapat `referral_id` nullable (`nullOnDelete`); opsinya hanya penerima aktif dan **bukan dirinya sendiri**. Akses super_admin+admin; petugas tetap boleh memilih Referal di form pelanggan) | ✅ | `app/Models/CommissionRecipient.php`, `app/Enums/RecipientType.php`, `app/Filament/Resources/CommissionRecipients/`, `app/Policies/CommissionRecipientPolicy.php`, migrations `2026_07_31_00001{0,1}`, `Customers/{Schemas/CustomerForm,Tables/CustomersTable,Pages/ListCustomers}.php`, `tests/Feature/{CommissionRecipientModuleTest,CustomerModuleTest}.php` |
| Pengaturan — sub-menu `Master` (di luar TASK: tabel `settings` **key-value** — setting baru = satu baris data, bukan migration baru; fallback `Setting::DEFAULTS` supaya DB fresh tetap benar tanpa seeder; **super_admin only** + guard `abort_unless` di `save()`. Isi saat ini hanya **komisi default 4%**; `Setting::defaultCommissionPercent()` dipakai sebagai nilai awal `commission_percent` di modul Penerima Komisi. Halaman = Filament Page ber-form via `content()` + `Form`/`EmbeddedSchema`/`InteractsWithFormActions`, bukan Resource) | ✅ | `app/Models/Setting.php`, `app/Filament/Pages/Settings.php`, migration `2026_07_31_000009`, `tests/Feature/SettingsPageTest.php` |
| Export Excel per tabel (di luar TASK: header action "Export Excel" di Tagihan Bulanan, Pelanggan, Setoran Petugas, Transaksi; mengekspor query yang sedang tampil — filter/search/sort tabel ikut — nominal ditulis sebagai angka mentah supaya bisa di-SUM; CEO+admin only) | ✅ | `app/Filament/Actions/ExportTableAction.php`, `app/Exports/Xlsx.php`, `{ListCustomers,ListTransactions,ListOfficerDeposits}::getHeaderActions()`, `MonthlyBilling::getHeaderActions()` |
| Laporan — TASK-12 (**Excel-only, PDF ditunda**; 3 sub-menu laporan terpisah di grup `Reports`: Rekap Pembayaran, Setoran Petugas, Tunggakan; **filter rentang tanggal** default bulan berjalan, basis `paid_at`/`deposited_at` via `BillingService::rangeSummary`; CEO+admin only. Default app locale kini `id`) | ✅ | `app/Filament/Pages/{AbstractRangeReportPage,PaymentRecapReport,OfficerDepositReport,ArrearsReport}.php`, `resources/views/filament/pages/report-range.blade.php`, `app/Exports/`, `app/Services/BillingService.php`, `tests/Feature/ReportPageTest.php` |

| Tema "NOC Console" (di luar TASK; custom Filament theme + Tailwind 4) — dark-first, palet primary cyan + gray navy, wordmark teks bergradient, favicon SVG, backdrop login, chip periode di topbar, strip status jaringan, stat card berikon + sparkline | ✅ | `resources/css/filament/admin/theme.css`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Filament/Widgets/NetworkStatusStrip.php`, `resources/views/filament/{brand,login-backdrop,topbar-status}.blade.php`, `tests/Feature/{PanelThemeTest,NetworkStatusStripTest}.php` |

**Spesifikasi modul billing ada di `docs/files/PionTech_AI_Agent_Tasks/`** (PRD_CONTEXT.html + TASK-01..16). Kerjakan sesuai urutan task & dependency di TASK-00. Selanjutnya: TASK-13.

### Catatan tema

- **Semua override CSS terkumpul di `resources/css/filament/admin/theme.css`** memakai CSS hook `fi-*` bawaan Filament v5 — jangan menyebar utility styling ke Blade. Token khusus tema diawali `--noc-*`.
- Warna stat card **tidak ada di elemen root**-nya; Filament hanya menaruh `fi-color-*` di description/chart. Garis aksen kartu membacanya lewat `:has()`. Konsekuensinya: stat tanpa description **dan** tanpa chart tidak akan berwarna.
- Widget dengan custom view **wajib** membungkus root-nya dengan `<x-filament-widgets::widget>` — komponen itulah yang menerapkan `$columnSpan` ke grid dashboard.
- Chart pakai `borderColor: 'transparent'`, bukan warna surface, supaya tidak jadi cincin gelap di light mode.

## Checklist Modul Baru

1. Tulis spec modul (format `sippw/.plan/modules/`: overview, schema, relationships, permissions).
2. Migration (ikuti konvensi kolom di atas).
3. Enum baru bila ada kolom pilihan → `app/Enums/` + `HasLabel`.
4. Model + relationships + casts.
5. `php artisan make:filament-resource X --generate` lalu rapikan Schemas/Tables.
6. Access gate (`canAccess`) + scoping (`getEloquentQuery` via ScopeService) sesuai matriks permission.
7. Feature test kontrol akses.
8. Update tabel "Implemented Modules" di file ini.
