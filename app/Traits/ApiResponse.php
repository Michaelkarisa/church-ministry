<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function successResponse(
        mixed  $data    = null,
        string $message = 'Success',
        int    $status  = 200
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data instanceof LengthAwarePaginator) {
            $payload['data']       = $data->items();
            $payload['pagination'] = [
                'current_page' => $data->currentPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
                'last_page'    => $data->lastPage(),
                'from'         => $data->firstItem(),
                'to'           => $data->lastItem(),
            ];
        } else {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function errorResponse(
        string $message = 'Error',
        int    $status  = 400,
        mixed  $errors  = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function createdResponse(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function noContentResponse(string $message = 'Deleted successfully'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message], 200);
    }

    protected function validationErrorResponse(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors'  => $errors,
        ], 422);
    }

    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse("{$resource} not found.", 404);
    }

    protected function forbiddenResponse(string $message = 'You do not have permission to perform this action.'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }
}
