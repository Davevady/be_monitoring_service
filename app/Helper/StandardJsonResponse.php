<?php

declare(strict_types=1);

namespace App\Helper;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * Standard JSON Response Helper
 * 
 * Provides consistent JSON response format across all API endpoints
 */
class StandardJsonResponse
{
    /**
     * Success response with data
     */
    public static function success(
        HttpResponse $response,
        mixed $data = null,
        string $message = 'Success',
        array $meta = [],
        int $statusCode = 200
    ): PsrResponse {
        $responseData = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $responseData['meta'] = $meta;
        }

        return $response->json($responseData, $statusCode);
    }

    /**
     * Error response
     */
    public static function error(
        HttpResponse $response,
        string $message = 'Error occurred',
        mixed $data = null,
        array $meta = [],
        int $statusCode = 400
    ): PsrResponse {
        $responseData = [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $responseData['meta'] = $meta;
        }

        return $response->json($responseData, $statusCode);
    }

    /**
     * Validation error response
     */
    public static function validationError(
        HttpResponse $response,
        array $errors,
        string $message = 'Validation failed',
        int $statusCode = 422
    ): PsrResponse {
        return $response->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => [
                'errors' => $errors,
            ],
        ], $statusCode);
    }

    /**
     * Unauthorized response
     */
    public static function unauthorized(
        HttpResponse $response,
        string $message = 'Unauthorized access'
    ): PsrResponse {
        return $response->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 401);
    }

    /**
     * Forbidden response
     */
    public static function forbidden(
        HttpResponse $response,
        string $message = 'Forbidden access'
    ): PsrResponse {
        return $response->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 403);
    }

    /**
     * Not found response
     */
    public static function notFound(
        HttpResponse $response,
        string $message = 'Resource not found'
    ): PsrResponse {
        return $response->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 404);
    }

    /**
     * Server error response
     */
    public static function serverError(
        HttpResponse $response,
        string $message = 'Internal server error',
        mixed $data = null
    ): PsrResponse {
        return $response->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], 500);
    }

    /**
     * Paginated response
     */
    public static function paginated(
        HttpResponse $response,
        mixed $data,
        array $pagination,
        string $message = 'Success',
        array $meta = []
    ): PsrResponse {
        $responseData = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge($meta, [
                'pagination' => $pagination,
            ]),
        ];

        return $response->json($responseData);
    }

    /**
     * Created response (201)
     */
    public static function created(
        HttpResponse $response,
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): PsrResponse {
        return self::success($response, $data, $message, [], 201);
    }

    /**
     * Updated response (200)
     */
    public static function updated(
        HttpResponse $response,
        mixed $data = null,
        string $message = 'Resource updated successfully'
    ): PsrResponse {
        return self::success($response, $data, $message);
    }

    /**
     * Deleted response (200)
     */
    public static function deleted(
        HttpResponse $response,
        string $message = 'Resource deleted successfully'
    ): PsrResponse {
        return self::success($response, null, $message);
    }

    /**
     * No content response (204)
     */
    public static function noContent(HttpResponse $response): PsrResponse
    {
        return $response->json(null, 204);
    }
}
