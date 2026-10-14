<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Adapters\Outbound\Persistence\Records\MemberRecord;
use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Member;
use App\Domain\Values\DocumentNumber;

final class EloquentMemberRepository implements MemberRepository
{
    public function findById(string $id): ?Member
    {
        $row = MemberRecord::query()->find($id);

        if ($row === null) {
            return null;
        }

        return Member::register(
            $row->id,
            $row->full_name,
            DocumentNumber::from($row->document_number),
        );
    }
}
