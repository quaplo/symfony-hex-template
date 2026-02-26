<?php

declare(strict_types=1);

namespace App\Authorization\Domain;

interface TokenValidator
{
    public function isValid(string $token): bool;
}
