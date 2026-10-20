<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly string $adminEmail,
        private readonly string $adminPassword,
    ) {
    }

    /** POST /api/auth/login {"email": "...", "password": "..."} */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $email = is_array($body) && is_string($body['email'] ?? null) ? $body['email'] : '';
        $password = is_array($body) && is_string($body['password'] ?? null) ? $body['password'] : '';

        // shortcut: a single admin from env, add a users table when more accounts are needed
        $valid = $this->adminPassword !== ''
            && hash_equals(mb_strtolower($this->adminEmail), mb_strtolower(trim($email)))
            && hash_equals($this->adminPassword, $password);
        if (!$valid) {
            return Json::write($response, ['error' => 'Неверный email или пароль'], 401);
        }

        return Json::write($response, ['email' => $this->adminEmail] + $this->jwt->issue($this->adminEmail));
    }
}
