<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters\Outbound\Storage;

use App\Adapters\Outbound\Storage\LocalCoverStorage;
use App\Application\Exceptions\InvalidCover;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LocalCoverStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('covers');
    }

    public function test_it_stores_and_reads_a_png_with_an_opaque_key(): void
    {
        $storage = new LocalCoverStorage;
        $contents = $this->pngOfSize(512);

        $key = $storage->store($contents);
        $storedCover = $storage->read($key);

        self::assertMatchesRegularExpression('/\A[a-f0-9]{40}\z/', $key);
        self::assertSame($contents, $storedCover?->contents);
        self::assertSame('image/png', $storedCover?->mediaType);
        Storage::disk('covers')->assertExists($key);
    }

    public function test_it_accepts_a_png_at_the_two_megabyte_limit(): void
    {
        $storage = new LocalCoverStorage;

        $key = $storage->store($this->pngOfSize(2 * 1024 * 1024));

        Storage::disk('covers')->assertExists($key);
    }

    public function test_it_rejects_a_png_larger_than_two_megabytes(): void
    {
        $this->expectException(InvalidCover::class);

        (new LocalCoverStorage)->store($this->pngOfSize(2 * 1024 * 1024 + 1));
    }

    public function test_it_validates_the_content_instead_of_trusting_a_filename_or_claimed_type(): void
    {
        $this->expectException(InvalidCover::class);

        (new LocalCoverStorage)->store('not an image');
    }

    public function test_it_deletes_a_cover_by_key_and_rejects_path_like_keys(): void
    {
        $storage = new LocalCoverStorage;
        $key = $storage->store($this->pngOfSize(128));

        $storage->delete($key);

        Storage::disk('covers')->assertMissing($key);
        $this->expectException(\InvalidArgumentException::class);
        $storage->delete('../private/file');
    }

    private function pngOfSize(int $size): string
    {
        $signature = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
            true,
        );

        self::assertNotFalse($signature);

        return $signature.str_repeat("\0", $size - strlen($signature));
    }
}
