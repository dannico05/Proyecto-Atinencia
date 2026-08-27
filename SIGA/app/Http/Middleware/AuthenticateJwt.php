<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Security\JwtService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateJwt
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return new JsonResponse(['message' => 'No se proporcionó un token JWT.'], 401);
        }

        try {
            $payload = $this->jwt->verify($token);
            $user = User::query()->find((int) $payload['sub']);
        } catch (AuthenticationException) {
            return new JsonResponse(['message' => 'El token JWT no es válido o ya venció.'], 401);
        }

        if ($user === null) {
            return new JsonResponse(['message' => 'El usuario del token ya no existe.'], 401);
        }

        $request->setUserResolver(static fn (): User => $user);
        Auth::setUser($user);

        return $next($request);
    }
}
