<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\BookRepository;
use App\Application\Services\Queries\BookSearch;
use App\Application\Services\Results\BookPage;
use App\Domain\Book;
use App\Domain\Values\Isbn;

final class InMemoryBookRepository implements BookRepository
{
    /** @var array<string, Book> */
    private array $books = [];

    /** @var array<string, bool> */
    private array $openLoans = [];

    public int $saveCalls = 0;

    /** @param list<Book> $books */
    public function __construct(array $books = [])
    {
        foreach ($books as $book) {
            $this->books[$book->id()] = $book;
        }
    }

    public function findActiveById(string $id): ?Book
    {
        $book = $this->books[$id] ?? null;

        return $book?->isActive() ? $book : null;
    }

    public function findActiveByIds(array $ids): array
    {
        $books = [];

        foreach ($ids as $id) {
            $book = $this->findActiveById($id);

            if ($book !== null) {
                $books[$id] = $book;
            }
        }

        return $books;
    }

    public function findActiveByIsbn(Isbn $isbn, ?string $excludingId = null): ?Book
    {
        foreach ($this->books as $book) {
            if (
                $book->isActive()
                && $book->id() !== $excludingId
                && $book->isbn()->value() === $isbn->value()
            ) {
                return $book;
            }
        }

        return null;
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return $this->openLoans[$bookId] ?? false;
    }

    public function search(BookSearch $search): BookPage
    {
        $books = array_values(array_filter($this->books, function (Book $book) use ($search): bool {
            if (! $book->isActive()) {
                return false;
            }

            if ($search->categoryId !== null && $book->categoryId() !== $search->categoryId) {
                return false;
            }

            if ($search->query !== null) {
                $query = mb_strtolower($search->query);

                if (
                    mb_stripos($book->title(), $query) === false
                    && mb_stripos($book->author(), $query) === false
                ) {
                    return false;
                }
            }

            return true;
        }));

        usort($books, static fn (Book $left, Book $right): int => strcasecmp($left->title(), $right->title()));
        $total = count($books);
        $pageBooks = array_slice($books, ($search->page - 1) * $search->size, $search->size);

        return new BookPage($pageBooks, $total);
    }

    public function save(Book $book): void
    {
        $this->saveCalls++;
        $this->books[$book->id()] = $book;
    }

    public function setOpenLoans(string $bookId, bool $hasOpenLoans): void
    {
        $this->openLoans[$bookId] = $hasOpenLoans;
    }

    /** @return list<Book> */
    public function all(): array
    {
        return array_values($this->books);
    }
}
