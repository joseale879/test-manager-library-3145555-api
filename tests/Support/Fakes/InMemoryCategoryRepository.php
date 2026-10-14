<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\CategoryRepository;

final class InMemoryCategoryRepository implements CategoryRepository
{
    /** @param array<string, string> $categoryNames */
    public function __construct(private readonly array $categoryNames = [
        'category-1' => 'Reference',
        'category-2' => 'Fiction',
    ]) {}

    public function exists(string $categoryId): bool
    {
        return array_key_exists($categoryId, $this->categoryNames);
    }

    public function nameForId(string $categoryId): ?string
    {
        return $this->categoryNames[$categoryId] ?? null;
    }
}
