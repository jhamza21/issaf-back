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
    //return tickets related to connected user
    public function index()
    {
        $user = Auth::user();
        $tickets = Ticket::where('user_id', $user->id)->where('name', null)->get();
        foreach ($tickets as $ticket) {
            $ticket["notifications"] = $ticket->notifications;
            $ticket["service"] = Service::where('id', $ticket->service_id)->first();
        }
        return response()->json($tickets, 200);
    }

    //return service tickets reserved by operator (where name!=null)
    public function ticketsByOperator(Service $service)
    {
        $user = Auth::user();
        $tickets = Ticket::where('service_id', $service->id)->where('user_id',$user->id)->where('name', "!=", null)->get();
        foreach ($tickets as $ticket) {
            $ticket["notifications"] = $ticket->notifications;
            $ticket["service"] = $ticket->service;
        }
        return response()->json($tickets, 200);
    }
    
    //return all tickets related to service
    public function getServiceTickets(Service $service)
    {
            return response()->json($service->tickets, 200);
    }

    //return availabale times(tickets) in service acoording to given date
    public function getAvailableTicketsByDate(string $date, Service $service)
    {
        $availableTickets = array();
        //check if given date(day) is in opend days of service 
        //if given date(day) is not in open days of service return empty array
        $day = Carbon::createFromFormat('Y-m-d', $date)->format('l');
        if (!in_array($day, $service->open_days)) return response()->json($availableTickets, 200);
        //check if given date(day) is not in hoolidays of service
        //if given date(day) is not in hoolidaysdays of service return empty array
        if ($service->hoolidays && in_array($date, $service->hoolidays)) return response()->json($availableTickets, 200);
        //generate a list of times according to work time of service
        $begin = new DateTime($service->work_start_time);
        $end   = new DateTime($service->work_end_time);
        $interval = DateInterval::createFromDateString($service->avg_time_per_client . ' min');
        $times    = new DatePeriod($begin, $interval, $end);
        $todayTime = Carbon::now()->addHour()->format('H:i');
        //filter times
        //if time is in break times of service => remove it from returned array of available times
        //if time has a ticket related to it where ticket status is IN_PROGRESS => mark it as unavailable(false)
        //if date of the given day is today date so check if time is greater than now else mark it as unavailable
        //if time(ticket number) is grater than service counter then mark it as unavailable
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

    //check if time is not in breaks time
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

    //add ticket
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

        //check request validation
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();
        //check duplicate ticket in service only if user is client 
        //operator can book sevral tickets in one service for several clients
        if ($request["name"] != null) {
            $exist = Ticket::where('user_id', $user->id)->where('service_id', $request["service_id"])->where('name', null)->where('status', 'IN_PROGRESS')->first();
            if ($exist) return response()->json(['error' => "ERROR_DUPLICATED_TICKET"], 401);
        }
        //check if ticket already taked
        $taked = Ticket::where('service_id', $request["service_id"])
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);

        $request['status'] = 'IN_PROGRESS';
        $request['duration'] = 0;
        $request["user_id"] = $user->id;
        $ticket = Ticket::create($request->all());
        //store notifications related to ticket
        if (!$request["notifications"]) $request["notifications"] = [];
        foreach ($request['notifications'] as $notif) {
            Notification::create([
                'number' =>  $notif,
                'ticket_id' => $ticket->id,
            ]);
        }

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

        //check request validation
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();

        //check if ticket already taked
        if ($request["time"]->format('H:i') != $ticket->time->format('H:i')) {
            $taked = Ticket::where('service_id', $request["service_id"])
                ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
            if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);
        }

        $request['status'] = 'IN_PROGRESS';
        $request["user_id"] = $user->id;
        $ticket->update([
            "number" => $request["number"],
            "date" => $request["date"],
            "time" => $request["time"],
            "name" => $request["name"]

        ]);
        //delete old ticket notifications
        $oldNotifications = $ticket->notifications;
        foreach ($oldNotifications as $notif) {
            $notif->delete();
        }
        //store notifications related to new ticket
        if (!$request["notifications"]) $request["notifications"] = [];
        foreach ($request['notifications'] as $notif) {
            Notification::create([
                'number' => $notif,
                'ticket_id' => $ticket->id,
            ]);
        }


        return response()->json($ticket, 201);
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


    //validate ticket then increment service counter
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
            //store duration and status of ticket
            $ticket->update([
                'duration' => $request['duration'],
                'status' => $request['ticket_status']
            ]);
        }
        //if ticket number different from service counter =>don't increment service counter
        //because ticket is validated not in his turn (client is urgent)
        if (!($ticket && $ticket->number != $service->counter)) $service->update(["counter" => $service->counter + 1]);
        //return service tickets in response
        $service["tickets"] = $service->tickets;
        foreach ($service["tickets"] as $tick) {
            $tick["user"] = User::where("id", $tick["user_id"])->first();
        }

        //send notification to next user
        $nextUser = Ticket::where('date', $date)->where('service_id', $service->id)->where('status', 'IN_PROGRESS')->where('number', $service->counter)->first();
        if ($nextUser) {
            $receiv = User::where('id', $nextUser->user_id)->first();
            $this->sendNotif($receiv->messaging_token, "C'est votre tour !", "E-SAFF : " . $service->title);
        }
        $nextTickets = Ticket::where('date', $date)->where('service_id', $service->id)->where('status', 'IN_PROGRESS')->get();
        //send planified notifications
        for ($i = 0; $i < count($nextTickets); $i++) {
            foreach ($nextTickets[$i]->notifications as $notif) {
                if ($nextTickets[$i]->number - $notif->number == $service->counter) {
                    $text = "Il reste " . $notif . " tickets avant votre rendez-vous. Soyez prêt !";
                    $user = $nextTickets[$i]->user;
                    $this->sendNotif($user->messaging_token, $text, "E-SAFF : " . $service->title);
                }
            }
        }

        return response()->json($service, 200);
    }

    //delete ticket and related notifications
    public function delete(Ticket $ticket)
    {
        foreach ($ticket->notifications as $notif) {
            $notif->delete();
        }
        $ticket->delete();
        return response()->json(null, 204);
    }
}
