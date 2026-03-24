<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(
                ApiResponse::error('INVALID_CREDENTIALS', 'Invalid email or password.'),
                401
            );
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(ApiResponse::success([
            'user' => $request->user(),
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(ApiResponse::success(null, [], 'Logged out.'));
    }
}