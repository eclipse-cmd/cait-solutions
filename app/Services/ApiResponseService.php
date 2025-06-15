<?php

namespace App\Services;

class ApiResponseService
{
  public static function success($message = null, $data = null, $statusCode = 200)
  {
    $response = [
      'status' => true,
      'message' => $message,
      'data' => $data
    ];
    return response()->json(array_filter($response));
  }

  public static function error($message = 'There was an error processing your request.', $errors = [], $statusCode = 400)
  {
    return response()->json([
      'status' => false,
      'message' => $message,
      'errors' => $errors
    ], $statusCode);
  }
}
