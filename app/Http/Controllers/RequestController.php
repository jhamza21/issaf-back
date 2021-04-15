<?php

namespace App\Http\Controllers;

use App\Request as UserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    public function index()
    {
        return UserRequest::all();
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


    public function store(Request $request)
    {
        $status = array("ACCEPTED", "REFUSED");

        $validator = Validator::make(
            $request->all(),
            [
                'sender_id' => ['required', 'numeric', 'min:0'],
                'receiver_id' => ['required', 'numeric', 'min:0'],
                'service_id' => ['required', 'numeric', 'min:0'],
                'date_time' => 'required|date_format:Y-m-d H:i:s|after:yesterday',
                'status' => 'required|in:' . implode(',', $status)
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $userRequest = UserRequest::create($request->all());


        return response()->json($userRequest, 201);
    }


    public function update(Req $req, UserRequest $request)
    {
        $status = array("ACCEPTED", "REFUSED");

        $validator = Validator::make(
            $req->all(),
            [
                'sender_id' => ['numeric', 'min:0'],
                'receiver_id' => ['numeric', 'min:0'],
                'service_id' => ['numeric', 'min:0'],
                'date_time' => 'date_format:Y-m-d H:i:s|after:yesterday',
                'status' => 'in:' . implode(',', $status)
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $request->update($req->all());

        return response()->json($request, 200);
    }

    public function delete(UserRequest $request)
    {
        $request->delete();
        return response()->json(null, 204);
    }
}
