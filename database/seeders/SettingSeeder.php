<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::query()->doesntExist()) {
            Setting::create([
                'school_name' => 'AAIWO School',
                'currency' => 'AFN',
                'timezone' => 'Asia/Kabul',
                'default_locale' => 'en',
            ]);
        }
    }
}
