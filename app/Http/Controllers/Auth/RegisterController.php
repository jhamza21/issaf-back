<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;


class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        $roles=array("CLIENT","ADMIN_SERVICE","ADMIN_SAFF");
        $sexes=array("HOMME","FEMME");
        return Validator::make($data, [
            'username' => ['required','string', 'max:255','min:6', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required','string', 'max:255','min:6'],
            'sexe' => 'required|in:' . implode(',', $sexes),
            'role' => 'required|in:' . implode(',', $roles),
            'email' => 'required|string|email',
            'mobile' => 'required|string|min:16|max:20',

            ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        return User::create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'sexe' => $data['sexe'],
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],

        ]);
    }
    protected function registered(Request $request, $user)
{
    $user->generateToken();

    return response()->json(['data' => $user->toArray()], 201);
}
}
