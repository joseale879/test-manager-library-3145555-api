<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\CategoryNotFound;
use App\Application\Exceptions\ForbiddenRole;
use App\Application\Exceptions\IsbnTaken;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Ports\Outbound\CategoryRepository;
use App\Domain\Book;
use App\Domain\User;
use App\Domain\UserRole;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;

final readonly class CreateBookService
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
        string $isbnValue,
        string $categoryId,
        int $totalCopies,
    ): Book {
        if ($actor->role() !== UserRole::ADMIN) {
            throw new ForbiddenRole('Only an admin can create books.');
        }

        $isbn = Isbn::from($isbnValue);

        if (! $this->categories->exists($categoryId)) {
            throw new CategoryNotFound('Category does not exist.');
        }

        if ($this->books->findActiveByIsbn($isbn) !== null) {
            throw new IsbnTaken('ISBN is already assigned to an active book.');
        }

        $book = Book::register(
            $bookId,
            $title,
            $author,
            $isbn,
            $categoryId,
            Quantity::from($totalCopies),
        );

        $this->books->save($book);

        return $book;
    }
}
