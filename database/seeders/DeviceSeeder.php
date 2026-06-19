<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $device = Device::firstOrCreate(
            ['firebase_path' => 'drybox'],
            [
                'user_id'  => $user->id,
                'name'     => 'DryBox Unit 1',
                'location' => 'Main Storage',
                'is_active' => true,
            ]
        );

        DeviceSetting::firstOrCreate(
            ['device_id' => $device->id],
            [
                'warn_humidity'           => 35,
                'crit_humidity'           => 45,
                'temp_min'                => null,
                'temp_max'                => null,
                'fungus_alerts_enabled'   => true,
                'protection_mode'         => false,
                'door_field'              => 'door',
                'silica_last_replaced_at' => now(),
                'silica_interval_days'    => 90,
                'notify_emails'           => [$user->email],
                'alert_cooldown_minutes'  => 30,
            ]
        );
    }
}
