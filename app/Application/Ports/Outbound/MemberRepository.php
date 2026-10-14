<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Member;

interface MemberRepository
{
    public function findById(string $id): ?Member;
}
