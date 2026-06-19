<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'temperature', 'humidity', 'status', 'door_state', 'recorded_at'])]
class Reading extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'humidity'    => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
