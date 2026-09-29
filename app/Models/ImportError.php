<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_row' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(DatasetImport::class, 'import_id');
    }
}
