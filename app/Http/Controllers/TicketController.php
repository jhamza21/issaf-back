<?php

namespace App\Http\Controllers;

use App\Notification;
use App\Service;
use App\Ticket;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Auth;
use DateInterval;
use DatePeriod;
use DateTime;
use Carbon\Carbon;


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

    public function ticketsResponsible()
    {
        $user = Auth::user();
        $tickets = Ticket::where('user_id', $user->id)->where('name', "!=", null)->get();
        foreach ($tickets as $ticket) {
            $ticket["service"] = Service::where('id', $ticket->service_id)->first();
        }
        return $tickets;
    }


    public function getAvailableTicketsByDate(string $date, string $service_id)
    {
        $service = null;
        if ($service_id == -1) {
            $user = Auth::user();
            $service = $user->service;
            $service_id = $service->id;
        } else
            $service = Service::where('id', $service_id)->first();
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

        foreach ($times as $time) {
            if (!$this->timeInBreak(date_format($time, 'H:i'), $service->break_times)) {
                if ($date == date('Y-m-d') && $todayTime > $time->format('H:i')) {
                    $availableTickets[date_format($time, 'H:i')] = "N";
                } else {
                    $exist = Ticket::where('service_id', $service_id)
                        ->whereDate('date', $date)->whereTime('time', $time)->first();
                    if (!$exist || $exist->status == "UNDONE") {
                        $availableTickets[date_format($time, 'H:i')] = "T";
                    } else if ($exist->status == "IN_PROGRESS") {
                        $availableTickets[date_format($time, 'H:i')] = "N";
                    } else
                        $availableTickets[date_format($time, 'H:i')] = "F";
                }
            }
        }

        return response()->json($availableTickets, 200);
    }

    private function timeInBreak($time, $breaks)
    {
        if (!$breaks) return false;
        foreach ($breaks as $break) {
            $startTime = Carbon::createFromFormat('H:i', substr($break, 0, 5));
            $endTime = Carbon::createFromFormat('H:i', substr($break, 10, 5));
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

        //update ticket number
        $todayDate = Carbon::now()->format('Y-m-d');
        $service = Service::where('id', $request["service_id"])->first();
        if ($todayDate == $request["date"]) {
            $request["number"] = $request["number"] + $service->counter;
        }
        $request['status'] = 'IN_PROGRESS';
        $request['duration'] = 0;
        $request['name'] = null;
        $request["user_id"] = $user->id;
        $ticket = Ticket::create($request->all());
        if (!$request["notifications"]) $request["notifications"] = [];
        foreach ($request['notifications'] as $notif) {
            if ($request['number'] - $notif > $service->counter)
                Notification::create([
                    'number' => $request['number'] - $notif,
                    'ticket_id' => $ticket->id,
                    'service_id' => $request["service_id"],
                    'date' => $request["date"],
                    'messaging_token' => $user->messaging_token
                ]);
        }

        return response()->json($ticket, 201);
    }

    public function storeRespo(Request $request)
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
        $service = $user->service;
        //check if ticket already taked
        $taked = Ticket::where('service_id', $service->id)
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);

        //update ticket number
        $todayDate = Carbon::now()->format('Y-m-d');
        if ($todayDate == $request["date"]) {
            $request["number"] = $request["number"] + $service->counter;
        }
        $request['status'] = 'IN_PROGRESS';
        $request['duration'] = 0;
        $request["user_id"] = $user->id;
        $request["service_id"] = $service->id;

        $ticket = Ticket::create($request->all());
        return response()->json($ticket, 201);
    }

    public function  reschudleTicket(Request $request)
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

        //update ticket number
        $todayDate = Carbon::now()->format('Y-m-d');
        $service = Service::where('id', $request["service_id"])->first();
        if ($todayDate == $request["date"]) {
            $request["number"] = $request["number"] + $service->counter;
        }

        $request['status'] = 'IN_PROGRESS';
        $request["user_id"] = $user->id;
        $oldTicket = Ticket::where('user_id', $user->id)->where('service_id', $request["service_id"])->where('status', 'IN_PROGRESS')->first();
        $newTicket = $oldTicket->update([
            "number" => $request["number"],
            "date" => $request["date"],
            "time" => $request["time"]
        ]);
        $notifications = Notification::where('ticket_id', $oldTicket->id)->get();
        foreach ($notifications as $notif) {
            $notif->delete();
        }
        if (!$request["notifications"]) $request["notifications"] = [];
        foreach ($request['notifications'] as $notif) {
            if ($request['number'] - $notif > $service->counter)
                Notification::create([
                    'number' => $request['number'] - $notif,
                    'ticket_id' => $oldTicket->id,
                    'service_id' => $request["service_id"],
                    'date' => $request["date"],
                    'messaging_token' => $user->messaging_token
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
}
