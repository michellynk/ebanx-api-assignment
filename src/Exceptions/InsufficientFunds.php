<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class InsufficientFunds extends DomainException
{
    public function __construct(string $accountId, int $balance, int $amount)
    {
        parent::__construct("Conta {$accountId} tem saldo {$balance}, insuficiente para {$amount}.");
    }
}
