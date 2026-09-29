<?php

namespace App\Imports\Exceptions;

use RuntimeException;

class CsvImportRowException extends RuntimeException
{
    public function __construct(
        public readonly ?string $column,
        string $message
    ) {
        parent::__construct($message);
    }
}
