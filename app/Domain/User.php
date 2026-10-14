<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

final readonly class User
{
    private function __construct(
        private string $id,
        private string $username,
        private string $passwordHash,
        private UserRole $role,
    ) {}

    public static function register(
        string $id,
        string $username,
        string $passwordHash,
        UserRole $role,
    ): self {
        $normalizedUsername = mb_strtolower(trim($username));

        if ($normalizedUsername === '' || mb_strlen($normalizedUsername) > 120) {
            throw new InvalidArgumentException('Username must contain between one and 120 characters.');
        }

        if ($passwordHash === '' || mb_strlen($passwordHash) > 512) {
            throw new InvalidArgumentException('Password hash must contain between one and 512 characters.');
        }

        return new self($id, $normalizedUsername, $passwordHash, $role);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): UserRole
    {
        return $this->role;
    }
}
