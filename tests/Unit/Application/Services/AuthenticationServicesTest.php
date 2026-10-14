<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Exceptions\ForbiddenRole;
use App\Application\Exceptions\InvalidCredentials;
use App\Application\Exceptions\UsernameTaken;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\LoginService;
use App\Domain\User;
use App\Domain\UserRole;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FakePasswordHasher;
use Tests\Support\Fakes\FakeTokenIssuer;
use Tests\Support\Fakes\InMemoryUserRepository;

final class AuthenticationServicesTest extends TestCase
{
    public function test_login_returns_the_documented_token_result_for_valid_credentials(): void
    {
        $user = User::register('user-1', 'ana', 'test-hash:secret', UserRole::LIBRARIAN);
        $users = new InMemoryUserRepository([$user]);
        $hasher = new FakePasswordHasher;
        $tokens = new FakeTokenIssuer;
        $service = new LoginService($users, $hasher, $tokens);

        $result = $service->execute(' Ana ', 'secret');

        self::assertSame('test-token:ana', $result->accessToken);
        self::assertSame('Bearer', $result->tokenType);
        self::assertSame(28800, $result->expiresIn);
        self::assertSame(UserRole::LIBRARIAN, $result->role);
        self::assertSame(1, $tokens->calls);
    }

    public function test_login_uses_the_same_generic_error_for_an_unknown_user_or_wrong_password(): void
    {
        $user = User::register('user-1', 'ana', 'test-hash:secret', UserRole::LIBRARIAN);
        $users = new InMemoryUserRepository([$user]);
        $tokens = new FakeTokenIssuer;
        $service = new LoginService($users, new FakePasswordHasher, $tokens);

        try {
            $service->execute('unknown', 'secret');
            self::fail('An unknown user must not authenticate.');
        } catch (InvalidCredentials $errorForUnknownUser) {
            try {
                $service->execute('ana', 'wrong');
                self::fail('An incorrect password must not authenticate.');
            } catch (InvalidCredentials $errorForWrongPassword) {
                self::assertSame($errorForUnknownUser->getMessage(), $errorForWrongPassword->getMessage());
                self::assertSame('Invalid username or password.', $errorForUnknownUser->getMessage());
            }
        }

        self::assertSame(0, $tokens->calls);
    }

    public function test_login_treats_a_blank_username_as_invalid_credentials(): void
    {
        $service = new LoginService(new InMemoryUserRepository, new FakePasswordHasher, new FakeTokenIssuer);

        $this->expectException(InvalidCredentials::class);
        $service->execute('   ', 'secret');
    }

    public function test_admin_can_create_a_librarian_with_a_normalized_username_and_hashed_password(): void
    {
        $users = new InMemoryUserRepository;
        $hasher = new FakePasswordHasher;
        $service = new CreateLibrarianService($users, $hasher);
        $actor = User::register('admin-1', 'root', 'admin-hash', UserRole::ADMIN);

        $result = $service->execute($actor, 'user-1', ' Ana ', 'secret');

        self::assertSame('user-1', $result->id);
        self::assertSame('ana', $result->username);
        self::assertSame(UserRole::LIBRARIAN, $result->role);
        self::assertFalse(property_exists($result, 'passwordHash'));
        self::assertSame('test-hash:secret', $users->findByUsername('ana')?->passwordHash());
        self::assertSame(1, $hasher->hashCalls);
    }

    public function test_a_non_admin_cannot_create_a_librarian(): void
    {
        $users = new InMemoryUserRepository;
        $hasher = new FakePasswordHasher;
        $service = new CreateLibrarianService($users, $hasher);
        $actor = User::register('librarian-1', 'ana', 'hash', UserRole::LIBRARIAN);

        $this->expectException(ForbiddenRole::class);
        $service->execute($actor, 'user-2', 'new-user', 'secret');
    }

    public function test_duplicate_normalized_username_is_rejected_without_hashing_password(): void
    {
        $existingUser = User::register('user-1', 'ana', 'hash', UserRole::LIBRARIAN);
        $users = new InMemoryUserRepository([$existingUser]);
        $hasher = new FakePasswordHasher;
        $service = new CreateLibrarianService($users, $hasher);
        $actor = User::register('admin-1', 'root', 'admin-hash', UserRole::ADMIN);

        $this->expectException(UsernameTaken::class);
        $service->execute($actor, 'user-2', ' Ana ', 'secret');
    }
}
