<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Adapters\Outbound\Persistence\Records\CategoryRecord;
use App\Application\Ports\Outbound\CategoryRepository;

final class EloquentCategoryRepository implements CategoryRepository
{
    public function exists(string $categoryId): bool
    {
        return CategoryRecord::query()->where('id', $categoryId)->exists();
    }

    public function nameForId(string $categoryId): ?string
    {
        $name = CategoryRecord::query()->where('id', $categoryId)->value('name');

        return $name === null ? null : (string) $name;
    }
}
