<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin Builder
 */

class Room extends Model
{
    use HasFactory;

    /**
     * @var array|mixed|string
     */
    public $start_date;
    /**
     * @var mixed
     */
    public $id;
    /**
     * @var array|int|mixed|string
     */
    public $max_duration;
    /**
     * @var array|int|mixed|string|null
     */
    public $max_participants;
}
