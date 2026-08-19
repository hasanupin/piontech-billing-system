<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panduan pengguna (docs/user-guide/) disajikan dari dalam aplikasi supaya
 * hanya bisa dibuka oleh yang sudah login, dan halamannya menyesuaikan peran.
 */
class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCannotOpenTheGuide(): void
    {
        $this->get(route('guide'))->assertRedirect();
        $this->get(route('guide.file', ['file' => 'admin.html']))->assertRedirect();
    }

    public function testEachRoleLandsOnItsOwnPage(): void
    {
        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('guide'))
            ->assertRedirect(route('guide.file', ['file' => 'ceo.html']));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('guide'))
            ->assertRedirect(route('guide.file', ['file' => 'admin.html']));

        $this->actingAs($officer)
            ->get(route('guide'))
            ->assertRedirect(route('guide.file', ['file' => 'petugas.html']));
    }

    public function testServesPagesAssetsAndScreenshots(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // response()->file() mengirim berkas sebagai stream, jadi isinya dibaca
        // dari berkasnya — bukan lewat assertSee().
        $page = $this->get(route('guide.file', ['file' => 'admin.html']))->assertOk();
        $this->assertStringContainsString(
            'Panduan Admin Penagihan',
            $page->baseResponse->getFile()->getContent(),
        );

        // Tipe MIME wajib benar: browser menolak stylesheet yang dikirim sebagai
        // text/plain, dan halaman panduan jadi tampil tanpa gaya sama sekali.
        $this->get(route('guide.file', ['file' => 'assets/guide.css']))
            ->assertOk()
            ->assertHeader('content-type', 'text/css; charset=utf-8');

        $this->get(route('guide.file', ['file' => 'screenshots/dashboard-admin.png']))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $page->assertHeader('content-type', 'text/html; charset=UTF-8');
    }

    public function testRefusesPathTraversalAndNonDocumentFiles(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // Keluar dari folder panduan
        $this->get('/panduan/../../.env')->assertNotFound();
        $this->get('/panduan/'.rawurlencode('../../.env'))->assertNotFound();
        // Berkas yang bukan bagian dokumen (README memuat kredensial contoh)
        $this->get(route('guide.file', ['file' => 'README.md']))->assertNotFound();
        $this->get(route('guide.file', ['file' => 'tidak-ada.html']))->assertNotFound();
    }

    public function testGuideIsReachableFromBothPanels(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('guide'), escape: false);

        $officer = User::factory()->fieldOfficer()->create();
        Cluster::factory()->create(['officer_id' => $officer->id]);

        $this->actingAs($officer)
            ->get('/petugas/pengaturan')
            ->assertOk()
            ->assertSee(route('guide'), escape: false);
    }
}
