<?php

namespace App\Imports\Contracts;

use App\Models\DatasetVersion;

interface CsvImportSchema
{
    public function key(): string;

    public function targetTable(): string;

    /**
     * @return array{required: array<int, string>, optional: array<int, string>}
     */
    public function columns(): array;

    /**
     * @param  array<int, string>  $headers
     */
    public function validateHeaders(array $headers): void;

    public function transform(array $row, DatasetVersion $version, array $options = []): array;

    /**
     * Return the stable, version-scoped identity for one canonical fact row.
     *
     * The importer hashes this value and the database enforces its uniqueness.
     * A repeat upload therefore updates the matching observation instead of
     * creating another copy.
     */
    public function naturalKey(array $record): string;
}
