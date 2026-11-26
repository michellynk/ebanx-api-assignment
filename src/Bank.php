<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\AccountNotFound;
use App\Exceptions\InsufficientFunds;
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
        $this->assertExists($accounts, $accountId);

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

    /** A conta precisa existir e ter saldo suficiente. Devolve o novo saldo. */
    public function withdraw(string $origin, int $amount): int
    {
        $this->assertPositive($amount);

        return $this->store->transaction(function (array &$accounts) use ($origin, $amount): int {
            $this->assertExists($accounts, $origin);

            if ($accounts[$origin] < $amount) {
                throw new InsufficientFunds($origin, $accounts[$origin], $amount);
            }

            $accounts[$origin] -= $amount;

            return $accounts[$origin];
        });
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidAmount($amount);
        }
    }

    /** @param array<string, int> $accounts */
    private function assertExists(array $accounts, string $accountId): void
    {
        if (!array_key_exists($accountId, $accounts)) {
            throw new AccountNotFound($accountId);
        }
    }
}
