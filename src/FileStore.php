<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Guarda os saldos das contas em um arquivo JSON: {"100": 20, "300": 15}.
 *
 * O PHP não mantém memória entre requisições, então o estado precisa viver fora
 * do processo. O flock garante que só uma requisição por vez faça o ciclo
 * "ler -> alterar -> gravar", e isso torna cada operação atômica.
 */
final class FileStore
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Leitura pura: trava compartilhada (várias leituras ao mesmo tempo),
     * nunca grava e nem cria o arquivo. É o que o GET /balance vai usar.
     *
     * @return array<string, int>
     */
    public function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $handle = $this->open('r');

        try {
            flock($handle, LOCK_SH);

            return $this->decode(stream_get_contents($handle));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Executa $operation com a trava exclusiva e grava o resultado.
     *
     * $operation recebe os saldos por referência e pode alterá-los.
     * Se ela lançar uma exceção, nada é gravado: o arquivo fica como estava.
     *
     * @template T
     * @param callable(array<string, int> &$accounts): T $operation
     * @return T
     */
    public function transaction(callable $operation): mixed
    {
        $handle = $this->open('c+'); // cria se não existir, sem apagar o conteúdo

        try {
            flock($handle, LOCK_EX);

            $accounts = $this->decode(stream_get_contents($handle));
            $result = $operation($accounts);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($accounts, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR));
            fflush($handle);

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource */
    private function open(string $mode)
    {
        $handle = fopen($this->path, $mode);

        if ($handle === false) {
            throw new RuntimeException("Não foi possível abrir o arquivo de dados: {$this->path}");
        }

        return $handle;
    }

    /** @return array<string, int> */
    private function decode(string|false $contents): array
    {
        if ($contents === false || $contents === '') {
            return [];
        }

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
