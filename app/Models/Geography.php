<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Geography extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'alternate_codes' => 'array',
            'metadata' => 'array',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(GeographyAlias::class);
    }

    public function shapes(): HasMany
    {
        return $this->hasMany(GeographyShape::class);
    }
}
