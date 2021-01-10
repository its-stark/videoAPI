<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Models\UserRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * list all rooms or if email given rooms for user with email
     * @param Request $request
     * @param string $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function listRooms(Request $request, $user_id = ""): JsonResponse
    {
        return response()->json("Rooms booked " . $user_id . $request);
    }

    /**
     * book room with start date, max participants and duration
     * @param Request $request
     * @return JsonResponse - status, RoomToken
     */
    function bookRoom(Request $request): JsonResponse
    {
        $start_date = $request->post('start_date');
        $duration = ($request->post('duration') != null) ? $request->post('duration') : 60;
        $max_participants = ($request->post('participants') != null) ? $request->post('participants') : 2;
        $user_token = $request->post('user_id');

        if($start_date != null && $duration != null && $user_token != null) {
            $users = User::where('user_token', '=', $user_token)->get();
            if(count($users) == 0){
                //create new user
                $user = new User();
                $user->user_token = $user_token;
                $user->save();
                $user_id = $user->id;
            }else{
                $user_id = $users[0]->id;
            }

            ///create room
            $room = new Room();
            $room->start_date = $start_date;
            $room->max_duration = $duration;
            $room->max_participants = $max_participants;
            $room->save();

            //link user to room
            $ur = new UserRoom();
            $ur->user_id = $user_id;
            $ur->room_id = $room->id;
            $ur->save();

            return response()->json(array('status_code' => 200, 'status_msg' => 'room booked', 'id' => $room->id));
        }else {
            return response()->json(array('status_code' => 401, 'status_msg' => 'could not create booking'));
        }

        //return response()->json(array('status_code' => 400, 'status_msg' => 'could not create booking'));
    }

    function createRoom(){

    }

    function terminateRoom(){

    }
}
