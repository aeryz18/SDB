<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id', 'warn_humidity', 'crit_humidity', 'temp_min', 'temp_max',
    'protection_mode', 'door_field',
    'silica_last_replaced_at', 'silica_interval_days', 'silica_next_replacement_at', 'silica_notify_days_before',
    'notify_emails', 'alert_cooldown_minutes',
])]
class DeviceSetting extends Model
{
    protected function casts(): array
    {
        return [
            'notify_emails' => 'array',
            'protection_mode' => 'boolean',
            'silica_last_replaced_at' => 'datetime',
            'silica_next_replacement_at' => 'date',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
