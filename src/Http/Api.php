<?php

declare(strict_types=1);

namespace App\Http;

use App\Bank;
use App\Exceptions\AccountNotFound;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\InvalidAmount;
use App\Exceptions\SameAccountTransfer;

/**
 * Camada HTTP: escolhe a rota, valida o formato da entrada, chama o Bank e
 * traduz o resultado (ou a exceção) em Response. Não contém regra de negócio.
 *
 * Padrão de resposta: sucesso devolve o dado; qualquer erro devolve corpo "0"
 * com o status que explica o motivo (404 conta inexistente, 400 entrada/regra).
 */
final class Api
{
    public function __construct(private readonly Bank $bank)
    {
    }

    /** @param array<string, mixed> $query */
    public function handle(string $method, string $path, array $query, string $body): Response
    {
        try {
            return match ("{$method} {$path}") {
                'POST /reset' => $this->reset(),
                'GET /balance' => $this->balance($query),
                'POST /event' => $this->event($body),
                default => Response::text(404, '0'),
            };
        } catch (AccountNotFound) {
            return Response::text(404, '0');
        } catch (InvalidRequest | InvalidAmount | InsufficientFunds | SameAccountTransfer) {
            return Response::text(400, '0');
        }
    }

    private function reset(): Response
    {
        $this->bank->reset();

        return Response::text(200, 'OK');
    }

    /** @param array<string, mixed> $query */
    private function balance(array $query): Response
    {
        $balance = $this->bank->balance($this->accountId($query, 'account_id'));

        return Response::text(200, (string) $balance);
    }

    private function event(string $body): Response
    {
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidRequest('O corpo deve ser um objeto JSON.');
        }

        return match ($data['type'] ?? null) {
            'deposit' => $this->deposit($data),
            'withdraw' => $this->withdraw($data),
            'transfer' => $this->transfer($data),
            default => throw new InvalidRequest('type deve ser deposit, withdraw ou transfer.'),
        };
    }

    /** @param array<string, mixed> $data */
    private function deposit(array $data): Response
    {
        $destination = $this->accountId($data, 'destination');
        $balance = $this->bank->deposit($destination, $this->amount($data));

        return Response::json(201, [
            'destination' => ['id' => $destination, 'balance' => $balance],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function withdraw(array $data): Response
    {
        $origin = $this->accountId($data, 'origin');
        $balance = $this->bank->withdraw($origin, $this->amount($data));

        return Response::json(201, [
            'origin' => ['id' => $origin, 'balance' => $balance],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function transfer(array $data): Response
    {
        $origin = $this->accountId($data, 'origin');
        $destination = $this->accountId($data, 'destination');
        $balances = $this->bank->transfer($origin, $destination, $this->amount($data));

        return Response::json(201, [
            'origin' => ['id' => $origin, 'balance' => $balances['origin']],
            'destination' => ['id' => $destination, 'balance' => $balances['destination']],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function accountId(array $data, string $field): string
    {
        $id = $data[$field] ?? null;

        if (!is_string($id) || $id === '') {
            throw new InvalidRequest("{$field} deve ser um texto não vazio.");
        }

        return $id;
    }

    /**
     * Só aceita inteiro de verdade: 10.5, "10" e null são recusados.
     * Se o valor é positivo, quem decide é o Bank (regra de negócio).
     *
     * @param array<string, mixed> $data
     */
    private function amount(array $data): int
    {
        $amount = $data['amount'] ?? null;

        if (!is_int($amount)) {
            throw new InvalidRequest('amount deve ser um número inteiro.');
        }

        return $amount;
    }
}
