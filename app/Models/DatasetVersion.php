<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'import_started_at' => 'datetime',
            'import_completed_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DatasetFile::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(DatasetImport::class);
    }
}
