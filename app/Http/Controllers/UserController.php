<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function index()
    {
        return User::all();
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
                'country' => 'string|min:2|max:2',
                'name' => 'min:4|max:80|string',
                'sexe' => 'min:4|max:80|string',
                'password' => 'min:8|string'
            ]);

            if ($validate->fails())
                return response()->json(['error' => $validate->errors()->first()], 401);
            else {
                if ($request['username']) $user->username = $request['username'];
                if ($request['email']) $user->email = $request['email'];
                if ($request['mobile']) $user->mobile = $request['mobile'];
                if ($request['country']) $user->country = $request['country'];
                if ($request['name']) $user->name = $request['name'];
                if ($request['sexe']) $user->sexe = $request['sexe'];
                if ($request['password']) $user->password = Hash::make($request['password']);
                $user->save();
                return response()->json($user, 200);
            }
        } else {
            return response()->json(["error" => "USER_NOT_FOUND"], 401);
        }
    }
}
