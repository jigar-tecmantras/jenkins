<?php

namespace App\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

trait FormRequestValidationTrait
{
    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();
        $responseErrors = [];

        foreach ($errors as $key => $value) {
            $currentError = is_array($value) ? implode(" | ", $value) : $value;
            $responseErrors = $currentError;
        }

        throw new HttpResponseException(
            response()->json(
                ['message' => $responseErrors],
                Response::HTTP_UNPROCESSABLE_ENTITY
            )
        );
    }
}
