<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Values;

use App\Domain\Values\Quantity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_it_accepts_a_positive_quantity(): void
    {
        self::assertSame(1, Quantity::from(1)->value());
        self::assertSame(5, Quantity::from(5)->value());
    }

    #[DataProvider('nonPositiveQuantities')]
    public function test_it_rejects_zero_or_negative_quantities(int $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Quantity::from($value);
    }

    public static function nonPositiveQuantities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
