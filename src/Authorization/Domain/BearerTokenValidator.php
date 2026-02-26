<?php

declare(strict_types=1);

namespace App\Authorization\Domain;

final readonly class BearerTokenValidator implements TokenValidator
{
    /** @param string[] $allowedTokens */
    public function __construct(private array $allowedTokens) {}

    public function isValid(string $token): bool
    {
        return in_array($token, $this->allowedTokens, strict: true);
    }
}
