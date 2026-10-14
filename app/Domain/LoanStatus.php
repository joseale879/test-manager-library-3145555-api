<?php

declare(strict_types=1);

namespace App\Domain;

enum LoanStatus: string
{
    case OPEN = 'open';
    case OVERDUE = 'overdue';
    case CLOSED = 'closed';
}
