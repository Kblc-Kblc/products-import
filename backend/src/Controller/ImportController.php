<?php

declare(strict_types=1);

namespace App\Controller;

use App\Import\ImportService;
use App\Import\InvalidUploadException;
use App\Repository\ImportJobRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ImportController
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly ImportJobRepository $jobs,
    ) {}

    /** POST /api/imports (multipart, field "file") */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $job = $this->imports->queue($request->getUploadedFiles()['file'] ?? null);
        } catch (InvalidUploadException $e) {
            return Json::write($response, ['error' => $e->getMessage()], 422);
        }

        return Json::write($response, $job->toArray(), 202)
            ->withHeader('Location', '/api/imports/' . $job->getId());
    }

    /**
     * GET /api/imports/{id} — progress and error report
     *
     * @param array{id: string} $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $job = $this->jobs->find((int) $args['id']);

        return $job === null
            ? Json::write($response, ['error' => 'Импорт не найден'], 404)
            : Json::write($response, $job->toArray());
    }
}
