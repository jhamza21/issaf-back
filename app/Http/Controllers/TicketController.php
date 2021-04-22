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

class TicketController extends Controller
{
    public function index()
    {

        $user = Auth::user();
        $data = Ticket::select('tickets.id','tickets.number', 'tickets.date', 'tickets.time', 'tickets.status', 'services.title', 'services.description')->where('user_id', $user->id)
            ->join('services', 'tickets.service_id', '=', 'services.id')
            ->get();
        return $data;
    }

    public function getAvailableTicketsByDate(string $date, string $service_id)
    {

        $service = Service::where('id', $service_id)->first();
        $availableTickets = array();
        $begin = new DateTime($service->work_start_time);
        $end   = new DateTime($service->work_end_time);
        $interval = DateInterval::createFromDateString($service->avg_time_per_client . ' min');
        $times    = new DatePeriod($begin, $interval, $end);

        foreach ($times as $time) {
            $exist = DB::table('requests')->where('service_id', $service_id)
                ->whereDate('date_time', $date)->first();
            if (!$exist) $availableTickets[] = date_format($time, 'H:i');
        }

        return response()->json($availableTickets, 200);
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
                'service_id' => 'required|numeric|min:0',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        $lastTicket = Ticket::where('service_id',$request['service_id'])->orderBy('created_at','desc')->first();
        if ($lastTicket) {
            $request['number'] = $lastTicket->number + 1;
        } else {
            $request['number'] = 1;
        }
        $request['status'] = 'IN_PROGRESS';
        $user = Auth::user();
        $request["user_id"] = $user->id;
        $ticket = Ticket::create($request->all());

        return response()->json($ticket, 201);
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
