<?php

declare(strict_types=1);

namespace App\Http;

/** Status + corpo. É o que a Api devolve e o que os testes inspecionam. */
final class Response
{
    private function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $contentType,
    ) {
    }

    public static function text(int $status, string $body): self
    {
        return new self($status, $body, 'text/plain; charset=utf-8');
    }

    /** @param array<string, mixed> $data */
    public static function json(int $status, array $data): self
    {
        return new self($status, json_encode($data, JSON_THROW_ON_ERROR), 'application/json');
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: ' . $this->contentType);
        echo $this->body;
    }
}
