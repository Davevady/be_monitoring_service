<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;

class AppRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Aturan untuk testing - alert jika response time > 1000ms
        \App\Model\AppRule::create([
            'app_name' => 'core',
            'max_duration' => 1000, // 1 detik
            'is_active' => true,
            'alert_channels' => ['telegram'],
            'cooldown_minutes' => 5,
        ]);
    }
}
