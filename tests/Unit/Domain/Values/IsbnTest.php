<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Values;

use App\Domain\Values\Isbn;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsbnTest extends TestCase
{
    public function test_it_accepts_thirteen_digits(): void
    {
        $isbn = Isbn::from('9780306406157');

        self::assertSame('9780306406157', $isbn->value());
        self::assertSame('9780306406157', (string) $isbn);
    }

    #[DataProvider('invalidIsbns')]
    public function test_it_rejects_values_that_are_not_thirteen_digits(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Isbn::from($value);
    }

    public static function invalidIsbns(): iterable
    {
        yield 'too short' => ['978030640615'];
        yield 'too long' => ['97803064061570'];
        yield 'contains letters' => ['978030640615X'];
        yield 'contains whitespace' => ['978 030640615'];
    }
}
