<?php

declare(strict_types=1);

namespace App\Domain\Values;

use InvalidArgumentException;

final readonly class DocumentNumber
{
    private function __construct(private string $value) {}

    public static function from(string $value): self
    {
        if (trim($value) === '' || mb_strlen($value) > 30) {
            throw new InvalidArgumentException('Document number must contain between one and 30 characters.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
