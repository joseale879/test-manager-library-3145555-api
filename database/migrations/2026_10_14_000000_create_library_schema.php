<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS library');

        Schema::create('library.category', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
        });

        Schema::create('library.book', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 250);
            $table->string('author', 200);
            $table->char('isbn', 13);
            $table->uuid('category_id');
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('cover_key', 512)->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->foreign('category_id')
                ->references('id')
                ->on('library.category')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE library.book ADD CONSTRAINT ck_book_available_range CHECK (available_copies >= 0 AND available_copies <= total_copies)');

        Schema::create('library.member', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('document_number', 30)->unique();
        });

        Schema::create('library.user', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('username', 120)->unique();
            $table->string('password_hash', 512);
            $table->string('role', 40);
        });

        Schema::create('library.loan', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('loaned_by_user_id');
            $table->timestampTz('loaned_at');

            $table->foreign('member_id')
                ->references('id')
                ->on('library.member')
                ->restrictOnDelete();

            $table->foreign('loaned_by_user_id')
                ->references('id')
                ->on('library.user')
                ->restrictOnDelete();
        });

        Schema::create('library.loan_item', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('loan_id');
            $table->uuid('book_id');
            $table->string('book_title', 250);
            $table->char('isbn', 13);
            $table->string('category_name', 120);
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();

            $table->foreign('loan_id')
                ->references('id')
                ->on('library.loan')
                ->cascadeOnDelete();

            $table->foreign('book_id')
                ->references('id')
                ->on('library.book')
                ->restrictOnDelete();

            $table->unique(['loan_id', 'book_id']);
        });

        DB::statement('CREATE UNIQUE INDEX book_isbn_active_unique ON library.book (isbn) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX book_active_category_title_idx ON library.book (category_id, title) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX loan_loaned_at_idx ON library.loan (loaned_at)');
        DB::statement('CREATE INDEX loan_item_book_id_idx ON library.loan_item (book_id)');
        DB::statement('CREATE INDEX loan_item_open_due_at_idx ON library.loan_item (due_at) WHERE returned_at IS NULL');

        DB::table('library.category')->insert([
            ['id' => '11111111-1111-4111-8111-111111111111', 'name' => 'Novela'],
            ['id' => '22222222-2222-4222-8222-222222222222', 'name' => 'Ciencia'],
            ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'Tecnología'],
            ['id' => '44444444-4444-4444-8444-444444444444', 'name' => 'Historia'],
            ['id' => '55555555-5555-4555-8555-555555555555', 'name' => 'Infantil'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('library.loan_item');
        Schema::dropIfExists('library.loan');
        Schema::dropIfExists('library.user');
        Schema::dropIfExists('library.member');
        Schema::dropIfExists('library.book');
        Schema::dropIfExists('library.category');

        DB::statement('DROP SCHEMA IF EXISTS library');
    }
};
