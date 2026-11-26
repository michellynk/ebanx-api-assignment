<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\AccountNotFound;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\InvalidAmount;
use App\Exceptions\SameAccountTransfer;
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
            $this->credit($accounts, $destination, $amount);

            return $accounts[$destination];
        });
    }

    /** A conta precisa existir e ter saldo suficiente. Devolve o novo saldo. */
    public function withdraw(string $origin, int $amount): int
    {
        $this->assertPositive($amount);

        return $this->store->transaction(function (array &$accounts) use ($origin, $amount): int {
            $this->debit($accounts, $origin, $amount);

            return $accounts[$origin];
        });
    }

    /**
     * Débito e crédito acontecem na mesma transação: ou os dois são gravados,
     * ou nenhum. O destino é criado se não existir.
     *
     * @return array{origin: int, destination: int}
     */
    public function transfer(string $origin, string $destination, int $amount): array
    {
        $this->assertPositive($amount);

        if ($origin === $destination) {
            throw new SameAccountTransfer($origin);
        }

        return $this->store->transaction(function (array &$accounts) use ($origin, $destination, $amount): array {
            $this->debit($accounts, $origin, $amount);
            $this->credit($accounts, $destination, $amount);

            return ['origin' => $accounts[$origin], 'destination' => $accounts[$destination]];
        });
    }

    /** Apaga todas as contas. */
    public function reset(): void
    {
        $this->store->transaction(function (array &$accounts): void {
            $accounts = [];
        });
    }

    /** @param array<string, int> $accounts */
    private function credit(array &$accounts, string $accountId, int $amount): void
    {
        $accounts[$accountId] = ($accounts[$accountId] ?? 0) + $amount;
    }

    /** @param array<string, int> $accounts */
    private function debit(array &$accounts, string $accountId, int $amount): void
    {
        $this->assertExists($accounts, $accountId);

        if ($accounts[$accountId] < $amount) {
            throw new InsufficientFunds($accountId, $accounts[$accountId], $amount);
        }

        $accounts[$accountId] -= $amount;
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
