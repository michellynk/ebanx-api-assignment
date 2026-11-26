<?php

declare(strict_types=1);

namespace App;

use DomainException;

final class AccountNotFound extends DomainException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Conta {$accountId} não existe.");
    }
}
