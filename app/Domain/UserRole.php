<?php

declare(strict_types=1);

namespace App\Domain;

enum UserRole: string
{
    case ADMIN = 'admin';
    case LIBRARIAN = 'librarian';
}
