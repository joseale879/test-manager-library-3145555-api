<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Adapters\Outbound\Persistence\Records\UserRecord;
use App\Application\Exceptions\UsernameTaken;
use App\Application\Ports\Outbound\UserRepository;
use App\Domain\User;
use App\Domain\UserRole;
use Illuminate\Database\QueryException;

final class EloquentUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?User
    {
        $row = UserRecord::query()->where('username', User::normalizeUsername($username))->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function save(User $user): void
    {
        $attributes = [
            'username' => $user->username(),
            'password_hash' => $user->passwordHash(),
            'role' => $user->role()->value,
        ];

        try {
            $record = UserRecord::query()->find($user->id()) ?? new UserRecord(['id' => $user->id()]);
            $record->fill($attributes)->save();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'user_username_unique')) {
                throw new UsernameTaken('Username is already in use.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function toDomain(object $row): User
    {
        return User::register(
            $row->id,
            $row->username,
            $row->password_hash,
            UserRole::from($row->role),
        );
    }
}
