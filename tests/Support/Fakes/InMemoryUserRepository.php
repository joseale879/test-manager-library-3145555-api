<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    /** @param list<User> $users */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->users[$user->username()] = $user;
        }
    }

    public function findByUsername(string $username): ?User
    {
        return $this->users[$username] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[$user->username()] = $user;
    }

    /** @return list<User> */
    public function all(): array
    {
        return array_values($this->users);
    }
}
