<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A party as printed on the result sheet (entered by administrators). */
class ElectionParty extends Model
{
    protected $guarded = ['id'];
}
