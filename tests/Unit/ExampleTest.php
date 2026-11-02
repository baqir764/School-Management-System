<?php

namespace Tests\Unit;

use App\Models\Setting;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_current_creates_a_default_row_when_none_exists(): void
    {
        $this->assertSame(0, Setting::query()->count());

        $setting = Setting::current();

        $this->assertSame(1, Setting::query()->count());
        $this->assertSame('School Management System', $setting->school_name);
    }
}
