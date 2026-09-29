<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return [
            'api_enabled' => 'boolean',
            'download_enabled' => 'boolean',
            'download_count' => 'integer',
            'featured' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DatasetVersion::class);
    }
}
