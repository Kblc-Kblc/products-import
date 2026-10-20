<?php

declare(strict_types=1);

namespace App\Auth;

use App\Controller\Json;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Requires "Authorization: Bearer <jwt>"; exposes the user as the "user" request attribute. */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly ResponseFactoryInterface $responses,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $user = str_starts_with($header, 'Bearer ') ? $this->jwt->verify(substr($header, 7)) : null;

        if ($user === null) {
            return Json::write($this->responses->createResponse(), ['error' => 'Требуется авторизация'], 401)
                ->withHeader('WWW-Authenticate', 'Bearer');
        }

        return $handler->handle($request->withAttribute('user', $user));
    }
}
