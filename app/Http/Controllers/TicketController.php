<?php

namespace App\Http\Controllers;

use App\Notification;
use App\Service;
use App\Ticket;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Auth;
use DateInterval;
use DatePeriod;
use DateTime;
use Carbon\Carbon;
use Exception;

class TicketController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tickets = Ticket::where('user_id', $user->id)->where('name', null)->get();
        foreach ($tickets as $ticket) {
            $ticket["service"] = Service::where('id', $ticket->service_id)->first();
        }
        return $tickets;
    }

    public function ticketsBySerice(Service $service)
    {
        $tickets = Ticket::where('service_id', $service->id)->where('name', "!=", null)->get();
        foreach ($tickets as $ticket) {
            $ticket["service"] = Service::where('id', $ticket->service_id)->first();
        }
        return $tickets;
    }


    public function getAvailableTicketsByDate(string $date, Service $service)
    {
        $availableTickets = array();
        //check day
        $day = Carbon::createFromFormat('Y-m-d', $date)->format('l');
        if (!in_array($day, $service->open_days)) return response()->json($availableTickets, 200);
        //check hoolidays
        if ($service->hoolidays && in_array($date, $service->hoolidays)) return response()->json($availableTickets, 200);
        $begin = new DateTime($service->work_start_time);
        $end   = new DateTime($service->work_end_time);
        $interval = DateInterval::createFromDateString($service->avg_time_per_client . ' min');
        $times    = new DatePeriod($begin, $interval, $end);
        $todayTime = Carbon::now()->addHour()->format('H:i');
        $i = 1;
        foreach ($times as $time) {
            if (!$this->timeInBreak(date_format($time, 'H:i'), $service->break_times)) {
                if ($date == date('Y-m-d') && ($todayTime > $time->format('H:i') || $service->counter > $i))
                    $availableTickets[date_format($time, 'H:i')] = false;
                else {
                    $exist = Ticket::where('service_id', $service->id)
                        ->whereDate('date', $date)->whereTime('time', $time)->first();
                    if (!$exist || $exist->status != "IN_PROGRESS") {
                        $availableTickets[date_format($time, 'H:i')] = true;
                    } else
                        $availableTickets[date_format($time, 'H:i')] = false;
                }
            }
            $i++;
        }

        return response()->json($availableTickets, 200);
    }

    private function timeInBreak($time, $breaks)
    {
        if (!$breaks) return false;
        foreach ($breaks as $break) {
            $startTime = Carbon::createFromFormat('H:i', substr($break, 0, 5));
            $endTime = Carbon::createFromFormat('H:i', substr($break, 9, 5));
            $currentTime = Carbon::createFromFormat('H:i', $time);

            if ($currentTime->between($startTime, $endTime, true)) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function store(Request $request)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'date' => 'required|date_format:Y-m-d',
                'time' => 'required|date_format:H:i',
                'number' => 'required|numeric|min:0',
                'service_id' => 'required|numeric|min:0',
                'notifications' => 'array'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();
        //check duplicate ticket
        $exist = Ticket::where('user_id', $user->id)->where('service_id', $request["service_id"])->where('name', null)->where('status', 'IN_PROGRESS')->first();
        if ($exist) return response()->json(['error' => "ERROR_DUPLICATED_TICKET"], 401);
        //check if ticket already taked
        $taked = Ticket::where('service_id', $request["service_id"])
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);

        $request['status'] = 'IN_PROGRESS';
        $request['duration'] = 0;
        $request['name'] = null;
        $request["user_id"] = $user->id;
        $ticket = Ticket::create($request->all());
        if (!$request["notifications"]) $request["notifications"] = [];
        foreach ($request['notifications'] as $notif) {
            Notification::create([
                'number' =>  $notif,
                'ticket_id' => $ticket->id,
            ]);
        }

        return response()->json($ticket, 201);
    }

    public function addTicketToService(Request $request, Service $service)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'date' => 'required|date_format:Y-m-d',
                'time' => 'required|date_format:H:i',
                'number' => 'required|numeric|min:0',
                'name' => 'required|string'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();
        //check if ticket already taked
        $taked = Ticket::where('service_id', $service->id)
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);

        $request['status'] = 'IN_PROGRESS';
        $request['duration'] = 0;
        $request["user_id"] = $user->id;
        $request["service_id"] = $service->id;

        $ticket = Ticket::create($request->all());
        return response()->json($ticket, 201);
    }

    public function  reschudleTicket(Request $request, Ticket $ticket)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'date' => 'required|date_format:Y-m-d',
                'time' => 'required|date_format:H:i',
                'number' => 'required|numeric|min:0',
                'service_id' => 'required|numeric|min:0',
                'notifications' => 'array'

            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();

        //check if ticket already taked
        $taked = Ticket::where('service_id', $request["service_id"])
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);


        $request['status'] = 'IN_PROGRESS';
        $request["user_id"] = $user->id;
        $newTicket = $ticket->update([
            "number" => $request["number"],
            "date" => $request["date"],
            "time" => $request["time"]
        ]);
        $notifications = $newTicket->notifications;
        foreach ($notifications as $notif) {
            $notif->delete();
        }
        if (!$request["notifications"]) $request["notifications"] = [];
        foreach ($request['notifications'] as $notif) {
            Notification::create([
                'number' => $$notif,
                'ticket_id' => $newTicket->id,
            ]);
        }


        return response()->json($newTicket, 201);
    }

    public function delete(Ticket $ticket)
    {
        $notifications = Notification::where('ticket_id', $ticket->id)->get();
        foreach ($notifications as $notif) {
            $notif->delete();
        }
        $ticket->delete();
        return response()->json(null, 204);
    }

    private function sendNotif($receiverToken, $body,$title)
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


    public function validateTicket(Request $request, int $ticketId, Service $service)
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
        $ticket = Ticket::where('id', $ticketId)->first();
        $date = Carbon::now()->format('Y-m-d');
        if ($ticket) {
            //validate ticket
            $ticket->update([
                'duration' => $request['duration'],
                'status' => $request['ticket_status']
            ]);
        }
        if (!($ticket && $ticket->number > $service->counter)) $service->update(["counter" => $service->counter + 1]);
        $service["tickets"] = $service->tickets;
        foreach ($service["tickets"] as $tick) {
            $tick["user"] = User::where("id", $tick["user_id"])->first();
        }

        //send notification to next user
        $nextUser = Ticket::where('date', $date)->where('service_id', $service->id)->where('status', 'IN_PROGRESS')->where('number', $service->counter)->first();
        if ($nextUser) {
            $receiv = User::where('id', $nextUser->user_id)->first();
            $this->sendNotif($receiv->messaging_token, "C'est votre tour !","E-SAFF : ".$service->name);
        }
        $nextTickets = Ticket::where('date', $date)->where('service_id', $service->id)->where('status', 'IN_PROGRESS')->get();
        //send planified notifications
        for ($i = 0; $i < count($nextTickets); $i++) {
            foreach ($nextTickets[$i]->notifications as $notif) {
                if ($nextTickets[$i]->number - $notif->number == $service->counter) {
                    $text = "Il reste " . $i . " tickets avant votre rendez-vous. Soyez prêt !";
                    $user=$nextTickets[$i]->user;
                    $this->sendNotif($user->messaging_token, $text,"E-SAFF : ".$service->name);
                }
            }
        }

        return response()->json($service, 200);
    }
}
