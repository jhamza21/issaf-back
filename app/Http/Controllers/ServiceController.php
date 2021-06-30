<?php

namespace App\Http\Controllers;

use App\Service;
use App\Request as Req;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Exception;


class ServiceController extends Controller
{
    //return service handled by connected operator
    public function getServiceByRespo()
    {
        $user = Auth::user();
        $service = $user->service;
        if ($service) {
            $service["tickets"] = $service->tickets;
            foreach ($service["tickets"] as $tick) {
                $tick["user"] = $tick->user;
            }
            return response()->json($service, 200);
        } else
            return response()->json("NOT_AFFECTED_TO_SERVICE", 404);
    }

    //return admin services
    public function getServicesByAdmin()
    {
        $user = Auth::user();
        $provider = $user->provider;
        if ($provider) {
            $services = $provider->services;
            return response()->json($services, 200);
        } else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    //return service by id
    //return service tickets
    //return users that handle this service
    //affect to each user status(has accepted/ refuse to handle service)
    public function getServiceById(String $id)
    {
        $service = Service::where('id', $id)->first();
        if ($service) {
            $service["tickets"] = $service->tickets;
            foreach ($service["tickets"] as $tick) {
                $tick["user"] = $tick->user;
            }
            //users that accepted to be operator
            $service["users"] = $service->users;
            foreach ($service["users"] as $user) {
                $user["status"] = "ACCEPTED";
            }
            //users from request
            $requests = Req::where('service_id', $id)->get();
            foreach ($requests as $req) {
                if ($req->status == "ACCEPTED") continue;
                if ($req->status == "REFUSED") {
                    $req->receiver["status"] = "REFUSED";
                    $service["users"][] = $req->receiver;
                } else
                    $service["users"][] = $req->receiver;
            }
            return response()->json($service, 200);
        } else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
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


    //store new service
    public function store(Request $request)
    {
        $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

        $validator = Validator::make(
            $request->all(),
            [
                'title' => ['required', 'string', 'max:255', 'min:2'],
                'description' => ['string', 'min:4', 'max:255'],
                'avg_time_per_client' => ['required', 'numeric', 'max:300', 'min:1'],
                'work_start_time' => ['required', 'date_format:H:i'],
                'work_end_time' => ['required', 'date_format:H:i', 'after:work_start_time'],
                'open_days' => "required|array|min:1",
                'open_days.*' => 'required|string|distinct|in:' . implode(',', $days),
                'users' => "array",
                'hoolidays' => "array",
                'break_times' => "array",
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }

        if ($request["img"] != null) {
            $res = $request->file("img")->store("servicesImg");
            $request["image"] = substr($res, strpos($res, "/") + 1);
        }
        $user = Auth::user();
        $request["provider_id"] = $user->provider->id;
        $request["status"] = null;
        $request["counter"] = "1";

        $service = Service::create($request->all());
        //SEND REQUEST TO OPERATORS
        if ($request["users"]) {
            $dateTime = Carbon::now();
            foreach ($request["users"] as $userId) {
                Req::create(
                    [
                        'status' => null,
                        'date_time' => $dateTime->toDateTimeString(),
                        'sender_id' => $user->id,
                        'receiver_id' =>  $userId,
                        'service_id' => $service->id,

                    ]
                );

                //send notif to user
                $receiver = User::where("id", $userId)->first();
                $this->sendNotif($receiver->messaging_token, $user->name . "(" . $user->username . ") vous a invité pour gérer la file d'attente du service: " . $service->title, "E-SAFF");
            }
        }
        return response()->json($service, 201);
    }



    //$array1 is an array of user object
    //check if $array1 contains $id
    private function contains($array1, $id)
    {
        foreach ($array1 as $el)
            if ((string) $el->id == $id) return true;
        return false;
    }

    //$array1 is an array of user object
    //$arrayOfIds is an array of users ids
    //return ids from $arrayOfIds that not exist in $array1
    private function diff($array1, $arrayOfIds)
    {
        $res = [];
        foreach ($arrayOfIds as $id) {
            if (!$this->contains($array1, $id)) $res[] = $id;
        }
        return $res;
    }
    //$array1 is an array of user object
    //$arrayOfIds is an array of users ids
    //return user ids of $array1 that not exist in $arrayOfIds
    private function undiff($array1, $arrayOfIds)
    {
        $res = [];
        foreach ($array1 as $user) {
            if (!in_array((string) $user->id, $arrayOfIds)) $res[] = $user->id;
        }
        return $res;
    }

    //update a service
    public function update(Request $request, Service $service)
    {
        $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

        $validator = Validator::make(
            $request->all(),
            [
                'title' => ['string', 'max:255', 'min:2'],
                'description' => ['string', 'min:4', 'max:255'],
                'avg_time_per_client' => ['numeric', 'max:300', 'min:1'],
                'work_start_time' => ['date_format:H:i'],
                'work_end_time' => ['date_format:H:i', 'after:work_start_time'],
                'open_days' => "array|min:1",
                'open_days.*' => 'string|distinct|in:' . implode(',', $days),
                'hoolidays' => "array",
                'break_times' => "array",
                'users' => 'array',
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        if (!$request["users"]) $request['users'] = [];
        //users from request
        $oldUsers = $service->users;
        $requests = Req::where('service_id', $service->id)->get();
        foreach ($requests as $req) {
            if ($req->status == "ACCEPTED") continue;
            $oldUsers[] = $req->receiver;
        }
        //new operators
        $diff = $this->diff($oldUsers, $request["users"]);
        //removed operators
        $undiff = $this->undiff($oldUsers, $request["users"]);

        $dateTime = Carbon::now();
        $connectedUser = Auth::user();
        //SEND REQUEST TO NEW OPERATORS
        foreach ($diff as $userId) {
            Req::create(
                [
                    'status' => null,
                    'date_time' => $dateTime->toDateTimeString(),
                    'sender_id' => $connectedUser->id,
                    'receiver_id' =>  $userId,
                    'service_id' => $service->id,

                ]
            );
            $receiver = User::where("id", $userId)->first();
                $this->sendNotif($receiver->messaging_token, $connectedUser->name . "(" . $connectedUser->username . ") vous a invité pour gérer la file d'attente du service: " . $service->title, "E-SAFF");
        }
        //DELETE REQUESTS AND SERVICE FROM REMOVED OPERATORS
        foreach ($undiff as $userId) {
            //delete if there is a request sent to user
            $req = Req::where('service_id', $service->id)->where('receiver_id', $userId)->first();
            if ($req) $req->delete();
            $user = User::where('id', $userId)->first();
            if ($user->service_id != null)
                $user->update([
                    "service_id" => null
                ]);
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

    //update service counter
    public function updateCounter(Request $request, Service $service)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'counter' => ['numeric', 'min:0'],
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $service->update($request->all());
        return response()->json($service, 200);
    }

    //delete service
    public function delete(Service $service)
    {
        //delete related request to service
        foreach ($service->requests as $req) {
            $req->delete();
        }
        //delete related tickets to service
        foreach ($service->tickets as $tic) {
            //delete related notifications
            foreach ($tic->notifications as $notif) {
                $notif->delete();
            }
            $tic->delete();
        }
        //delete related image to service 
        $path = storage_path() . "/" . "app/servicesImg/" . $service->image;
        if (File::exists($path)) {
            File::delete($path);
        }
        $service->delete();
        return response()->json(null, 204);
    }

    //return image of service by name
    public function downloadImage(String $imgName)
    {
        return response()->download(storage_path() . "/" . "app/servicesImg/" . $imgName);
    }
}
