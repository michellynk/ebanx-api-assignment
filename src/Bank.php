<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\AccountNotFound;
use App\Exceptions\InvalidAmount;
use App\Storage\FileStore;

/**
 * Regras de negócio das contas. Não sabe nada de HTTP: recebe valores já
 * tipados, devolve saldos e sinaliza erros com exceções de domínio.
 */
final class Bank
{
    public function __construct(private readonly FileStore $store)
    {
    }

    /** Consulta pura: usa read(), que nunca grava. */
    public function balance(string $accountId): int
    {
        $accounts = $this->store->read();

        if (!array_key_exists($accountId, $accounts)) {
            throw new AccountNotFound($accountId);
        }

        return $accounts[$accountId];
    }

    /** Cria a conta se ela não existir. Devolve o novo saldo. */
    public function deposit(string $destination, int $amount): int
    {
        $this->assertPositive($amount);

        return $this->store->transaction(function (array &$accounts) use ($destination, $amount): int {
            $accounts[$destination] = ($accounts[$destination] ?? 0) + $amount;

            return $accounts[$destination];
        });
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidAmount($amount);
        }
    }
}
