<?php

namespace App\Http\Controllers;

use App\Request as UserRequest;
use App\Service;
use App\User;
use Exception;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
{
    //return sended request of connected user
    public function getSendedRequests()
    {
        $user = Auth::user();
        $result = UserRequest::where('sender_id', $user->id)->get();
        foreach ($result as $req) {
            $req["service"] = Service::where('id', $req->service_id)->first();
            $req["sender"] = $user;
            $req["receiver"] = User::where('id', $req->receiver_id)->first();
        }
        return response()->json($result, 200);
    }

    //return received requests of connected user
    public function getReceivedRequests()
    {
        $user = Auth::user();
        $result = UserRequest::where('receiver_id', $user->id)->get();
        foreach ($result as $req) {
            $req["service"] = Service::where('id', $req->service_id)->first();
            $req["receiver"] = $user;
            $req["sender"] = User::where('id', $req->sender_id)->first();
        }
        return response()->json($result, 200);
    }

     //send notification using firebase free service
    //notification is sent based on token of user's device
    private function sendNotif($receiverToken, $body, $title)
    {
        try {
            $SERVER_API_KEY = "AAAAUXABvuk:APA91bGzKA4BLwPlLjbnWLKO13IcLQ5Qeem1ZYc2OUAlCD45HUhQpyv_lDX_zgc4-RklQtWAbKf8ltUOJ31Foon7bDYXc9UnF-4zOh52dU0U71QthCN8jEa0rlNA3CvoRVafeeK5_XQ3";
            $data = [
                "registration_ids" => [$receiverToken],
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                    "sound" => "default"
                ]
            ];
            $dataString = json_encode($data);
            $headers = [
                "Authorization: key=" . $SERVER_API_KEY,
                "Content-Type: application/json"
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/fcm/send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
            curl_exec($ch);
        } catch (Exception $e) { }
    }

    //refuse a request
    public function refuseRequest(UserRequest $request)
    {
        //update request status to refused
        $request->update([
            "status" => "REFUSED",
        ]);
        $receiver = User::where("id", $request->receiver_id)->first();
        $sender = User::where("id", $request->sender_id)->first();
        $service = Service::where("id",$request->service_id)->first();
        $this->sendNotif($sender->messaging_token,$receiver->name." a refusé votre invitation pour gérer: ".$service->title,"E-SAFF");
        return response()->json(null, 200);
    }

    //accept a request
    public function acceptRequest(UserRequest $request)
    {
        $user =$request->receiver;
        //check if user already is handling another service
        if ($user->service_id) return response()->json(['error' => "USER_ALREADY_AFFECTED_TO_SERVICE"], 401);

        //affect service to user
        $user->update([
            "service_id" => $request["service_id"],
        ]);
        //update request status to accepted
        $request->update([
            "status" => "ACCEPTED",
        ]);
        $receiver = User::where("id", $request->receiver_id)->first();
        $sender = User::where("id", $request->sender_id)->first();
        $service = Service::where("id",$request->service_id)->first();
        $this->sendNotif($sender->messaging_token,$receiver->name." a accepté votre invitation pour gérer: ".$service->title,"E-SAFF");
        return response()->json(null, 200);
    }

    //delete a request
    public function delete(UserRequest $request)
    {
        $request->delete();
        return response()->json(null, 204);
    }
}
