<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;


class UserController extends Controller
{

    //get users suggestions based on given string
    //remove connected user from suggestions
    public function getSuggestions(String $text)
    {
        $users = User::where('username', 'LIKE', "%{$text}%")->where('id', '!=', Auth::user()->id)
            ->orWhere('email', 'LIKE', "%{$text}%")
            ->orWhere('name', 'LIKE', "%{$text}%")
            ->get();
        return response()->json($users, 404);
    }

    //get user by email (used to connect user with GOOGLE ACCOUNT)
    public function getUserByEmail(String $email)
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            $user->generateToken();
            return response()->json($user, 200);
        }
        return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    //update connected user
    public function update(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $validate = Validator::make($request->all(), [
                'username' => 'string|min:6|max:255|unique:users',
                'email' => 'string|email|unique:users',
                'mobile' => 'min:16|max:20|string',
                'name' => 'min:4|max:80|string',
                'password' => 'min:8|string',
                'region' => 'string|min:2|max:20',
                'messaging_token' => 'string',


            ]);
            //check request validation
            if ($validate->fails())
                return response()->json(['error' => $validate->errors()->first()], 401);
            else {
                if ($request['username']) $user->username = $request['username'];
                if ($request['email']) $user->email = $request['email'];
                if ($request['mobile']) $user->mobile = $request['mobile'];
                if ($request['name']) $user->name = $request['name'];
                if ($request['password']) $user->password = Hash::make($request['password']);
                if ($request['region']) $user->region = $request['region'];
                if ($request['messaging_token']) $user->messaging_token = $request['messaging_token'];

                $user->save();
                return response()->json($user, 200);
            }
        } else {
            return response()->json(["error" => "USER_NOT_FOUND"], 401);
        }
    }

    //delete connected user
    public function delete()
    {
        $user = Auth::user();
        //delete related tickets to user
        foreach ($user->tickets as $tic) {
            //delete related notifications
            foreach ($tic->notifications as $notif) {
                $notif->delete();
            }
            $tic->delete();
        }
        $provider = $user->provider;
        if ($provider) {
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
        }
        $user->delete();
        return response()->json(null, 204);
    }
}
