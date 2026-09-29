<?php

namespace App\Queries\Datasets;

use App\Models\Dataset;
use Illuminate\Support\Collection;

class DatasetCatalogQuery
{
    public function getApiVisible(): Collection
    {
        return Dataset::query()
            ->with([
                'source',
                'versions' => fn ($query) => $query
                    ->orderByDesc('activated_at')
                    ->orderByDesc('id'),
            ])
            ->where('status', 'active')
            ->where('api_enabled', true)
            ->orderBy('title')
            ->get();
    }

    public function getOne(Dataset $dataset): Dataset
    {
        return $dataset->load([
            'source',
            'versions' => fn ($query) => $query
                ->orderByDesc('activated_at')
                ->orderByDesc('id'),
        ]);
    }
}
