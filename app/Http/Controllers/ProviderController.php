<?php

namespace App\Http\Controllers;

use App\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Auth;

class ProviderController extends Controller
{
    //return all providers
    public function index()
    {
        return Provider::all();
    }


    //get connected user provider
    public function getProviderByUser()
    {
        $user = Auth::user();
        return response()->json($user->provider, 200);
    }

    //get provider by id
    public function getProviderById(Provider $provider)
    {
        $provider["services"] = $provider->services;
        return response()->json($provider, 200);
    }

    //store new provider
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'type' => ['required', 'string', 'max:255', 'min:2'],
                'title' => ['required', 'string', 'max:255', 'min:2'],
                'description' => ['required', 'string', 'min:8', 'max:255'],
                'mobile' => ['string', 'max:20', 'min:16'],
                'region' => ['required', 'string', 'min:2', 'max:20'],
                'email' => ['string', 'email'],
                'url' => ['string'],
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 401);
        }
        if ($request["img"] != null) {
            $res = $request->file("img")->store("providersImg");
            $request["image"] = substr($res, strpos($res, "/") + 1);
        }
        $user = Auth::user();
        $request["user_id"] = $user->id;
        $provider = Provider::create($request->all());


        return response()->json($provider, 201);
    }

    //return provider image
    public function downloadImage(String $imgName)
    {
        return response()->download(storage_path() . "/" . "app/providersImg/" . $imgName);
    }


    //update provider
    public function update(Request $request, Provider $provider)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'type' => ['string', 'max:255', 'min:2'],
                'title' => ['string', 'max:255', 'min:2'],
                'description' => ['string', 'min:8', 'max:255'],
                'mobile' => ['string', 'max:20', 'min:16'],
                'region' => ['string', 'min:2', 'max:20'],
                'email' => ['string', 'email'],
                'url' => ['string'],
                'img' => 'mimes:jpg,jpeg,png|max:2048',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }
        if ($request["img"] != null) {
            $res = $request->file("img")->store("providersImg");
            $request["image"] = substr($res, strpos($res, "/") + 1);
        }
        $provider->update($request->all());

        return response()->json($provider, 200);
    }

    //delete provider
    public function delete(Provider $provider)
    {
        foreach ($provider->services as $service) {
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
        }
        //delete related image to provider 
        $path = storage_path() . "/" . "app/providersImg/" . $provider->image;
        if (File::exists($path)) {
            File::delete($path);
        }
        $provider->delete();
        return response()->json(null, 204);
    }
}
