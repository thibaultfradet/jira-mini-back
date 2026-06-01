<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponseTrait
{
    protected function success(array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['success' => true, ...$data], $status);
    }

    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message], $status);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    protected function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    protected function created(array $data = []): JsonResponse
    {
        return $this->success($data, Response::HTTP_CREATED);
    }

    protected function noContent(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
