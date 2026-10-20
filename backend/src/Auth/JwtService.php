<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private const ALGORITHM = 'HS256';
    private const TTL_SECONDS = 8 * 3600;

    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new \LogicException('JWT_SECRET must be at least 32 characters');
        }
    }

    /** @return array{token: string, expiresAt: int} */
    public function issue(string $subject): array
    {
        $expiresAt = time() + self::TTL_SECONDS;

        return [
            'token' => JWT::encode(['sub' => $subject, 'iat' => time(), 'exp' => $expiresAt], $this->secret, self::ALGORITHM),
            'expiresAt' => $expiresAt,
        ];
    }

    /** @return string|null the subject, or null if the token is invalid or expired */
    public function verify(string $token): ?string
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\Throwable) {
            return null;
        }

        return is_string($payload->sub ?? null) ? $payload->sub : null;
    }
}
