<?php

use Illuminate\Http\Request;
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

/**
 * Get all bookeed rooms or rooms for a mail address
 * @param $email - users email address
 * @return $json_array - rooms
 */
Route::middleware('basic_auth')->get('room/book/{email}', function (Request $request, $email) {
    //var_dump($request);
    return "Rooms booked ".$email;
})->name("booked.rooms");

/**
 * book a room for user with email
 * @param $email
 * @return $json_array - status code, room_token
 */
Route::middleware('basic_auth')->post('room/book', function (Request $request) {
    //var_dump($request);
    return response()->json("Book room for ".$request->post("email")." at ".$request->post("date")." for ".$request->post('duration'));
})->name("book.room");


/**
 * create the room for booking
 * @param $room_token
 * @return $json_array - status, link
 */
Route::middleware('basic_auth')->post('room/create', function (Request $request) {
    return response()->json("Book room for ".$request->post("email")." at ".$request->post("date")." for ".$request->post('duration'));
})->name("create.room");


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
