<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class ApiTokenController extends Controller
{
    public function store(Request $request, JwtService $jwt): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();
        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Las credenciales no son válidas.'], 422);
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $jwt->issue($user),
            'expires_in' => (int) config('jwt.ttl_minutes', 60) * 60,
        ]);
    }
}
