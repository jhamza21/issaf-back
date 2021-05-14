<?php

namespace App\Http\Controllers;

use App\Request as UserRequest;
use App\Service;
use App\User;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
{

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

    public function refuseRequest(UserRequest $request)
    {
        $request->update([
            "status" => "REFUSED",
        ]);
        return response()->json(null, 200);
    }

    public function acceptRequest(UserRequest $request)
    {
        $user = User::where('id', $request->receiver_id)->first();
        if ($user->service_id) return response()->json(['error' => "USER_ALREADY_AFFECTED_TO_SERVICE"], 401);

        //update service admin
        $user->update([
            "service_id" => $request["service_id"],
        ]);
        $request->update([
            "status" => "ACCEPTED",
        ]);
        return response()->json(null, 200);
    }

    public function delete(UserRequest $request)
    {
        if ($request->status == "ACCEPTED" || $request->status == "REFUSED")
            $request->delete();
        else {
            $service = Service::where('id', $request->service_id)->first();
            $service->update([
                "user_id" => null
            ]);
            $request->delete();
        }
        return response()->json(null, 204);
    }
}
