<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman root diarahkan ke panel admin.
     */
    public function testRootRedirectsToAdminPanel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
