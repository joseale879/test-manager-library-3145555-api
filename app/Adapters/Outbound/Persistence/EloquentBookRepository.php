<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Adapters\Outbound\Persistence\Records\BookRecord;
use App\Application\Exceptions\IsbnTaken;
use App\Application\Ports\Outbound\BookRepository;
use App\Application\Services\Queries\BookSearch;
use App\Application\Services\Results\BookPage;
use App\Domain\Book;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentBookRepository implements BookRepository
{
    public function findActiveById(string $id): ?Book
    {
        $row = BookRecord::query()
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findActiveByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return BookRecord::query()
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->id => $this->toDomain($row)])
            ->all();
    }

    public function findActiveByIsbn(Isbn $isbn, ?string $excludingId = null): ?Book
    {
        $query = BookRecord::query()
            ->where('isbn', $isbn->value())
            ->whereNull('deleted_at');

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        $row = $query->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('library.loan_item')
            ->where('book_id', $bookId)
            ->whereNull('returned_at')
            ->exists();
    }

    public function search(BookSearch $search): BookPage
    {
        $query = BookRecord::query()->whereNull('deleted_at');

        if ($search->query !== null && $search->query !== '') {
            $pattern = '%'.$search->query.'%';
            $query->whereRaw('(title ILIKE ? OR author ILIKE ?)', [$pattern, $pattern]);
        }

        if ($search->categoryId !== null) {
            $query->where('category_id', $search->categoryId);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('title')
            ->offset(($search->page - 1) * $search->size)
            ->limit($search->size)
            ->get();

        return new BookPage(
            $rows->map(fn (object $row): Book => $this->toDomain($row))->all(),
            $total,
        );
    }

    public function save(Book $book): void
    {
        $attributes = [
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn()->value(),
            'category_id' => $book->categoryId(),
            'total_copies' => $book->totalCopies()->value(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
            'deleted_at' => $book->deletedAt()?->format('Y-m-d H:i:s.uP'),
        ];

        try {
            $record = BookRecord::query()->find($book->id()) ?? new BookRecord(['id' => $book->id()]);
            $record->fill($attributes)->save();
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'book_isbn_active_unique')) {
                throw new IsbnTaken('ISBN is already assigned to an active book.', previous: $exception);
            }

            throw $exception;
        }
    }

    private function toDomain(object $row): Book
    {
        return Book::restore(
            $row->id,
            $row->title,
            $row->author,
            Isbn::from(rtrim($row->isbn)),
            $row->category_id,
            Quantity::from((int) $row->total_copies),
            (int) $row->available_copies,
            $row->deleted_at === null ? null : new DateTimeImmutable($row->deleted_at),
            $row->cover_key,
        );
    }
}
