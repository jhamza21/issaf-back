<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function getSuggestions(String $text)
    {
        $users = User::where('username', 'LIKE', "%{$text}%")
            ->orWhere('email', 'LIKE', "%{$text}%")
            ->orWhere('name', 'LIKE', "%{$text}%")
            ->get();
                $idUser = Auth::user()->id;
                $filtred=[];
                foreach ($users as $user) {
                    if($idUser!=$user->id)$filtred[]=$user;                }
        return $filtred;
    }

    public function getUserByUsername(String $username)
    {
        $user = User::where('username', $username)->first();
        if ($user)
            return response()->json($user, 200);
        return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function getUserById(String $id)
    {
        $user = User::where('id', $id)->first();
        if ($user)
            return response()->json($user, 200);
        return response()->json("RESSOURCE_NOT_FOUND", 404);
    }

    public function update(Request $request)
    {
        $user = User::find(Auth::user()->id);

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
}
