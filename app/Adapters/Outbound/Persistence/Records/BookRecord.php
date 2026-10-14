<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence\Records;

use Illuminate\Database\Eloquent\Model;

final class BookRecord extends Model
{
    protected $table = 'library.book';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
