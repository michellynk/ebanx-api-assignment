# EBANX — Take-home assignment

API com `GET /balance`, `POST /event` e `POST /reset`, em PHP puro.

> Em construção: este README é completado a cada passo.

## Requisitos

- PHP 8.3+
- Composer

## Como rodar os testes

```bash
composer install
composer test
```

## Decisões

- **Sem framework.** São três rotas; um framework traria muito mais código do que o problema pede.
- **Estado em arquivo JSON com `flock`.** O PHP não guarda memória entre requisições, então o
  estado precisa ficar fora do processo. A trava exclusiva do arquivo faz cada operação
  (ler → alterar → gravar) ser atômica; se algo falhar no meio, nada é gravado.
  Leituras usam trava compartilhada e nunca escrevem.
