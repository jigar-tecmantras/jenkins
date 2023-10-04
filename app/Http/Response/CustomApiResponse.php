<?php

/** @noinspection ALL */

namespace App\Http\Response;

use Symfony\Component\HttpFoundation\Response as ResponseHTTP;

class CustomApiResponse
{
    public function getResponseStructure($success = false, $payload = null, $message = '')
    {
         if (!empty($success) && !empty($payload)) {
            $data = [
                'message' => $message,
                'data' => $payload
            ];
        } elseif ($success) {
            $data = [
                'message' => $message,
            ];
        } else {
            $data = [
                'error' => [
                     $message,
                ]
            ];
        }

        return $data;

        //json_encode($data);
    }

    /**
     * handle all type of exceptions
     * @param \Exception $ex
     * @return mixed|string
     */
    public function handleAndResponseException(\Exception $ex)
    {
        $response = '';
        switch (true) {
            case $ex instanceof \Illuminate\Database\Eloquent\ModelNotFoundException:
                $response = response()->json(['message' => trans('message.record_not_found')], ResponseHTTP::HTTP_NOT_FOUND);
                break;
            case $ex instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException:
                $response = response()->json(['message' => trans('message.not_found')], ResponseHTTP::HTTP_NOT_FOUND);
                break;
            case $ex instanceof \Illuminate\Database\QueryException:
                $response = response()->json(['message' => trans('message.wrong_with_query')], ResponseHTTP::HTTP_BAD_REQUEST);
                break;
            case $ex instanceof \Illuminate\Http\Exceptions\HttpResponseException:
                $response = response()->json(['error' => trans('message.wrong_with_system')], ResponseHTTP::HTTP_INTERNAL_SERVER_ERROR);
                break;
            case $ex instanceof \Illuminate\Validation\ValidationException:
                $response = response()->json(['message' => trans('message.invalid_data')], ResponseHTTP::HTTP_BAD_REQUEST);
                break;
                case $ex instanceof \App\Exceptions\LinkedInFailException:
                    $response = response()->json(['message' => "Unauthorized Access!"], ResponseHTTP::HTTP_UNAUTHORIZED);
                    break;   
            case $ex instanceof \Exception:
                $response = response()->json(['message' => "Something is going wrong with our system!"], ResponseHTTP::HTTP_INTERNAL_SERVER_ERROR);
                break;
        }
        return $response;
    }
}
