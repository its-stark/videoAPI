<?php

use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('/room')->name('room.')->middleware('basic_auth')->group(function() {
    /**
     * Get all booked rooms or rooms for a mail address
     * @param $user_id - users email address
     * @return $json_array - rooms
     */
    Route::middleware('basic_auth')->get('book/{user_id?}', [RoomController::class, 'listRooms'])
    ->name("booked.list");

    /**
     * book a room for user with email
     * @param $user_id
     * @param $date
     * @param $duration
     * @param $max_participants
     * @return $json_array - status code, room_token
     */
    Route::middleware('basic_auth')->post('book', [RoomController::class, 'bookRoom'])
    ->name("book");


    /**
     * create the room from booking
     * @param $room_token
     * @param $max_participants
     * @return $json_array - status, link
     */
    Route::middleware('basic_auth')->post("create", [RoomController::class, 'createRoom'])->name("create");
});

//Examples
//optional parameter with where over pattern
Route::get('test/{id?}', function ($theId = 0){
    return 'test: ' . $theId;
})
    //RouteServiceProvider
    /*->where([
        'id', '[0-9]+']
    )*/
    ->name('test.parameter');
