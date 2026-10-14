<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\CoverStorage;

final class FakeCoverStorage implements CoverStorage
{
    /** @var list<string> */
    public array $deletedKeys = [];

    /** @var list<array{contents: string, mediaType: string}> */
    public array $storedFiles = [];

    public function store(string $contents, string $mediaType): string
    {
        $this->storedFiles[] = ['contents' => $contents, 'mediaType' => $mediaType];

        return 'opaque-cover-key-'.count($this->storedFiles);
    }

    public function delete(string $coverKey): void
    {
        $this->deletedKeys[] = $coverKey;
    }
}
