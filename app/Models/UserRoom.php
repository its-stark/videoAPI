<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRoom extends Model
{
    use HasFactory;

    /**
     * @var
     */
    public $user_id;
    /**
     * @var
     */
    public $room_id;
}
