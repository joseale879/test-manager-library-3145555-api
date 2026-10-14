<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence\Records;

use Illuminate\Database\Eloquent\Model;

final class MemberRecord extends Model
{
    protected $table = 'library.member';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
