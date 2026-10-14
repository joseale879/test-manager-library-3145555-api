<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Adapters\Outbound\Persistence\EloquentBookRepository;
use App\Adapters\Outbound\Persistence\EloquentMemberRepository;
use App\Adapters\Outbound\Persistence\EloquentUserRepository;
use App\Adapters\Outbound\Persistence\Records\MemberRecord;
use App\Application\Exceptions\IsbnTaken;
use App\Application\Exceptions\UsernameTaken;
use App\Application\Services\Queries\BookSearch;
use App\Domain\Book;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\UserRole;
use App\Domain\Values\Isbn;
use App\Domain\Values\Quantity;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PersistenceRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_repository_persists_searches_and_loads_active_books(): void
    {
        $repository = new EloquentBookRepository;
        $book = Book::register(
            '11111111-1111-4111-8111-111111111111',
            'Dune',
            'Frank Herbert',
            Isbn::from('9780306406157'),
            '11111111-1111-4111-8111-111111111111',
            Quantity::from(2),
        );

        $repository->save($book);

        $found = $repository->findActiveById($book->id());
        $search = $repository->search(new BookSearch('frank', null, 1, 20));

        self::assertNotNull($found);
        self::assertSame('Dune', $found->title());
        self::assertSame(2, $found->availableCopies());
        self::assertSame($book->id(), $repository->findActiveByIsbn($book->isbn())?->id());
        self::assertSame(
            [$book->id()],
            array_map(static fn (Book $result): string => $result->id(), array_values($repository->findActiveByIds([$book->id()]))),
        );
        self::assertSame(1, $search->total);
        self::assertSame('Dune', $search->books[0]->title());
    }

    public function test_member_and_user_repositories_reconstitute_domain_entities(): void
    {
        $memberId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $userId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        MemberRecord::query()->create([
            'id' => $memberId,
            'full_name' => 'Ana Pérez',
            'document_number' => 'CC-123',
        ]);
        $user = User::register($userId, ' Ana ', 'password-hash', UserRole::LIBRARIAN);
        $users = new EloquentUserRepository;
        $users->save($user);

        $member = (new EloquentMemberRepository)->findById($memberId);
        $foundUser = $users->findByUsername('ANA');

        self::assertInstanceOf(Member::class, $member);
        self::assertSame('Ana Pérez', $member->fullName());
        self::assertSame('ana', $foundUser?->username());
        self::assertSame(UserRole::LIBRARIAN, $foundUser?->role());
        self::assertSame('password-hash', $foundUser?->passwordHash());
    }

    public function test_book_repository_maps_active_isbn_constraint_and_allows_reuse_after_retirement(): void
    {
        $repository = new EloquentBookRepository;
        $firstBook = $this->book('11111111-1111-4111-8111-111111111111');
        $repository->save($firstBook);

        $duplicateRejected = false;

        try {
            DB::transaction(fn () => $repository->save($this->book('22222222-2222-4222-8222-222222222222')));
        } catch (IsbnTaken) {
            $duplicateRejected = true;
        }

        self::assertTrue($duplicateRejected);
        self::assertSame(1, DB::table('library.book')->count());

        $firstBook->retire(new DateTimeImmutable('2026-10-14T12:00:00+00:00'));
        $repository->save($firstBook);
        $replacement = $this->book('22222222-2222-4222-8222-222222222222');
        $repository->save($replacement);

        self::assertSame($replacement->id(), $repository->findActiveByIsbn($replacement->isbn())?->id());
    }

    public function test_user_repository_maps_duplicate_username_constraint(): void
    {
        $repository = new EloquentUserRepository;
        $repository->save(User::register(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'ana',
            'hash-one',
            UserRole::LIBRARIAN,
        ));

        $this->expectException(UsernameTaken::class);
        $repository->save(User::register(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'ANA ',
            'hash-two',
            UserRole::LIBRARIAN,
        ));
    }

    public function test_book_repository_detects_open_loans_and_ignores_retired_books(): void
    {
        $bookRepository = new EloquentBookRepository;
        $memberId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $userId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        $bookId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
        MemberRecord::query()->create([
            'id' => $memberId,
            'full_name' => 'Ana Pérez',
            'document_number' => 'CC-123',
        ]);
        (new EloquentUserRepository)->save(User::register($userId, 'ana', 'hash', UserRole::LIBRARIAN));
        $book = Book::register(
            $bookId,
            'Dune',
            'Frank Herbert',
            Isbn::from('9780306406157'),
            '11111111-1111-4111-8111-111111111111',
            Quantity::from(1),
        );
        $bookRepository->save($book);

        DB::table('library.loan')->insert([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'member_id' => $memberId,
            'loaned_by_user_id' => $userId,
            'loaned_at' => '2026-10-01T09:00:00+00:00',
        ]);
        DB::table('library.loan_item')->insert([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'loan_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'book_id' => $bookId,
            'book_title' => 'Dune',
            'isbn' => '9780306406157',
            'category_name' => 'Novela',
            'due_at' => '2026-10-15T09:00:00+00:00',
            'returned_at' => null,
        ]);

        self::assertTrue($bookRepository->hasOpenLoans($bookId));

        DB::table('library.loan_item')->where('book_id', $bookId)->update(['returned_at' => '2026-10-10T09:00:00+00:00']);
        $book->retire(new DateTimeImmutable('2026-10-11T09:00:00+00:00'));
        $bookRepository->save($book);

        self::assertFalse($bookRepository->hasOpenLoans($bookId));
        self::assertNull($bookRepository->findActiveById($bookId));
    }

    private function book(string $id): Book
    {
        return Book::register(
            $id,
            'Dune',
            'Frank Herbert',
            Isbn::from('9780306406157'),
            '11111111-1111-4111-8111-111111111111',
            Quantity::from(1),
        );
    }
}
