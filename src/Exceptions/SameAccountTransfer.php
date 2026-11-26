<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class SameAccountTransfer extends DomainException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Origem e destino são a mesma conta: {$accountId}.");
    }
}
