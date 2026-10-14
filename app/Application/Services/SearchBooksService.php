<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Services\Queries\BookSearch;
use App\Application\Services\Results\BookPage;

final readonly class SearchBooksService
{
    public function __construct(private BookRepository $books) {}

    public function execute(BookSearch $search): BookPage
    {
        return $this->books->search($search);
    }
}
