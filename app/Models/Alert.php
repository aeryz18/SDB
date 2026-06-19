<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'type', 'message', 'value', 'emailed_at', 'resolved_at'])]
class Alert extends Model
{
    protected function casts(): array
    {
        return [
            'value'       => 'decimal:2',
            'emailed_at'  => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
