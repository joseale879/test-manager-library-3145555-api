<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\ForbiddenRole;
use App\Application\Exceptions\UsernameTaken;
use App\Application\Ports\Outbound\PasswordHasher;
use App\Application\Ports\Outbound\UserRepository;
use App\Application\Services\Results\CreatedLibrarian;
use App\Domain\User;
use App\Domain\UserRole;

final readonly class CreateLibrarianService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
    ) {}

    public function execute(User $actor, string $userId, string $username, string $plainTextPassword): CreatedLibrarian
    {
        if ($actor->role() !== UserRole::ADMIN) {
            throw new ForbiddenRole('Only an admin can create a librarian.');
        }

        $normalizedUsername = User::normalizeUsername($username);

        if ($this->users->findByUsername($normalizedUsername) !== null) {
            throw new UsernameTaken('Username is already in use.');
        }

        $librarian = User::register(
            $userId,
            $normalizedUsername,
            $this->passwordHasher->hash($plainTextPassword),
            UserRole::LIBRARIAN,
        );

        $this->users->save($librarian);

        return new CreatedLibrarian($librarian->id(), $librarian->username());
    }
}
