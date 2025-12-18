<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class RepositoryData extends Data
{
    public function __construct(
        public string $name,
        public string $nameWithOwner,
    ) {}
}
