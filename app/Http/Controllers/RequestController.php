<?php

namespace App\Http\Controllers;

use App\Request as UserRequest;
use App\Service;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
{

    public function index()
    {
        return UserRequest::all();
    }

    public function getRequestByService($service)
    {
        $result = UserRequest::where('service_id', $service)->first();
        if($result)
        return response()->json($result, 200);
        else
        return response()->json("RESSOURCE_NOT_FOUND", 404);

    }

    public function getSendedRequests()
    {
        $user = Auth::user();
        $result = UserRequest::where('sender_id', $user->id)->get();
        return response()->json($result, 200);
    }

    public function getReceivedRequests()
    {
        $user = Auth::user();
        $result = UserRequest::where('receiver_id', $user->id)->get();
        return response()->json($result, 200);
    }

    public function refuseRequest(UserRequest $request)
    {
        $request->update([
            "status" => "REFUSED",
            "receiver_id" => $request->sender_id,
            "sender_id" => $request->receiver_id
        ]);
        return response()->json(null, 200);
    }

    public function acceptRequest(UserRequest $request)
    {
        $service = Service::where('id', $request->service_id)->where('admin_id', $request->receiver_id)->first();
        if ($service) return response()->json(['error' => "USER_ALREADY_AFFECTED_TO_SERVICE"], 401);
        //update service admin
        $service = Service::where('id', $request->service_id)->first();
        if ($service) {
            $service->update([
                "admin_id" => $request->receiver_id
            ]);
        }
        $request->update([
            "status" => "ACCEPTED",
            "receiver_id" => $request->sender_id,
            "sender_id" => $request->receiver_id
        ]);
        return response()->json(null, 200);
    }

    public function delete(UserRequest $request)
    {
        $request->delete();
        return response()->json(null, 204);
    }
}
