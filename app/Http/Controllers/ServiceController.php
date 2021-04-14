<?php

namespace App\Http\Controllers;

use App\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    public function index()
    {
        return Service::all();
    }

    public function show(Service $service)
    {
        return $service;
    }

    public function store(Request $request)
    {               
        $days=array("MON","TUE","WED","THU","FRI","SAT","SUN");
        $status=array("OPENED","CLOSED");

        $validator = Validator::make($request->all(), 
        [ 
            'title' => ['required','string', 'max:255','min:2'],
            'description' => [ 'string', 'min:4','max:255'],
            'avg_time_per_client' => ['required','numeric', 'max:300','min:1'],
            'counter' => ['required','numeric', 'max:10000','min:0'],
            'work_start_time' => ['required', 'date_format:H:i'],
            'work_end_time' => ['required','date_format:H:i','after:work_start_time'],
            'open_days' => "required|array|min:1",
            'open_days.*' => 'required|string|distinct|in:' . implode(',', $days),
            'status' => 'required|in:' . implode(',', $status),
            'provider_id' =>'required|numeric|min:0',
            'img' => 'mimes:jpg,jpeg,png|max:2048',
       ]);   

        if ($validator->fails()) {          
        return response()->json(['error'=>$validator->errors()], 401);                        
        }
        if($request["img"]!=null){
        $res=$request->file("img")->store("servicesImg");
        $request["image"]=substr($res, strpos($res, "/")+1 );
        }
        $service = Service::create($request->all());
      

        return response()->json($service, 201);
    }

    public function downloadImage(String $imgName)
    {
        return response()->download(storage_path()."/"."app/servicesImg/".$imgName);
    }

    

    public function update(Request $request, Service $service)
    {
        $days=array("MON","TUE","WED","THU","FRI","SAT","SUN");
        $status=array("OPENED","CLOSED");

        $validator = Validator::make($request->all(), 
        [ 
            'title' => ['string', 'max:255','min:2'],
            'description' => [ 'string', 'min:4','max:255'],
            'avg_time_per_client' => ['numeric', 'max:300','min:1'],
            'counter' => ['numeric', 'max:10000','min:0'],
            'work_start_time' => [ 'date_format:H:i'],
            'work_end_time' => ['date_format:H:i','after:work_start_time'],
            'open_days' => "array|min:1",
            'open_days.*' => 'string|distinct|in:' . implode(',', $days),
            'status' => 'in:' . implode(',', $status),
            'id_provider' =>'numeric|min:0',
            'img' => 'mimes:jpg,jpeg,png|max:2048',
       ]);   

        if ($validator->fails()) {          
        return response()->json(['error'=>$validator->errors()], 401);                        
        }
        $service->update($request->all());

        return response()->json($service, 200);
    }

    public function delete(Service $service)
    {
        $service->delete();
        return response()->json(null, 204);
    }
}
