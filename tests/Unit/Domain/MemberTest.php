<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Member;
use App\Domain\Values\DocumentNumber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase
{
    public function test_it_registers_a_member_with_its_document_number(): void
    {
        $member = Member::register('member-1', 'Ana Pérez', DocumentNumber::from('CC-123'));

        self::assertSame('member-1', $member->id());
        self::assertSame('Ana Pérez', $member->fullName());
        self::assertSame('CC-123', $member->documentNumber()->value());
    }

    public function test_it_rejects_a_blank_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Member::register('member-1', '  ', DocumentNumber::from('CC-123'));
    }

    public function test_document_number_rejects_blank_or_oversized_values(): void
    {
        foreach (['   ', str_repeat('1', 31)] as $value) {
            try {
                DocumentNumber::from($value);
                self::fail('An invalid document number should be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
