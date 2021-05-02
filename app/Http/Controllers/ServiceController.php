<?php

namespace App\Http\Controllers;

use App\Service;
use App\Request as Req;
use App\Ticket;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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
        if ($service) {
            $service["user"] = $service->user;
            return response()->json($service, 200);
        } else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function getServiceByAdmin()
    {
        $user = Auth::user();
        $service = Service::where('user_id', $user->id)->first();
        if ($service && $service->status == "ACCEPTED")
            return response()->json($service, 200);
        else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function store(Request $request)
    {
        $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

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
                'hoolidays' => "array",
                'break_times' => "array",
                'user_id' => 'required|numeric|min:0',
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        //VALIDATE USER
        $userAdmin = User::where('id', $request["user_id"])->first();
        if (!$userAdmin) return response()->json(['error' => "USER_NOT_FOUND"], 401);

        if ($request["img"] != null) {
            $res = $request->file("img")->store("servicesImg");
            $request["image"] = substr($res, strpos($res, "/") + 1);
        }
        $user = Auth::user();
        $request["provider_id"] = $user->provider->id;
        $request["status"] = null;

        $service = Service::create($request->all());
        //SEND REQUEST TO USER
        $dateTime = Carbon::now();
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
        $status = array("ACCEPTED", "REFUSED");

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
                'hoolidays' => "array",
                'break_times' => "array",
                'status' => 'in:' . implode(',', $status),
                'user_id' => 'numeric|min:0',
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        //update username
        if ($request["user_id"] != $service->user_id) {
            $userAdmin = User::where('id', $request["user_id"])->first();
            if (!$userAdmin) return response()->json(['error' => "USER_NOT_FOUND"], 401);
            //set service status to null
            $request["status"] = null;

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
        if (!$request["hoolidays"]) $request["hoolidays"] = null;
        if (!$request["break_times"]) $request["break_times"] = null;

        $service->update($request->all());

        return response()->json($service, 200);
    }
    public function resetCounter(Request $request, Service $service)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'counter' => ['numeric', 'max:10000', 'min:0'],
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }


        $service->update($request->all());

        return response()->json($service, 200);
    }

    public function incrementCounter(Request $request, Service $service)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'ticket_status' => ['required', 'string'],
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        //validate ticket
        $date = Carbon::now()->format('Y-m-d');
        $ticket = Ticket::where('service_id', $service->id)->where('status', 'IN_PROGRESS')->where('number', $service->counter)->where('date', $date)->first();
        if ($ticket) {
            $ticket->update([
                'status' => $request['ticket_status'] == 'DONE' ? 'DONE' : 'UNDONE'
            ]);
        }
        //increment counter
        $service->counter++;
        $service->update($request->all());

        return response()->json($service, 200);
    }

    public function delete(Service $service)
    {
        //delete related request to service
        $req = Req::where("service_id", $service->id)->first();
        if ($req)
            $req->delete();
        //delete related image to service 
        $path = storage_path() . "/" . "app/servicesImg/" . $service->image;
        if (File::exists($path)) {
            File::delete($path);
        }
        $service->delete();
        return response()->json(null, 204);
    }
}
