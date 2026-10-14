<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\User;
use App\Domain\UserRole;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function test_it_normalizes_username_by_trimming_and_lowercasing(): void
    {
        $user = User::register('user-1', ' Ana ', 'hashed-password', UserRole::LIBRARIAN);

        self::assertSame('ana', $user->username());
    }

    public function test_it_accepts_only_documented_roles(): void
    {
        $admin = User::register('user-1', 'root', 'hashed-password', UserRole::ADMIN);
        $librarian = User::register('user-2', 'ana', 'hashed-password', UserRole::LIBRARIAN);

        self::assertSame(UserRole::ADMIN, $admin->role());
        self::assertSame(UserRole::LIBRARIAN, $librarian->role());
    }

    public function test_it_rejects_a_blank_or_oversized_username(): void
    {
        foreach (['  ', str_repeat('a', 121)] as $username) {
            try {
                User::register('user-1', $username, 'hashed-password', UserRole::LIBRARIAN);
                self::fail('An invalid username should be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_it_accepts_a_hash_instead_of_a_plaintext_password(): void
    {
        $user = User::register('user-1', 'ana', '$2y$12$example-hash', UserRole::LIBRARIAN);

        self::assertSame('$2y$12$example-hash', $user->passwordHash());
    }
}
