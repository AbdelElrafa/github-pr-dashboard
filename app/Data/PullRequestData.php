<?php

namespace App\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class PullRequestData extends Data
{
    /**
     * @param  DataCollection<int, ReviewerData>  $reviewers
     */
    public function __construct(
        public int $number,
        public string $title,
        public string $url,
        public RepositoryData $repository,
        public string $author,
        public bool $isDraft,
        public string $createdAt,
        public string $updatedAt,
        public ?string $reviewDecision,
        public ?string $headRefName,
        public ?string $baseRefName,
        public ?string $mergeable,
        public ?string $ciStatus,
        public int $unresolvedCount,
        public bool $isApproved,
        #[DataCollectionOf(ReviewerData::class)]
        public DataCollection $reviewers,
    ) {}

    public function updatedAtHuman(): string
    {
        return Carbon::parse($this->updatedAt)->diffForHumans();
    }
}
