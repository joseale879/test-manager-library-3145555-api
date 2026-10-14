<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Services;

use App\Application\Exceptions\BookHasOpenLoans;
use App\Application\Exceptions\BookNotFound;
use App\Application\Exceptions\CategoryNotFound;
use App\Application\Exceptions\ForbiddenRole;
use App\Application\Exceptions\IsbnTaken;
use App\Application\Services\CreateBookService;
use App\Application\Services\EditBookService;
use App\Application\Services\GetBookService;
use App\Application\Services\Queries\BookSearch;
use App\Application\Services\RemoveBookCoverService;
use App\Application\Services\RetireBookService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\SetBookCoverService;
use App\Domain\Book;
use App\Domain\User;
use App\Domain\UserRole;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FakeClock;
use Tests\Support\Fakes\FakeCoverStorage;
use Tests\Support\Fakes\InMemoryBookRepository;
use Tests\Support\Fakes\InMemoryCategoryRepository;

final class BookServicesTest extends TestCase
{
    public function test_admin_can_create_a_book_with_all_copies_available(): void
    {
        $books = new InMemoryBookRepository;
        $service = new CreateBookService($books, new InMemoryCategoryRepository);

        $book = $service->execute($this->admin(), 'book-1', ' Book ', 'Author', '9780306406157', 'category-1', 3);

        self::assertSame('Book', $book->title());
        self::assertSame(3, $book->availableCopies());
        self::assertSame($book, $books->findActiveById('book-1'));
    }

    public function test_book_creation_rejects_an_isbn_already_used_by_an_active_book(): void
    {
        $existing = $this->book('book-1');
        $books = new InMemoryBookRepository([$existing]);
        $service = new CreateBookService($books, new InMemoryCategoryRepository);

        $this->expectException(IsbnTaken::class);
        $service->execute($this->admin(), 'book-2', 'Another', 'Author', '9780306406157', 'category-1', 2);
    }

    public function test_book_creation_rejects_an_unknown_category(): void
    {
        $service = new CreateBookService(new InMemoryBookRepository, new InMemoryCategoryRepository);

        $this->expectException(CategoryNotFound::class);
        $service->execute($this->admin(), 'book-1', 'Book', 'Author', '9780306406157', 'unknown', 2);
    }

    public function test_only_admin_can_create_books(): void
    {
        $service = new CreateBookService(new InMemoryBookRepository, new InMemoryCategoryRepository);

        $this->expectException(ForbiddenRole::class);
        $service->execute($this->librarian(), 'book-1', 'Book', 'Author', '9780306406157', 'category-1', 2);
    }

    public function test_edit_changes_catalog_fields_and_adjusts_available_copies_by_the_same_difference(): void
    {
        $book = $this->book('book-1', totalCopies: 3);
        $book->withdraw();
        $books = new InMemoryBookRepository([$book]);
        $service = new EditBookService($books, new InMemoryCategoryRepository);

        $updated = $service->execute($this->admin(), 'book-1', ' New title ', 'New author', 'category-2', 5);

        self::assertSame('New title', $updated->title());
        self::assertSame('New author', $updated->author());
        self::assertSame('category-2', $updated->categoryId());
        self::assertSame('9780306406157', $updated->isbn()->value());
        self::assertSame(5, $updated->totalCopies()->value());
        self::assertSame(4, $updated->availableCopies());
    }

    public function test_edit_cannot_reduce_total_below_borrowed_copies(): void
    {
        $book = $this->book('book-1', totalCopies: 3);
        $book->withdraw();
        $book->withdraw();
        $books = new InMemoryBookRepository([$book]);
        $service = new EditBookService($books, new InMemoryCategoryRepository);

        $this->expectException(DomainException::class);
        $service->execute($this->admin(), 'book-1', 'Changed', 'Author', 'category-1', 1);
    }

    public function test_get_returns_not_found_for_a_retired_book(): void
    {
        $book = $this->book('book-1');
        $book->retire(new DateTimeImmutable('2026-10-14T12:00:00+00:00'));
        $service = new GetBookService(new InMemoryBookRepository([$book]));

        $this->expectException(BookNotFound::class);
        $service->execute('book-1');
    }

    public function test_search_filters_active_books_by_text_and_category_and_returns_total(): void
    {
        $books = new InMemoryBookRepository([
            $this->book('book-1', title: 'The Story', categoryId: 'category-1'),
            $this->book('book-2', title: 'Other', author: 'Story Writer', categoryId: 'category-1'),
            $this->book('book-3', title: 'Another Story', categoryId: 'category-2'),
        ]);
        $service = new SearchBooksService($books);
        $search = new BookSearch('story', 'category-1', 1, 1);

        $result = $service->execute($search);

        self::assertSame(2, $result->total);
        self::assertCount(1, $result->books);
        self::assertSame('book-2', $result->books[0]->id());
    }

    public function test_search_rejects_a_page_size_over_fifty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BookSearch(null, null, 1, 51);
    }

    public function test_retiring_a_book_is_logical_and_uses_the_clock(): void
    {
        $book = $this->book('book-1');
        $books = new InMemoryBookRepository([$book]);
        $service = new RetireBookService(
            $books,
            new FakeClock(new DateTimeImmutable('2026-10-14T12:00:00+00:00')),
        );

        $retired = $service->execute($this->admin(), 'book-1');

        self::assertSame('2026-10-14T12:00:00+00:00', $retired->deletedAt()?->format(DATE_ATOM));
        self::assertNull($books->findActiveById('book-1'));
        self::assertSame(1, $books->saveCalls);
    }

    public function test_retirement_is_rejected_while_the_book_has_open_loans(): void
    {
        $books = new InMemoryBookRepository([$this->book('book-1')]);
        $books->setOpenLoans('book-1', true);
        $service = new RetireBookService(
            $books,
            new FakeClock(new DateTimeImmutable('2026-10-14T12:00:00+00:00')),
        );

        $this->expectException(BookHasOpenLoans::class);
        $service->execute($this->admin(), 'book-1');
    }

    public function test_cover_upload_stores_only_an_opaque_key_and_replaces_previous_file(): void
    {
        $book = $this->book('book-1', coverKey: 'old-cover');
        $books = new InMemoryBookRepository([$book]);
        $covers = new FakeCoverStorage;
        $service = new SetBookCoverService($books, $covers);

        $updated = $service->execute($this->admin(), 'book-1', 'binary-content');

        self::assertSame('opaque-cover-key-1', $updated->coverKey());
        self::assertSame(['old-cover'], $covers->deletedKeys);
        self::assertSame('binary-content', $covers->storedFiles['opaque-cover-key-1']);
    }

    public function test_removing_a_cover_clears_the_key_before_deleting_the_file(): void
    {
        $book = $this->book('book-1', coverKey: 'old-cover');
        $books = new InMemoryBookRepository([$book]);
        $covers = new FakeCoverStorage;
        $service = new RemoveBookCoverService($books, $covers);

        $updated = $service->execute($this->admin(), 'book-1');

        self::assertNull($updated->coverKey());
        self::assertNull($books->findActiveById('book-1')?->coverKey());
        self::assertSame(['old-cover'], $covers->deletedKeys);
    }

    private function admin(): User
    {
        return User::register('admin-1', 'root', 'hash', UserRole::ADMIN);
    }

    private function librarian(): User
    {
        return User::register('librarian-1', 'ana', 'hash', UserRole::LIBRARIAN);
    }

    private function book(
        string $id,
        int $totalCopies = 3,
        string $title = 'Book title',
        string $author = 'Author',
        string $categoryId = 'category-1',
        ?string $coverKey = null,
    ): Book {
        return Book::register(
            $id,
            $title,
            $author,
            Isbn::from('9780306406157'),
            $categoryId,
            Quantity::from($totalCopies),
            $coverKey,
        );
    }
}
