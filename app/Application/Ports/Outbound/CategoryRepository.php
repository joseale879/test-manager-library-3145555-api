<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface CategoryRepository
{
    public function exists(string $categoryId): bool;

    public function nameForId(string $categoryId): ?string;
}
