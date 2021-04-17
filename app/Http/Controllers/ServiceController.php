<?php

namespace App\Http\Controllers;

use App\Service;
use App\Request as Req;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{

    public function index()
    {
        return Service::all();
    }

    public function getServiceById(String $id)
    {
        $service = Service::where('id', $id)->first();
        if ($service)
            return response()->json($service, 200);
        else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function getServiceByAdmin()
    {
        $user = Auth::user();
        $service = Service::where('admin_id', $user->id)->first();
        if ($service)
            return response()->json($service, 200);
        else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function store(Request $request)
    {
        $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
        $status = array("OPENED", "CLOSED");

        $validator = Validator::make(
            $request->all(),
            [
                'title' => ['required', 'string', 'max:255', 'min:2'],
                'description' => ['string', 'min:4', 'max:255'],
                'avg_time_per_client' => ['required', 'numeric', 'max:300', 'min:1'],
                'counter' => ['required', 'numeric', 'max:10000', 'min:0'],
                'work_start_time' => ['required', 'date_format:H:i'],
                'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
                'open_days' => "required|array|min:1",
                'open_days.*' => 'required|string|distinct|in:' . implode(',', $days),
                'status' => 'required|in:' . implode(',', $status),
                'provider_id' => 'required|numeric|min:0',
                'username_admin' => ['required', 'string', 'max:255', 'min:6'],
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        //VALIDATE USERNAME
        $userAdmin = User::where('username', $request["username_admin"])->first();
        if (!$userAdmin) return response()->json(['error' => "USER_NOT_FOUND"], 401);
        if ($userAdmin && $userAdmin->role != "ADMIN_SAFF") return response()->json(['error' => "USER_INVALID_ROLE"], 401);
        $serv = Service::where('admin_id', $userAdmin->id)->first();
        if ($serv) return response()->json(['error' => "USER_ALREADY_AFFECTED_TO_SERVICE"], 401);

        if ($request["img"] != null) {
            $res = $request->file("img")->store("servicesImg");
            $request["image"] = substr($res, strpos($res, "/") + 1);
        }
        $service = Service::create($request->all());
        //SEND REQUEST TO USER
        $dateTime = Carbon::now();
        $user = Auth::user();
        Req::create(
            [
                'status' => null,
                'date_time' => $dateTime->toDateTimeString(),
                'sender_id' => $user->id,
                'receiver_id' =>  $userAdmin->id,
                'service_id' => $service->id,

            ]
        );

        return response()->json($service, 201);
    }

    public function downloadImage(String $imgName)
    {
        return response()->download(storage_path() . "/" . "app/servicesImg/" . $imgName);
    }

    public function update(Request $request, Service $service)
    {
        $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
        $status = array("OPENED", "CLOSED");

        $validator = Validator::make(
            $request->all(),
            [
                'title' => ['string', 'max:255', 'min:2'],
                'description' => ['string', 'min:4', 'max:255'],
                'avg_time_per_client' => ['numeric', 'max:300', 'min:1'],
                'counter' => ['numeric', 'max:10000', 'min:0'],
                'work_start_time' => ['date_format:H:i'],
                'work_end_time' => ['date_format:H:i', 'after:work_start_time'],
                'open_days' => "array|min:1",
                'open_days.*' => 'string|distinct|in:' . implode(',', $days),
                'status' => 'in:' . implode(',', $status),
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        //update username
        if ($request["username_admin"] != null) {
            $userAdmin = User::where('username', $request["username_admin"])->first();
            if (!$userAdmin) return response()->json(['error' => "USER_NOT_FOUND"], 401);
            if ($userAdmin && $userAdmin->role != "ADMIN_SAFF") return response()->json(['error' => "USER_INVALID_ROLE"], 401);
            $serv = Service::where('admin_id', $userAdmin->id)->first();
            if ($serv) return response()->json(['error' => "USER_ALREADY_AFFECTED_TO_SERVICE"], 401);

            //SEND REQUEST TO USER
            $dateTime = Carbon::now();
            $user = Auth::user();
            $req = Req::where('service_id', $request->id)->first();
            if ($req) {
                $req->update([
                    [
                        'status' => null,
                        'date_time' => $dateTime->toDateTimeString(),
                        'sender_id' => $user->id,
                        'receiver_id' =>  $userAdmin->id,
                        'service_id' => $service->id,

                    ]
                ]);
            } else {
                Req::create(
                    [
                        'status' => null,
                        'date_time' => $dateTime->toDateTimeString(),
                        'sender_id' => $user->id,
                        'receiver_id' =>  $userAdmin->id,
                        'service_id' => $service->id,

                    ]
                );
            }
        }
        //update image
        if ($request["img"] != null) {
            $res = $request->file("img")->store("servicesImg");
            $request["image"] = substr($res, strpos($res, "/") + 1);
        }

        $service->update($request->all());

        return response()->json($service, 200);
    }

    public function delete(Service $service)
    {
        $req = Req::where("service_id", $service->id)->first();
        if ($req)
            $req->delete();
        $service->delete();
        return response()->json(null, 204);
    }
}
