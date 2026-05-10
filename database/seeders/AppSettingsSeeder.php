<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'support_whatsapp' => '249912414288',
            'bank_name'        => 'بنك الخرطوم',
            'account_name'     => 'نهى احمد',
            'account_number'   => '8389213',
            'vendor_terms'     => 'شروط وأحكام البائعين.',
            'driver_terms'     => 'شروط وأحكام السائقين.',
            'terms_version'    => '1.0',
        ];

        foreach ($defaults as $key => $value) {
            AppSetting::set($key, $value);
        }
    }
}
