<?php

declare(strict_types=1);

namespace App\Security;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use JsonException;
use RuntimeException;

final class JwtService
{
    public function issue(User $user): string
    {
        $issuedAt = time();
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => (string) config('jwt.issuer'),
            'sub' => (string) $user->id,
            'email' => $user->email,
            'iat' => $issuedAt,
            'exp' => $issuedAt + ((int) config('jwt.ttl_minutes', 60) * 60),
        ];

        $segments = [$this->encode($header), $this->encode($payload)];
        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret(), true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw new AuthenticationException('Malformed JWT.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $segments;
        $expected = hash_hmac('sha256', $headerSegment.'.'.$payloadSegment, $this->secret(), true);
        $actual = $this->base64UrlDecode($signatureSegment);

        if (! hash_equals($expected, $actual)) {
            throw new AuthenticationException('Invalid JWT signature.');
        }

        try {
            $header = json_decode($this->base64UrlDecode($headerSegment), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($payloadSegment), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AuthenticationException('Invalid JWT payload.');
        }

        if (($header['alg'] ?? null) !== 'HS256' || ! is_array($payload)) {
            throw new AuthenticationException('Unsupported JWT.');
        }

        if (! isset($payload['sub'], $payload['exp']) || (int) $payload['exp'] <= time()) {
            throw new AuthenticationException('Expired JWT.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        try {
            return $this->base64UrlEncode(json_encode($value, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('JWT encoding failed.', previous: $exception);
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new AuthenticationException('Invalid JWT encoding.');
        }

        return $decoded;
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');
        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded === false ? '' : $decoded;
        }

        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must contain at least 32 bytes.');
        }

        return $secret;
    }
}
