<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\CategoryNotFound;
use App\Application\Exceptions\ForbiddenRole;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Book;
use App\Domain\User;
use App\Domain\UserRole;
use App\Domain\Values\Quantity;

final readonly class EditBookService
{
    public function __construct(
        private BookRepository $books,
        private CategoryRepository $categories,
    ) {}

    public function execute(
        User $actor,
        string $bookId,
        string $title,
        string $author,
        string $categoryId,
        int $totalCopies,
    ): Book {
        if ($actor->role() !== UserRole::ADMIN) {
            throw new ForbiddenRole('Only an admin can edit books.');
        }

        $book = $this->books->findActiveById($bookId);

        if ($book === null) {
            throw new BookNotFound('Book does not exist.');
        }

        if (! $this->categories->exists($categoryId)) {
            throw new CategoryNotFound('Category does not exist.');
        }

        $book->edit($title, $author, $categoryId, Quantity::from($totalCopies));
        $this->books->save($book);

        return $book;
    }
}
