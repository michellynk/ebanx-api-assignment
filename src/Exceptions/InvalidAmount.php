<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class InvalidAmount extends DomainException
{
    public function __construct(int $amount)
    {
        parent::__construct("O valor deve ser maior que zero, recebido: {$amount}.");
    }
}
