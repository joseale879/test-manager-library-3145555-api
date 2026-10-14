<?php

declare(strict_types=1);

namespace App\Domain\Values;

use InvalidArgumentException;

final readonly class Isbn
{
    private function __construct(private string $value) {}

    public static function from(string $value): self
    {
        if (preg_match('/\A[0-9]{13}\z/', $value) !== 1) {
            throw new InvalidArgumentException('ISBN must contain exactly 13 digits.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
