<?php

namespace App\Traits\V1;

trait ApiResponses
{
    protected function ok($message, $data = [])
    {
        return $this->success($message, $data, 200);
    }

    protected function success($message, $data = [], $statusCode = 200)
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
            'status' => $statusCode
        ]);
    }

    protected function error($errors = [], $statusCode = null)
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

    protected function notAuthorized($message)
    {
        return $this->error([
            'status' => 401,
            'message' => $message,
            'source' => ''
        ]);
    }
}
