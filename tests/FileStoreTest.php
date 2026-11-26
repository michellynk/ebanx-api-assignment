<?php

declare(strict_types=1);

namespace Tests;

use App\FileStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Testa o store com um arquivo de verdade (sem mocks): o que importa é o que
 * fica gravado no disco entre uma operação e outra.
 */
final class FileStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ebanx-store-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testReadWithoutFileReturnsEmptyAndDoesNotCreateIt(): void
    {
        $store = new FileStore($this->path);

        $this->assertSame([], $store->read());
        $this->assertFileDoesNotExist($this->path);
    }

    public function testTransactionPersistsChangesForANewInstance(): void
    {
        (new FileStore($this->path))->transaction(function (array &$accounts): void {
            $accounts['100'] = 20;
        });

        // Uma nova instância simula uma nova requisição HTTP.
        $this->assertSame(['100' => 20], (new FileStore($this->path))->read());
    }

    public function testTransactionReturnsTheOperationResult(): void
    {
        $result = (new FileStore($this->path))->transaction(fn (array &$accounts): string => 'ok');

        $this->assertSame('ok', $result);
    }

    public function testExceptionInsideTransactionKeepsPreviousState(): void
    {
        $store = new FileStore($this->path);
        $store->transaction(function (array &$accounts): void {
            $accounts['100'] = 20;
        });

        try {
            $store->transaction(function (array &$accounts): void {
                $accounts['100'] = 0;
                $accounts['300'] = 20;
                throw new RuntimeException('falhou no meio');
            });
            $this->fail('A exceção deveria ter sido propagada.');
        } catch (RuntimeException $e) {
            $this->assertSame('falhou no meio', $e->getMessage());
        }

        $this->assertSame(['100' => 20], $store->read());
    }

    public function testReadDoesNotChangeTheFile(): void
    {
        $store = new FileStore($this->path);
        $store->transaction(function (array &$accounts): void {
            $accounts['100'] = 20;
        });
        $before = file_get_contents($this->path);

        $store->read();
        $store->read();

        $this->assertSame($before, file_get_contents($this->path));
    }
}
