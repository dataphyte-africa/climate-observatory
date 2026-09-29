<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryYearSectorGasValueEdit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['previous_values' => 'array', 'updated_values' => 'array'];
    }
}
