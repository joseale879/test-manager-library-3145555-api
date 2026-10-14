<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Ports\Outbound\StoredCover;

final class FakeCoverStorage implements CoverStorage
{
    /** @var list<string> */
    public array $deletedKeys = [];

    /** @var array<string, string> */
    public array $storedFiles = [];

    public function store(string $contents): string
    {
        $key = 'opaque-cover-key-'.(count($this->storedFiles) + 1);
        $this->storedFiles[$key] = $contents;

        return $key;
    }

    public function read(string $coverKey): ?StoredCover
    {
        return isset($this->storedFiles[$coverKey])
            ? new StoredCover($this->storedFiles[$coverKey], 'image/png')
            : null;
    }

    public function delete(string $coverKey): void
    {
        $this->deletedKeys[] = $coverKey;
    }
}
