<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeographyShape extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bbox' => 'array',
            'metadata' => 'array',
            'area_sq_km' => 'decimal:4',
            'centroid_latitude' => 'decimal:7',
            'centroid_longitude' => 'decimal:7',
        ];
    }

    public function geography(): BelongsTo
    {
        return $this->belongsTo(Geography::class);
    }
}
