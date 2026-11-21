<?php

namespace App\Modules\Members\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscribers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Mobile app login with national ID + password (Sanctum token). */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'ssn' => ['required', 'digits:14'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $member = Subscribers::where('ssn', $credentials['ssn'])->first();
        if (! $member || ! $member->password || ! Hash::check($credentials['password'], $member->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // One device at a time: a new login ends the previous session.
        $member->tokens()->delete();

        return response()->json([
            'access_token' => $member->createToken('subscriber_token')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $member = $request->user();
        $member->forceFill(['fcm_token' => null])->save();
        $member->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
