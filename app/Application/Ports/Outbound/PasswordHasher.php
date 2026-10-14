<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface PasswordHasher
{
    public function hash(string $plainTextPassword): string;

    public function verify(string $plainTextPassword, string $passwordHash): bool;
}
