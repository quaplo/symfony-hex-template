<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Infrastructure\Http\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class BaseController
{
    public function __construct(
        protected readonly SerializerInterface $serializer,
        protected readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Deserialize and validate DTO from request.
     */
    protected function deserializeAndValidate(Request $request, string $dtoClass): object
    {
        /** @var object $dto */
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            $dtoClass,
            'json'
        );

        $constraintViolationList = $this->validator->validate($dto);

        if (\count($constraintViolationList) > 0) {
            throw new ValidationException($constraintViolationList);
        }

        return $dto;
    }

    /**
     * Create validation error response.
     */
    protected function createValidationErrorResponse(
        ConstraintViolationListInterface $constraintViolationList
    ): JsonResponse {
        $errors = [];

        foreach ($constraintViolationList as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new JsonResponse([
            'error' => 'Validation failed',
            'violations' => $errors,
        ], JsonResponse::HTTP_BAD_REQUEST);
    }
}
