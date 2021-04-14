<?php

namespace App\Http\Controllers;

use App\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return $user->tickets;
    }

    
    public function getTicketsByDate(string $date,string $service_id)
    {
        $result = DB::table('requests')->where('serivce_id',$service_id)
    ->whereDate('date_time', $date);
    return response()->json($result,200);
    }

    public function show(Ticket $ticket)
    {
        $ticket["service"]=$ticket->service;
        return $ticket;
    }

    public function store(Request $request)
    {               
        $status=array("IN_PROGRESS","DONE","DELAYED","CANCELED");

        $validator = Validator::make($request->all(), 
        [ 
            'number' => ['required','numeric', 'max:10000','min:0'],
            'status' => 'required|in:' . implode(',', $status),
            'date_time' => 'required|date_format:Y-m-d H:i:s|after:yesterday'
       ]);   

        if ($validator->fails()) {          
        return response()->json(['error'=>$validator->errors()], 401);                        
        }

        $user = Auth::user();
        $request["user_id"]=$user->id;
        $ticket = Ticket::create($request->all());
      
        return response()->json($ticket, 201);
    }
    

    public function update(Request $request, Ticket $ticket)
    {
        $status=array("IN_PROGRESS","DONE","DELAYED","CANCELED");

        $validator = Validator::make($request->all(), 
        [ 
            'number' => ['numeric', 'max:10000','min:0'],
            'status' => 'in:' . implode(',', $status),
            'date_time' => 'date_format:Y-m-d H:i:s'
       ]);    
  

        if ($validator->fails()) {          
        return response()->json(['error'=>$validator->errors()], 401);                        
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
