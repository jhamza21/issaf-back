<?php

namespace App\Http\Controllers;

use App\Request as UserRequest;
use App\Service;
use App\User;
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

    //refuse a request
    public function refuseRequest(UserRequest $request)
    {
        //update request status to refused
        $request->update([
            "status" => "REFUSED",
        ]);
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
        return response()->json(null, 200);
    }

    //delete a request
    public function delete(UserRequest $request)
    {
        $request->delete();
        return response()->json(null, 204);
    }
}
