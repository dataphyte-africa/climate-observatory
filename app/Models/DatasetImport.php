<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetImport extends Model
{
    protected $table = 'imports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'import_id');
    }

    public function stagedRecords(): HasMany
    {
        return $this->hasMany(ImportStagedRecord::class, 'import_id');
    }
}
