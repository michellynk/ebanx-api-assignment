<?php

declare(strict_types=1);

use App\Bank;
use App\Http\Api;
use App\Http\Response;
use App\Storage\FileStore;

require __DIR__ . '/../vendor/autoload.php';

// Ponto de entrada: monta as dependências, entrega a requisição para a Api e envia a resposta.
try {
    $api = new Api(new Bank(new FileStore(__DIR__ . '/../storage/accounts.json')));

    $response = $api->handle(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
        $_GET,
        (string) file_get_contents('php://input'),
    );
} catch (Throwable $e) {
    // Erro inesperado (ex.: sem permissão no arquivo): registra no log e responde no mesmo padrão.
    error_log((string) $e);
    $response = Response::text(500, '0');
}

$response->send();
