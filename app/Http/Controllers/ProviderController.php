<?php

namespace App\Http\Controllers;

use App\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index()
    {
        return Provider::all();
    }

    public function show(Provider $provider)
    {
        return $provider;
    }

    public function store(Request $request)
    {
        $provider = Provider::create($request->all());

        return response()->json($provider, 201);
    }

    public function update(Request $request, Provider $provider)
    {
        $provider->update($request->all());

        return response()->json($provider, 200);
    }

    public function delete(Provider $provider)
    {
        $provider->delete();

        return response()->json(null, 204);
    }
}
