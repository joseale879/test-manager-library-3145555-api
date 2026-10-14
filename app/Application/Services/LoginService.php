<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\InvalidCredentials;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\TokenIssuer;
use App\Application\Ports\Outbound\UserRepository;
use App\Application\Services\Results\LoginResult;
use App\Domain\User;
use InvalidArgumentException;

final readonly class LoginService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private TokenIssuer $tokenIssuer,
    ) {}

    public function execute(string $username, string $plainTextPassword): LoginResult
    {
        try {
            $normalizedUsername = User::normalizeUsername($username);
        } catch (InvalidArgumentException) {
            throw new InvalidCredentials('Invalid username or password.');
        }

        $user = $this->users->findByUsername($normalizedUsername);

        if ($user === null || ! $this->passwordHasher->verify($plainTextPassword, $user->passwordHash())) {
            throw new InvalidCredentials('Invalid username or password.');
        }

        return new LoginResult($this->tokenIssuer->issue($user), $user->role());
    }
}
