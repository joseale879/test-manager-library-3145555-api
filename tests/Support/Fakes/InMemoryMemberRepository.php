<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\MemberRepository;
use App\Domain\Member;

final class InMemoryMemberRepository implements MemberRepository
{
    /** @var array<string, Member> */
    private array $members = [];

    /** @param list<Member> $members */
    public function __construct(array $members = [])
    {
        foreach ($members as $member) {
            $this->members[$member->id()] = $member;
        }
    }

    public function findById(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }
}
