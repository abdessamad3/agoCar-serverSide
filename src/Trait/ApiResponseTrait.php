<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Standard API response envelope for all controllers.
 *
 * Success shape:  { "success": true,  "data": <any>,   "message": "" }
 * Error shape:    { "success": false, "message": "...", "errors": [] }
 */
trait ApiResponseTrait
{
    protected function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        return new JsonResponse($body, $status);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function unprocessable(string $message, array $errors = []): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    protected function validationError(array $errors): JsonResponse
    {
        return $this->error('Validation failed', 422, $errors);
    }
}
