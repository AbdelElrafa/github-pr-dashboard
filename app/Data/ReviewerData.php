<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ReviewerData extends Data
{
    public function __construct(
        public string $login,
        public string $state,
        public ?string $submittedAt = null,
    ) {}
}
