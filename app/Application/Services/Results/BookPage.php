<?php

declare(strict_types=1);

namespace App\Application\Services\Results;

use App\Domain\Book;

final readonly class BookPage
{
    /** @param list<Book> $books */
    public function __construct(
        public array $books,
        public int $total,
    ) {}
}
