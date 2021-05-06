<?php

namespace App\Http\Controllers;

use App\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Auth;

class ProviderController extends Controller
{
    public function getUserProvider()
    {
        $user = Auth::user();
        $result = Provider::where('user_id', $user->id)
            ->first();
        return response()->json($result, 200);
    }
    public function index()
    {
        return Provider::all();
    }

    public function show(Provider $provider)
    {
        $provider["services"] = $provider->services;
        return $provider;
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'type' => ['required', 'string', 'max:255', 'min:2'],
                'title' => ['required', 'string', 'max:255', 'min:2'],
                'description' => ['required', 'string', 'min:8', 'max:255'],
                'mobile' => ['string', 'max:20', 'min:16'],
                'region' => ['required','string','min:2','max:20'],
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

    public function downloadImage(String $imgName)
    {
        return response()->download(storage_path() . "/" . "app/providersImg/" . $imgName);
    }



    public function update(Request $request, Provider $provider)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'type' => ['string', 'max:255', 'min:2'],
                'title' => ['string', 'max:255', 'min:2'],
                'description' => ['string', 'min:8', 'max:255'],
                'mobile' => ['string', 'max:20', 'min:16'],
                'region' => ['string','min:2','max:20'],
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

    public function delete(Provider $provider)
    {
        $provider->delete();
        return response()->json(null, 204);
    }
}
