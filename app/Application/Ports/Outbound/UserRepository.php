<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\User;

interface UserRepository
{
    public function findByUsername(string $username): ?User;

    public function save(User $user): void;
}
