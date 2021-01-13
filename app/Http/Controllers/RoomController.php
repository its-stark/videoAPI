<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Models\UserRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass as stdClassAlias;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use Twilio\Rest\Client;

class RoomController extends Controller
{
    /**
     * list all rooms or if email given rooms for user with email
     * @param Request $request
     * @param string $booking_token
     * @param string $user_token
     * @return JsonResponse
     */
    public function listRooms(Request $request, $booking_token = "0", $user_token = ""): JsonResponse
    {
        $rooms = $this->getRooms($booking_token, $user_token);
        return response()->json(array('status' => 200, 'status_msg' => 'ok', 'rooms' => $rooms));
    }

    /**
     * @param string $booking_token
     * @param string $user_token
     * @return object
     */
    private function getRooms($booking_token = "0", $user_token = "") : object{
        if(($user_token != "") || ($booking_token != "0")){
            $where = array();
            if($user_token != ""){
                array_push($where, ['user_token', '=', $user_token]);
            }
            if($booking_token != "0"){
                array_push($where, ['booking_token', '=', $booking_token]);
            }
            $rooms = (Room::class)::where($where)->get();
        }else{
            $rooms = (Room::class)::get();
        }
        return $rooms;
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
        $max_participants = ($request->post('participants') != null) ? $request->post('participants') : 10;
        $user_token = ($request->post('user_token') != null && $request->post('user_token') != "") ? $request->post('user_token') : "";
        $booking_token = ($request->post('booking_token') != null && $request->post('booking_token') != "") ? $request->post('booking_token') : "";

        if($start_date != null && $booking_token != "") {

            $existed = false;

            $rooms = $this->getRooms($booking_token);

            if(count($rooms) > 0){
                $room = $rooms[0];
                $existed = true;
            }else{
                ///create room
                $room = new Room();
                $room->start_date = $start_date;
                $room->max_duration = $duration;
                $room->max_participants = $max_participants;
                $room->booking_token = $booking_token;
                $room->user_token = $user_token;
                $room->save();
            }

            if($user_token != "") {
                $users = User::where('user_token', '=', $user_token)->get();
                if (count($users) == 0) {
                    //create new user
                    $user = new User();
                    $user->user_token = "$user_token";
                    $user->save();
                    var_dump($user->id);
                    $user_id = $user->id;

                } else {
                    $user_id = $users[0]->id;
                }
                //link user to room
                $ur = new UserRoom();
                $ur->user_id = $user_id;
                $ur->room_id = $room->id;
                $ur->save();
            }

            $link = env('TWILIO_ROOM_URL').'?room='.env('TWILIO_ROOM_PREFIX').$room->id."&passcode=".env('TWILIO_PASSCODE');

            return response()->json(array('status_code' => 200, 'status_msg' => 'room booked', 'room_id' => $room->id, 'link' => $link, 'existed' => $existed));
        }else {
            return response()->json(array('status_code' => 401, 'status_msg' => 'could not create booking'));
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    function createRoom(Request $request): JsonResponse
    {
        $user_token = ($request->post('user_token') != null) ? $request->post('user_token') : "";
        $booking_token = ($request->post('booking_token') != null && $request->post('booking_token') != "") ? $request->post('booking_token') : "";
        $room_id = ($request->post('room_id') != null) ? $request->post('room_id') : 0;

        if($room_id > 0 && $booking_token != ""){
            $this->updateRoom($request);
            //create twilio room
            $rooms = Room::where([
                ['id', '=', $room_id],
                ['booking_token', '=', $booking_token]
            ])->get();
            if(count($rooms) > 0) {
                $room = $rooms[0];

                //ToDo: check if start_date is in range

                try {
                    $twilioRoom = $this->createTwilioRoom($room);
                } catch (ConfigurationException | TwilioException $e) {
                    echo $e->getMessage();
                    return response()->json(array('status_code' => 460, 'status_msg' => 'exception'));
                }

                //var_dump($twilioRoom);

                $link = env('TWILIO_ROOM_URL').'?token='.$twilioRoom['token']."&room=".$twilioRoom['room_name']."&passcode=".env('TWILIO_PASSCODE');

                $this->updateRoomDB((object)array('status' => 'created'));

                return response()->json(array('status_code' => 200, 'status_msg' => 'room created', 'link' => $link));
            }else{
                return response()->json(array('status_code' => 451, 'status_msg' => 'room not found'));
            }
        }else{
            return $this->createErrorResponse(400, 'invalid or missing parameters');
        }


    }

    /**
     * @param Room $room
     * @return array|null
     * @throws TwilioException
     * @throws \Twilio\Exceptions\ConfigurationException
     */
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

        //echo "room sid: ".$twilioRoom->sid;
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
        return ['room_name' => $room_name, 'identity' => $identity, 'sid' => $twilioRoom->sid, 'token' => $token->toJWT()];
    }

    /**
     * update room parameters
     * @param Request $request
     * @return JsonResponse
     */
    public function updateRoom(Request $request) : JsonResponse
    {
        $params = new stdClassAlias;
        $params->user_token = ($request->post('user_token') != null) ? $request->post('user_token') : "";
        $params->booking_token = ($request->post('booking_token') != null && $request->post('booking_token') != "") ? $request->post('booking_token') : "";
        $params->room_id = ($request->post('room_id') != null) ? $request->post('room_id') : 0;
        $params->duration = ($request->post('duration') != null) ? $request->post('duration') : 0;
        $params->max_participants = ($request->post('max_participants') != null) ? $request->post('max_participants') : 0;
        $params->status = ($request->post('status') != null) ? $request->post('status') : "";

        if($params->room_id > 0 && $params->booking_token != ""){
            $res = $this->updateRoomDB($params);
            return response()->json($res);
        }
        return $this->createErrorResponse(400,'invalid or missing parameters');
    }

    /**
     * @param $params
     * @return array
     */
    private function updateRoomDB($params) : array{

        $where = array();
        if(isset($params->room_id) && $params->room_id > 0){
            array_push($where, ['id', '=', $params->room_id]);
        }
        if(isset($params->booking_token) && $params->booking_token != ""){
            array_push($where, ['booking_token', '=', $params->booking_token]);
        }
        $rooms = (Room::class)::where($where)->get();
        if(count($rooms) > 0){
            $room = $rooms[0];
            $room->max_participants = (isset($params->max_participants) && $params->max_participants > 0) ? $params->max_participants : $room->max_participants;
            $room->max_duration = (isset($params->duration) && $params->duration > 0) ? $params->duration : $room->max_duration;
            $room->status = (isset($params->status) && $params->status != "") ? $params->status : $room->status;
            $room->save();
            return array('status_code' => 200, 'status_msg' => 'room updated');
        }else{
            return array('status_code' => 451, 'status_msg' => 'room not found');
        }
    }

    function terminateRoom(){

    }

    /**
     * @param int $code
     * @param string $msg
     * @return JsonResponse
     */
    private function createErrorResponse($code = 400, $msg = 'error'): JsonResponse
    {
        return response()->json(array('status_code' => $code, 'status_msg' => $msg));
    }
}
