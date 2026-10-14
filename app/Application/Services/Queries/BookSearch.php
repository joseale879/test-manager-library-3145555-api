<?php

declare(strict_types=1);

namespace App\Application\Services\Queries;

use InvalidArgumentException;

final readonly class BookSearch
{
    public function __construct(
        public ?string $query,
        public ?string $categoryId,
        public int $page,
        public int $size,
    ) {
        if ($page < 1 || $size < 1 || $size > 50) {
            throw new InvalidArgumentException('Page must be positive and size must be between one and 50.');
        }
    }
}
