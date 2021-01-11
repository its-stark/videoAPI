<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin Builder
 * @property mixed max_participants
 * @property array|mixed|string|null user_token
 * @property array|int|mixed|string|null max_duration
 * @property array|mixed|string start_date
 */

class Room extends Model
{
    use HasFactory;

}
