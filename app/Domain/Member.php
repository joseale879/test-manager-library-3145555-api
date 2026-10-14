<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Values\DocumentNumber;
use InvalidArgumentException;

final readonly class Member
{
    private function __construct(
        private string $id,
        private string $fullName,
        private DocumentNumber $documentNumber,
    ) {}

    public static function register(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        if (trim($fullName) === '' || mb_strlen($fullName) > 200) {
            throw new InvalidArgumentException('Member name must contain between one and 200 characters.');
        }

        return new self($id, $fullName, $documentNumber);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function documentNumber(): DocumentNumber
    {
        return $this->documentNumber;
    }
}
