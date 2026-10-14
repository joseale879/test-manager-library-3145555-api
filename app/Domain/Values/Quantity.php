<?php

declare(strict_types=1);

namespace App\Domain\Values;

use InvalidArgumentException;

final readonly class Quantity
{
    private function __construct(private int $value) {}

    public static function from(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}
