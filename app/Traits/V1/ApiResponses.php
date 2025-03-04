<?php

namespace App\Traits\V1;

trait ApiResponses
{
    protected function ok(mixed $message, mixed $data = [])
    {
        return $this->success($message, $data, 200);
    }

    protected function success(mixed $message, mixed $data = [], int|null $statusCode = 200)
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'status' => $statusCode
        ]);
    }

    protected function error(mixed $errors = [], int|null $statusCode = null)
    {
        if (is_string($errors)) {
            return response()->json([
                'message' => $errors,
                'status' => $statusCode
            ]);
        }

        return response()->json([
            'errors' => $errors
        ]);
    }
}
