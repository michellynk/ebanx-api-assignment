<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Bank;
use App\Http\Api;
use App\Http\Response;
use App\Storage\FileStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testa a API de ponta a ponta (rota -> validação -> Bank -> arquivo -> resposta),
 * sem servidor web. Cada requisição monta uma Api nova, como acontece de verdade
 * no PHP: o único estado compartilhado entre elas é o arquivo.
 */
final class ApiTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ebanx-api-' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /** O script oficial da EBANX, na mesma ordem. */
    public function testEbanxScript(): void
    {
        $this->assertResponse(200, 'OK', $this->post('/reset'));

        $this->assertResponse(404, '0', $this->get('/balance', ['account_id' => '1234']));

        $this->assertJsonResponse(201, '{"destination": {"id":"100", "balance":10}}',
            $this->post('/event', '{"type":"deposit", "destination":"100", "amount":10}'));

        $this->assertJsonResponse(201, '{"destination": {"id":"100", "balance":20}}',
            $this->post('/event', '{"type":"deposit", "destination":"100", "amount":10}'));

        $this->assertResponse(200, '20', $this->get('/balance', ['account_id' => '100']));

        $this->assertResponse(404, '0',
            $this->post('/event', '{"type":"withdraw", "origin":"200", "amount":10}'));

        $this->assertJsonResponse(201, '{"origin": {"id":"100", "balance":15}}',
            $this->post('/event', '{"type":"withdraw", "origin":"100", "amount":5}'));

        $this->assertJsonResponse(201, '{"origin": {"id":"100", "balance":0}, "destination": {"id":"300", "balance":15}}',
            $this->post('/event', '{"type":"transfer", "origin":"100", "amount":15, "destination":"300"}'));

        $this->assertResponse(404, '0',
            $this->post('/event', '{"type":"transfer", "origin":"200", "amount":15, "destination":"300"}'));
    }

    public function testResetClearsExistingAccounts(): void
    {
        $this->post('/event', '{"type":"deposit", "destination":"100", "amount":10}');

        $this->post('/reset');

        $this->assertResponse(404, '0', $this->get('/balance', ['account_id' => '100']));
    }

    public function testGetBalanceDoesNotChangeTheStore(): void
    {
        $this->post('/event', '{"type":"deposit", "destination":"100", "amount":10}');
        $before = file_get_contents($this->path);

        $this->get('/balance', ['account_id' => '100']);
        $this->get('/balance', ['account_id' => '999']);

        $this->assertSame($before, file_get_contents($this->path));
    }

    public function testInsufficientFundsReturns400AndKeepsBalance(): void
    {
        $this->post('/event', '{"type":"deposit", "destination":"100", "amount":10}');

        $this->assertResponse(400, '0',
            $this->post('/event', '{"type":"withdraw", "origin":"100", "amount":11}'));
        $this->assertResponse(400, '0',
            $this->post('/event', '{"type":"transfer", "origin":"100", "amount":11, "destination":"300"}'));

        $this->assertResponse(200, '10', $this->get('/balance', ['account_id' => '100']));
        $this->assertResponse(404, '0', $this->get('/balance', ['account_id' => '300']));
    }

    public function testTransferToSameAccountReturns400(): void
    {
        $this->post('/event', '{"type":"deposit", "destination":"100", "amount":10}');

        $this->assertResponse(400, '0',
            $this->post('/event', '{"type":"transfer", "origin":"100", "amount":5, "destination":"100"}'));
        $this->assertResponse(200, '10', $this->get('/balance', ['account_id' => '100']));
    }

    /** @return array<string, array{string}> */
    public static function invalidEvents(): array
    {
        return [
            'JSON inválido' => ['{"type":'],
            'corpo não é objeto' => ['10'],
            'type desconhecido' => ['{"type":"refund", "destination":"100", "amount":10}'],
            'sem type' => ['{"destination":"100", "amount":10}'],
            'amount decimal' => ['{"type":"deposit", "destination":"100", "amount":10.5}'],
            'amount em texto' => ['{"type":"deposit", "destination":"100", "amount":"10"}'],
            'amount negativo' => ['{"type":"deposit", "destination":"100", "amount":-10}'],
            'amount zero' => ['{"type":"deposit", "destination":"100", "amount":0}'],
            'sem amount' => ['{"type":"deposit", "destination":"100"}'],
            'sem destination' => ['{"type":"deposit", "amount":10}'],
            'destination numérico' => ['{"type":"deposit", "destination":100, "amount":10}'],
            'destination vazio' => ['{"type":"deposit", "destination":"", "amount":10}'],
            'transfer sem destination' => ['{"type":"transfer", "origin":"100", "amount":10}'],
        ];
    }

    #[DataProvider('invalidEvents')]
    public function testInvalidEventReturns400AndChangesNothing(string $body): void
    {
        $this->assertResponse(400, '0', $this->post('/event', $body));
        $this->assertSame([], (new FileStore($this->path))->read());
    }

    public function testBalanceWithoutAccountIdReturns400(): void
    {
        $this->assertResponse(400, '0', $this->get('/balance', []));
    }

    public function testUnknownRouteReturns404(): void
    {
        $this->assertResponse(404, '0', $this->get('/accounts', []));
        $this->assertResponse(404, '0', $this->request('GET', '/event', [], ''));
    }

    private function get(string $path, array $query): Response
    {
        return $this->request('GET', $path, $query, '');
    }

    private function post(string $path, string $body = ''): Response
    {
        return $this->request('POST', $path, [], $body);
    }

    private function request(string $method, string $path, array $query, string $body): Response
    {
        $api = new Api(new Bank(new FileStore($this->path)));

        return $api->handle($method, $path, $query, $body);
    }

    private function assertResponse(int $status, string $body, Response $response): void
    {
        $this->assertSame($status, $response->status);
        $this->assertSame($body, $response->body);
    }

    private function assertJsonResponse(int $status, string $json, Response $response): void
    {
        $this->assertSame($status, $response->status);
        $this->assertSame('application/json', $response->contentType);
        $this->assertJsonStringEqualsJsonString($json, $response->body);
    }
}
