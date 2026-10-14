<?php

declare(strict_types=1);

namespace App\Application\Services\Results;

use App\Domain\UserRole;

final readonly class LoginResult
{
    public function __construct(
        public string $accessToken,
        public UserRole $role,
        public string $tokenType = 'Bearer',
        public int $expiresIn = 28800,
    ) {}
}
