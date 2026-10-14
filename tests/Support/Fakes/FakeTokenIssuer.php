<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Ports\Outbound\TokenIssuer;
use App\Domain\User;

final class FakeTokenIssuer implements TokenIssuer
{
    public int $calls = 0;

    public function issue(User $user): string
    {
        $this->calls++;

        return 'test-token:'.$user->username();
    }
}
