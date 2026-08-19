<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function testLocaleToggleIsVisibleInTopbar(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertSee('locale/id')
            ->assertSee('locale/en');
    }

    public function testSwitchingLocaleIsAppliedOnSubsequentRequests(): void
    {
        $this->get('/locale/id')->assertRedirect();
        $this->assertSame('id', session('locale'));

        $this->get('/admin/login');
        $this->assertSame('id', app()->getLocale());

        $this->get('/locale/en')->assertRedirect();
        $this->get('/admin/login');
        $this->assertSame('en', app()->getLocale());
    }

    public function testUnsupportedLocaleIsRejected(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }
}
