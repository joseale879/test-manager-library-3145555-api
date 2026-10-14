<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    public int $hashCalls = 0;

    public int $verifyCalls = 0;

    public function hash(string $plainTextPassword): string
    {
        $this->hashCalls++;

        return 'test-hash:'.$plainTextPassword;
    }

    public function verify(string $plainTextPassword, string $passwordHash): bool
    {
        $this->verifyCalls++;

        return 'test-hash:'.$plainTextPassword === $passwordHash;
    }
}
