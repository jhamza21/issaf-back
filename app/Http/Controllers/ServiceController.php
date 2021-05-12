<?php

namespace App\Http\Controllers;

use App\Notification;
use App\Service;
use App\Request as Req;
use App\Ticket;
use App\User;
use Carbon\Carbon;
use Exception;
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
    public function getServiceTickets(String $id)
    {
        $service = Service::where('id', $id)->first();
        if ($service) {

            return response()->json($service->tickets, 200);
        } else
            return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function getServiceById(String $id)
    {

        $service = Service::where('id', $id)->first();
        if ($service) {
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

    public function getServiceByRespo()
    {
        $user = Auth::user();
        $service = $user->service;
        if ($service) {
            return response()->json($service, 200);
        } else
            return response()->json("NOT_AFFECTED_TO_SERVICE", 404);
    }

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

        $service = Service::create($request->all());
        //SEND REQUEST TO USERS
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
            }
        }
        return response()->json($service, 201);
    }

    public function downloadImage(String $imgName)
    {
        return response()->download(storage_path() . "/" . "app/servicesImg/" . $imgName);
    }

    private function contains($array1, $id)
    {
        foreach ($array1 as $el)
            if ((string) $el->id == $id) return true;
        return false;
    }

    private function diff($array1, $arrayOfIds)
    {
        $res = [];
        foreach ($arrayOfIds as $id) {
            if (!$this->contains($array1, $id)) $res[] = $id;
        }
        return $res;
    }

    private function undiff($array1, $arrayOfIds)
    {
        $res = [];
        foreach ($array1 as $user) {
            if (!in_array((string) $user->id, $arrayOfIds)) $res[] = $user->id;
        }
        return $res;
    }

    public function update(Request $request, Service $service)
    {
        $days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

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
        }
        //DELETE REQUESTS AND SERVICE FROM REMOVED OPERATORS
        foreach ($undiff as $userId) {
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

    private function sendNotif($receiversToken, $body)
    {
        try {
            $SERVER_API_KEY = "AAAAUXABvuk:APA91bGzKA4BLwPlLjbnWLKO13IcLQ5Qeem1ZYc2OUAlCD45HUhQpyv_lDX_zgc4-RklQtWAbKf8ltUOJ31Foon7bDYXc9UnF-4zOh52dU0U71QthCN8jEa0rlNA3CvoRVafeeK5_XQ3";
            $data = [
                "registration_ids" => $receiversToken,
                "notification" => [
                    "title" => "E-SAFF",
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


    public function incrementCounter(Request $request, Service $service)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'ticket_status' => ['required', 'string'],
                'duration' => ['required', 'numeric', 'min:0']
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
                'duration' => $request['duration'],
                'status' => $request['ticket_status']
            ]);
        }
        //send notif to next user
        $nextTicket = Ticket::where('service_id', $service->id)->where('status', 'IN_PROGRESS')->where('number', $service->counter + 1)->where('date', $date)->first();
        if ($nextTicket) {
            $receiver = User::where('id', $nextTicket->user_id)->first();
            $this->sendNotif([$receiver->messaging_token], "C'est votre tour ! Avancez");
        }

        //send notif to planified notifications
        $notifs = Notification::select("messaging_token")->where('service_id', $service->id)->where('date', $date)->where('number', $service->counter + 1)->get();
        if (count($notifs) > 0) {
            $text = "On serre maintenant le client num ";
            $text += $service->counter + 1;
            $text += " ! Soyez prết !";
            $this->sendNotif($notifs, $text);
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
