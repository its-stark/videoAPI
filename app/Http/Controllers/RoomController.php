<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Models\UserRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Twilio\Exceptions\TwilioException;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use Twilio\Rest\Client;

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
        $user_token = ($request->post('user_id') != null && $request->post('user_id') != "") ? $request->post('user_id') : "";

        var_dump($user_token);

        if($start_date != null && $user_token != "") {
            $users = User::where('user_token', '=', $user_token)->get();
            if(count($users) == 0){
                //create new user
                echo "create new user with token ". $user_token;
                $user = new User();
                echo "set user token ".$user_token;
                $user->user_token = "$user_token";
                echo "before save";
                echo $user->save();
                echo "saved?";
                var_dump($user->id);
                $user_id = $user->id;
                echo "uid=".$user_id;

            }else{
                $user_id = $users[0]->id;
            }

            ///create room
            $room = new Room();
            $room->start_date = $start_date;
            $room->max_duration = $duration;
            $room->max_participants = $max_participants;
            $room->user_token = $user_token;
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

    function createRoom(Request $request): JsonResponse
    {
        $user_token = ($request->post('user_id') != null) ? $request->post('user_id') : "";
        $room_id = ($request->post('room_id') != null) ? $request->post('room_id') : 0;

        if($room_id > 0 && $user_token != ""){
            $this->updateRoom($request);
            //create twilio room
            $rooms = Room::where([
                ['id', '=', $room_id],
                ['user_token', '=', $user_token]
            ])->get();
            if(count($rooms) > 0) {
                $room = $rooms[0];

                //ToDo: check if start_date is in range

                $twilioRoom = $this->createTwilioRoom($room);

                var_dump($twilioRoom);

                $link = env('TWILIO_ROOM_URL').'?token='.$twilioRoom['token']."&room=".$twilioRoom['room_name']."&passcode=".env('TWILIO_PASSCODE');

                return response()->json(array('status_code' => 200, 'status_msg' => 'room created', 'link' => $link));
            }else{
                return response()->json(array('status_code' => 451, 'status_msg' => 'room not found'));
            }
        }else{
            return response()->json(array('status_code' => 400, 'status_msg' => 'invalid or missing parameters'));
        }


    }

    private function createTwilioRoom(Room $room) : ?array
    {
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_TOKEN');
        // Required for Video grant
        $room_name = 'S3M_'.$room->id;
        // An identifier for your app - can be anything you'd like
        $identity = 's3m_room';

        $client = new Client($sid, $token);

        //try to get room
        $twilioRooms = $client->video->v1->rooms->read(["uniqueName" => $room_name]);
        if(count($twilioRooms) > 0){
            $twilioRoom = $twilioRooms[0];
        }else{
            $twilioRoom = $client->video->rooms
                ->create([
                        "recordParticipantsOnConnect" => False,
                        "statusCallback" => "http://example.org",
                        "type" => "group",
                        "uniqueName" => $room_name,
                        "maxParticipants" => $room->max_participants
                    ]
                );
        }

        echo "room sid: ".$twilioRoom->sid;


        try {
            $new_key = $client->newKeys
                ->create();
        } catch (TwilioException $e) {
            return null;
        }

        // Create access token, which we will serialize and send to the client
        $token = new AccessToken(
            $sid,
            $new_key->sid,
            $new_key->secret,
            3600,
            $identity
        );

        // Create Video grant
        $videoGrant = new VideoGrant();
        $videoGrant->setRoom($room_name);

        // Add grant to token
        $token->addGrant($videoGrant);

        // render token to string
        return ['room_name' => $room_name, 'identity' => $identity, 'token' => $token->toJWT()];
    }

    private function updateRoom(Request $request) : JsonResponse
    {
        $user_token = ($request->post('user_id') != null) ? $request->post('user_id') : "";
        $room_id = ($request->post('room_id') != null) ? $request->post('room_id') : 0;
        $duration = ($request->post('duration') != null) ? $request->post('duration') : 0;
        $max_participants = ($request->post('participants') != null) ? $request->post('participants') : 0;
        if($room_id > 0 && $user_token != ""){
            $rooms = Room::where([
                ['id', '=', $room_id],
                ['user_token', '=', $user_token]
            ])->get();
            if(count($rooms) > 0){
                $room = $rooms[0];
                $room->max_participants = ($max_participants > 0) ? $max_participants : $room->max_participants;
                $room->max_duration = ($duration > 0) ? $duration : $room->max_duration;
                $room->save();
                return response()->json(array('status_code' => 200, 'status_msg' => 'room updated'));
            }else{
                return response()->json(array('status_code' => 451, 'status_msg' => 'room not found'));
            }
        }
        return response()->json(array('status_code' => 400, 'status_msg' => 'invalid or missing parameters'));
    }

    function terminateRoom(){

    }
}
