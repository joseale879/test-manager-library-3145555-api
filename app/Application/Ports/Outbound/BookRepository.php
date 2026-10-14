<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Application\Services\Queries\BookSearch;
use App\Application\Services\Results\BookPage;
use App\Domain\Book;
use App\Domain\Values\Isbn;

interface BookRepository
{
    public function findActiveById(string $id): ?Book;

    public function findActiveByIsbn(Isbn $isbn, ?string $excludingId = null): ?Book;

    public function hasOpenLoans(string $bookId): bool;

    public function search(BookSearch $search): BookPage;

    public function save(Book $book): void;
}
