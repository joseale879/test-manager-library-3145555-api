<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\ForbiddenRole;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CoverStorage;
use App\Domain\Book;
use App\Domain\User;
use App\Domain\UserRole;

final readonly class SetBookCoverService
{
    public function __construct(
        private BookRepository $books,
        private CoverStorage $covers,
    ) {}

    public function execute(User $actor, string $bookId, string $contents): Book
    {
        if ($actor->role() !== UserRole::ADMIN) {
            throw new ForbiddenRole('Only an admin can change book covers.');
        }

        $book = $this->books->findActiveById($bookId);

        if ($book === null) {
            throw new BookNotFound('Book does not exist.');
        }

        $previousCoverKey = $book->coverKey();
        $newCoverKey = $this->covers->store($contents);
        $book->changeCoverKey($newCoverKey);
        $this->books->save($book);

        if ($previousCoverKey !== null) {
            $this->covers->delete($previousCoverKey);
        }

        return $book;
    }
}
