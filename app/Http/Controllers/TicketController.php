<?php

namespace App\Http\Controllers;

use App\Service;
use App\Ticket;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Auth;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class TicketController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $tickets = Ticket::where('user_id', $user->id)->get();
        foreach ($tickets as $ticket) {
            $ticket["service"] = Service::where('id', $ticket->service_id)->first();
        }
        return $tickets;
    }

    public function getAvailableTicketsByDate(string $date, string $service_id)
    {

        $service = Service::where('id', $service_id)->first();
        $availableTickets = array();
        //check day
        $day = Carbon::createFromFormat('Y-m-d', $date)->format('l');
        //   if (!in_array($day, $service->open_days)) return response()->json($availableTickets, 200);
        //check hoolidays
        if ($service->hoolidays && in_array($date, $service->hoolidays)) return response()->json($availableTickets, 200);
        $begin = new DateTime($service->work_start_time);
        $end   = new DateTime($service->work_end_time);
        $interval = DateInterval::createFromDateString($service->avg_time_per_client . ' min');
        $times    = new DatePeriod($begin, $interval, $end);
        $todayDate = Carbon::now()->format('Y-m-d');
        $todayTime = Carbon::now()->format('H:i');
        foreach ($times as $time) {
            if ($date == $todayDate && $time < $todayTime) continue;
            //check break times
            if (!$this->timeInBreak(date_format($time, 'H:i'), $service->break_times)) {
                $exist = DB::table('tickets')->where('service_id', $service_id)
                    ->whereDate('date', $date)->whereTime('time', $time)->first();
                if (!$exist || $exist->status == "CANCELED") $availableTickets[] = date_format($time, 'H:i');
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

    public function show(Ticket $ticket)
    {
        $ticket["service"] = $ticket->service;
        return $ticket;
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
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();
        //check duplicate ticket
        $exist = DB::table('tickets')->where('user_id', $user->id)->where('service_id', $request["service_id"])->where('status', 'IN_PROGRESS')->first();
        if ($exist) return response()->json(['error' => "ERROR_DUPLICATED_TICKET"], 401);
        //check if ticket already taked
        $taked = DB::table('tickets')->where('service_id', $request["service_id"])
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);

        //update ticket number
        $todayDate = Carbon::now()->format('Y-m-d');
        if ($todayDate == $request["date"]) {
            $service = Service::where('id', $request["service_id"])->first();
            $request["number"] = $request["number"] + $service->counter;
        }
        $request['status'] = 'IN_PROGRESS';
        $request["user_id"] = $user->id;
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
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $user = Auth::user();

        //check if ticket already taked
        $taked = DB::table('tickets')->where('service_id', $request["service_id"])
            ->whereDate('date', $request["date"])->whereTime('time', $request["time"])->first();
        if ($taked) return response()->json(['error' => "TICKET_ALREADY_TAKED"], 401);

        //update ticket number
        $todayDate = Carbon::now()->format('Y-m-d');
        if ($todayDate == $request["date"]) {
            $service = Service::where('id', $request["service_id"])->first();
            $request["number"] = $request["number"] + $service->counter;
        }

        $request['status'] = 'IN_PROGRESS';
        $request["user_id"] = $user->id;
        $oldTicket = DB::table('tickets')->where('user_id', $user->id)->where('service_id', $request["service_id"])->where('status', 'IN_PROGRESS')
            ->update([
                "number" => $request["number"],
                "date" => $request["date"],
                "time" => $request["time"]
            ]);

        return response()->json($oldTicket, 201);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $status = array("IN_PROGRESS", "DONE", "DELAYED", "CANCELED");

        $validator = Validator::make(
            $request->all(),
            [
                'number' => ['numeric', 'max:10000', 'min:0'],
                'status' => 'in:' . implode(',', $status),
                'date_time' => 'date_format:Y-m-d H:i:s'
            ]
        );


        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $ticket->update($request->all());

        return response()->json($ticket, 200);
    }

    public function delete(Ticket $ticket)
    {
        $ticket->delete();
        return response()->json(null, 204);
    }
}
