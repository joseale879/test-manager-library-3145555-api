<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

interface CoverStorage
{
    public function store(string $contents): string;

    public function read(string $coverKey): ?StoredCover;

    public function delete(string $coverKey): void;
}
