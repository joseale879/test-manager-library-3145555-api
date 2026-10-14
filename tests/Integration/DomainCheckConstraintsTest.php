<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DomainCheckConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_book_without_positive_total_copies(): void
    {
        try {
            DB::table('library.book')->insert([
                'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'isbn' => '9780306406157',
                'category_id' => '11111111-1111-4111-8111-111111111111',
                'total_copies' => 0,
                'available_copies' => 0,
                'cover_key' => null,
                'deleted_at' => null,
            ]);

            self::fail('PostgreSQL accepted a book with total_copies = 0.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('ck_book_total_positive', $exception->getMessage());
        }
    }

    public function test_database_rejects_a_user_with_an_unsupported_role(): void
    {
        try {
            DB::table('library.user')->insert([
                'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'username' => 'ana',
                'password_hash' => 'not-a-real-hash',
                'role' => 'administrator',
            ]);

            self::fail('PostgreSQL accepted a role outside admin and librarian.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('ck_user_role', $exception->getMessage());
        }
    }
}
