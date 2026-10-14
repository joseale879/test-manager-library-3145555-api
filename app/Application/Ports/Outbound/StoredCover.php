<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

final readonly class StoredCover
{
    public function __construct(
        public string $contents,
        public string $mediaType,
    ) {}
}
