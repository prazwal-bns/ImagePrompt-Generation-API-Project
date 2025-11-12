<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $token = $request->user()->createToken($request->user()->name. 'Auth-Token')->plainTextToken;

        return response()->json([
            'message' => 'User logged in successfully',
            'user' => $request->user(),
            'token' => $token,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        // $request->session()->regenerateToken();

        return response()->json([
            'message' => 'User logged out successfully',
        ]);
    }
}
