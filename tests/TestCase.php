<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Panel memakai ->viteTheme(); tanpa ini setiap render halaman butuh
        // public/build/manifest.json yang tidak dibuat saat test.
        $this->withoutVite();
    }
}
