<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPeriodIndicatorValueEdit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'updated_values' => 'array',
        ];
    }

    public function rainfallValue(): BelongsTo
    {
        return $this->belongsTo(AdminPeriodIndicatorValue::class, 'admin_period_indicator_value_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_user_id');
    }
}
