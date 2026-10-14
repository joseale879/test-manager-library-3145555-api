<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\BookNotFound;
use App\Application\Ports\Outbound\BookRepository;
use App\Domain\Book;

final readonly class GetBookService
{
    public function __construct(private BookRepository $books) {}

    public function execute(string $bookId): Book
    {
        return $this->books->findActiveById($bookId)
            ?? throw new BookNotFound('Book does not exist.');
    }
}
