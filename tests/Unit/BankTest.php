<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Bank;
use App\Exceptions\AccountNotFound;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\InvalidAmount;
use App\Exceptions\SameAccountTransfer;
use App\Storage\FileStore;
use PHPUnit\Framework\TestCase;

/**
 * Testa as regras com o store real. Cada teste confere o efeito no estado
 * (o saldo lido depois), não só o valor de retorno.
 */
final class BankTest extends TestCase
{
    private string $path;
    private FileStore $store;
    private Bank $bank;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ebanx-bank-' . uniqid() . '.json';
        $this->store = new FileStore($this->path);
        $this->bank = new Bank($this->store);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testBalanceOfNonExistingAccountThrows(): void
    {
        $this->expectException(AccountNotFound::class);

        $this->bank->balance('1234');
    }

    public function testDepositCreatesAccountWithInitialBalance(): void
    {
        $this->assertSame(10, $this->bank->deposit('100', 10));
        $this->assertSame(10, $this->bank->balance('100'));
    }

    public function testDepositIntoExistingAccountIncreasesBalance(): void
    {
        $this->bank->deposit('100', 10);

        $this->assertSame(20, $this->bank->deposit('100', 10));
        $this->assertSame(20, $this->bank->balance('100'));
    }

    public function testDepositDoesNotTouchOtherAccounts(): void
    {
        $this->bank->deposit('100', 10);
        $this->bank->deposit('300', 5);

        $this->assertSame(10, $this->bank->balance('100'));
        $this->assertSame(5, $this->bank->balance('300'));
    }

    public function testDepositPersistsAcrossInstances(): void
    {
        $this->bank->deposit('100', 10);

        // Nova instância = nova requisição.
        $this->assertSame(10, (new Bank(new FileStore($this->path)))->balance('100'));
    }

    public function testBalanceIsReadOnly(): void
    {
        $this->bank->deposit('100', 10);

        $this->bank->balance('100');
        $this->bank->balance('100');

        $this->assertSame(['100' => 10], $this->store->read());
    }

    public function testFailedBalanceDoesNotCreateAccount(): void
    {
        try {
            $this->bank->balance('1234');
        } catch (AccountNotFound) {
        }

        $this->assertSame([], $this->store->read());
    }

    public function testDepositRejectsZeroAndNegativeAmountsWithoutChangingState(): void
    {
        $this->bank->deposit('100', 10);

        foreach ([0, -5] as $amount) {
            try {
                $this->bank->deposit('100', $amount);
                $this->fail("Depósito de {$amount} deveria falhar.");
            } catch (InvalidAmount) {
            }
        }

        $this->assertSame(10, $this->bank->balance('100'));
    }

    public function testWithdrawReducesBalance(): void
    {
        $this->bank->deposit('100', 20);

        $this->assertSame(15, $this->bank->withdraw('100', 5));
        $this->assertSame(15, $this->bank->balance('100'));
    }

    public function testWithdrawCanLeaveBalanceAtZero(): void
    {
        $this->bank->deposit('100', 15);

        $this->assertSame(0, $this->bank->withdraw('100', 15));
        $this->assertSame(0, $this->bank->balance('100'));
    }

    public function testWithdrawFromNonExistingAccountThrowsAndDoesNotCreateIt(): void
    {
        try {
            $this->bank->withdraw('200', 10);
            $this->fail('Saque de conta inexistente deveria falhar.');
        } catch (AccountNotFound) {
        }

        $this->assertSame([], $this->store->read());
    }

    public function testWithdrawWithInsufficientFundsKeepsBalance(): void
    {
        $this->bank->deposit('100', 10);

        try {
            $this->bank->withdraw('100', 11);
            $this->fail('Saque acima do saldo deveria falhar.');
        } catch (InsufficientFunds) {
        }

        $this->assertSame(10, $this->bank->balance('100'));
    }

    public function testWithdrawRejectsZeroAndNegativeAmountsWithoutChangingState(): void
    {
        $this->bank->deposit('100', 10);

        foreach ([0, -5] as $amount) {
            try {
                $this->bank->withdraw('100', $amount);
                $this->fail("Saque de {$amount} deveria falhar.");
            } catch (InvalidAmount) {
            }
        }

        $this->assertSame(10, $this->bank->balance('100'));
    }

    public function testTransferMovesMoneyAndCreatesDestination(): void
    {
        $this->bank->deposit('100', 15);

        $result = $this->bank->transfer('100', '300', 15);

        $this->assertSame(['origin' => 0, 'destination' => 15], $result);
        $this->assertSame(0, $this->bank->balance('100'));
        $this->assertSame(15, $this->bank->balance('300'));
    }

    public function testTransferIntoExistingDestinationAddsToItsBalance(): void
    {
        $this->bank->deposit('100', 20);
        $this->bank->deposit('300', 5);

        $this->bank->transfer('100', '300', 8);

        $this->assertSame(12, $this->bank->balance('100'));
        $this->assertSame(13, $this->bank->balance('300'));
    }

    public function testTransferFromNonExistingAccountChangesNothing(): void
    {
        try {
            $this->bank->transfer('200', '300', 15);
            $this->fail('Transferência de conta inexistente deveria falhar.');
        } catch (AccountNotFound) {
        }

        // O destino também não pode ter sido criado.
        $this->assertSame([], $this->store->read());
    }

    public function testTransferWithInsufficientFundsChangesNeitherAccount(): void
    {
        $this->bank->deposit('100', 10);
        $this->bank->deposit('300', 5);

        try {
            $this->bank->transfer('100', '300', 11);
            $this->fail('Transferência acima do saldo deveria falhar.');
        } catch (InsufficientFunds) {
        }

        $this->assertSame(['100' => 10, '300' => 5], $this->store->read());
    }

    public function testTransferToSameAccountIsRejected(): void
    {
        $this->bank->deposit('100', 10);

        try {
            $this->bank->transfer('100', '100', 5);
            $this->fail('Transferência para a mesma conta deveria falhar.');
        } catch (SameAccountTransfer) {
        }

        $this->assertSame(10, $this->bank->balance('100'));
    }

    public function testTransferRejectsZeroAndNegativeAmountsWithoutChangingState(): void
    {
        $this->bank->deposit('100', 10);

        foreach ([0, -5] as $amount) {
            try {
                $this->bank->transfer('100', '300', $amount);
                $this->fail("Transferência de {$amount} deveria falhar.");
            } catch (InvalidAmount) {
            }
        }

        $this->assertSame(['100' => 10], $this->store->read());
    }

    public function testResetRemovesAllAccounts(): void
    {
        $this->bank->deposit('100', 10);
        $this->bank->deposit('300', 5);

        $this->bank->reset();

        $this->assertSame([], $this->store->read());
        $this->expectException(AccountNotFound::class);
        $this->bank->balance('100');
    }

    public function testTotalMoneyIsConservedByTransfers(): void
    {
        $this->bank->deposit('100', 50);
        $this->bank->deposit('200', 30);

        $this->bank->transfer('100', '200', 20);
        $this->bank->transfer('200', '300', 35);
        $this->bank->transfer('300', '100', 10);

        $this->assertSame(80, array_sum($this->store->read()));
    }
}
