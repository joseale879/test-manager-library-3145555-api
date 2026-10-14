<?php

declare(strict_types=1);

namespace App\Application\Services\Results;

use App\Domain\UserRole;

final readonly class CreatedLibrarian
{
    public function __construct(
        public string $id,
        public string $username,
        public UserRole $role = UserRole::LIBRARIAN,
    ) {}
}
