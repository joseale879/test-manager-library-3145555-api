<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\CategoryRepository;

final class InMemoryCategoryRepository implements CategoryRepository
{
    /** @param list<string> $categoryIds */
    public function __construct(private readonly array $categoryIds = ['category-1', 'category-2']) {}

    public function exists(string $categoryId): bool
    {
        return in_array($categoryId, $this->categoryIds, true);
    }
}
