<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Storage;

use App\Application\Exceptions\InvalidCover;
use App\Application\Ports\Outbound\CoverStorage;
use App\Application\Ports\Outbound\StoredCover;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

final class LocalCoverStorage implements CoverStorage
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const ALLOWED_MEDIA_TYPES = ['image/jpeg', 'image/png'];

    public function store(string $contents): string
    {
        $mediaType = $this->detectMediaType($contents);

        if (
            $contents === ''
            || strlen($contents) > self::MAX_BYTES
            || ! in_array($mediaType, self::ALLOWED_MEDIA_TYPES, true)
        ) {
            throw new InvalidCover('Cover must be a JPEG or PNG image no larger than 2 MB.');
        }

        $coverKey = bin2hex(random_bytes(20));

        if (! Storage::disk('covers')->put($coverKey, $contents)) {
            throw new RuntimeException('Cover could not be stored.');
        }

        return $coverKey;
    }

    public function read(string $coverKey): ?StoredCover
    {
        if (! $this->isValidKey($coverKey) || ! Storage::disk('covers')->exists($coverKey)) {
            return null;
        }

        $contents = Storage::disk('covers')->get($coverKey);
        $mediaType = $this->detectMediaType($contents);

        if (! in_array($mediaType, self::ALLOWED_MEDIA_TYPES, true)) {
            throw new RuntimeException('Stored cover has an invalid media type.');
        }

        return new StoredCover($contents, $mediaType);
    }

    public function delete(string $coverKey): void
    {
        if (! $this->isValidKey($coverKey)) {
            throw new InvalidArgumentException('Cover key is invalid.');
        }

        Storage::disk('covers')->delete($coverKey);
    }

    private function detectMediaType(string $contents): ?string
    {
        if ($contents === '') {
            return null;
        }

        $mediaType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        return is_string($mediaType) ? $mediaType : null;
    }

    private function isValidKey(string $coverKey): bool
    {
        return preg_match('/\A[a-f0-9]{40}\z/', $coverKey) === 1;
    }
}
