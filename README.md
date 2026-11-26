# EBANX — Take-home assignment

[Português](#português) · [English](#english)

## Português

API de contas com três endpoints (`POST /reset`, `GET /balance`, `POST /event`), escrita em PHP puro.
O foco é lógica de negócio correta, estado consistente e código simples.

### Requisitos

- PHP 8.3+
- Composer

### Como rodar

```bash
composer install
php -S 127.0.0.1:8000 public/index.php
```

A API fica disponível em `http://127.0.0.1:8000`. Os saldos são gravados em `storage/accounts.json`
(criado na primeira operação; ignorado pelo git).

### Como rodar os testes

```bash
composer test
```

- `tests/Unit`: regras do `Bank` e do `FileStore`, sempre conferindo o estado gravado depois de cada operação.
- `tests/Feature`: a API de ponta a ponta, incluindo o script oficial da EBANX e os casos de erro.

Os testes usam o armazenamento real em um arquivo temporário. Não há mocks.

### Endpoints

| Requisição | Sucesso | Erros |
|---|---|---|
| `POST /reset` | `200 OK` | — |
| `GET /balance?account_id={id}` | `200 {saldo}` | `404 0` conta inexistente · `400 0` sem `account_id` |
| `POST /event` `{"type":"deposit","destination":"100","amount":10}` | `201 {"destination":{"id","balance"}}` | `400 0` entrada inválida |
| `POST /event` `{"type":"withdraw","origin":"100","amount":5}` | `201 {"origin":{"id","balance"}}` | `404 0` conta inexistente · `400 0` saldo insuficiente |
| `POST /event` `{"type":"transfer","origin":"100","amount":15,"destination":"300"}` | `201 {"origin":{...},"destination":{...}}` | `404 0` origem inexistente · `400 0` saldo insuficiente ou mesma conta |

**Padrão de resposta:** em caso de sucesso, o corpo traz o dado. Em caso de erro, o corpo é sempre `0`
e o status indica o motivo (`404` recurso inexistente, `400` entrada ou regra inválida, `500` falha inesperada).

### Testando com o testador da EBANX

O testador da EBANX roda na internet, então precisa de uma **URL pública** que chegue até a sua máquina.
Com o servidor rodando, abra um túnel em outro terminal (exemplo com [ngrok](https://ngrok.com)):

```bash
ngrok http 127.0.0.1:8000
```

Copie a URL `https://...` exibida em `Forwarding` e informe-a no testador, **sem barra final e sem caminho**
(ex.: `https://xxxx.ngrok-free.app`).

#### Solução de problemas: `ECONNREFUSED`

**O que significa:** o testador tentou abrir uma conexão e nenhum processo estava escutando no endereço
informado. A requisição não chegou à API. Por isso este não é um erro da aplicação, e sim de rede.

**Por que acontece:** `localhost` e `127.0.0.1` sempre apontam para a própria máquina de quem faz a requisição.
Quando o testador da EBANX recebe `http://localhost:8000`, ele procura a porta 8000 **no servidor dele**, não no seu.

**Como resolver:**

1. Confirme que a API está rodando localmente: `http://127.0.0.1:8000/balance?account_id=1234` deve responder `0`.
2. Abra o túnel com `ngrok http 127.0.0.1:8000`, mantendo o servidor PHP rodando em outro terminal.
   Use `127.0.0.1` em vez de `localhost`: assim o ngrok não tenta o endereço IPv6 (`::1`), no qual o `php -S` não está escutando.
3. Informe no testador a URL pública `https://...` do ngrok, e não o endereço local.
4. Para inspecionar o que o testador enviou e o que a API respondeu, use o painel do ngrok em `http://127.0.0.1:4040`.

> No plano gratuito do ngrok, o **navegador** exibe uma página de aviso antes de mostrar a resposta.
> Clientes HTTP como o testador não passam por essa página.

### Estrutura

```
public/index.php        ponto de entrada: monta as dependências e envia a resposta
src/Bank.php            regras de negócio (deposit, withdraw, transfer, balance, reset)
src/Exceptions/         erros de domínio (conta inexistente, saldo insuficiente, ...)
src/Storage/            leitura e gravação atômica do estado
src/Http/               rotas, validação de formato e respostas
tests/Unit, tests/Feature
```

### Decisões

- **Sem framework.** São três rotas. Um framework traria muito mais código e configuração do que o problema pede.
- **Estado em arquivo JSON com `flock`.** O PHP não mantém memória entre requisições, então o estado precisa ficar
  fora do processo. Cada operação roda inteira sob trava exclusiva (ler → alterar → gravar). Se uma regra falhar
  no meio, nada é gravado. Isso torna a transferência atômica: débito e crédito são gravados juntos ou nenhum dos dois.
  Leituras (`GET /balance`) usam trava compartilhada e nunca escrevem.
- **Regra separada do HTTP.** O `Bank` recebe valores já tipados, devolve saldos e sinaliza erros com exceções de domínio.
  A camada `Http` só valida formato e traduz exceções em status, num único ponto.
- **Validação em duas camadas.** O HTTP garante o *formato* (`amount` precisa ser um inteiro JSON, os ids precisam ser texto).
  O `Bank` garante a *regra* (valor maior que zero, saldo suficiente, origem diferente do destino).
- **Sem interfaces ou containers.** Existe uma única implementação de armazenamento. Uma abstração seria criada
  só quando surgisse uma segunda.

#### Regras não definidas na especificação

- `amount` precisa ser um inteiro maior que zero. Decimais, textos, zero e negativos retornam `400 0`.
- Saldo insuficiente em saque ou transferência retorna `400 0`, e os saldos não mudam.
- Transferência para a própria conta retorna `400 0`.
- Depósito ou transferência para uma conta inexistente cria a conta de destino. Saque ou transferência
  de uma origem inexistente retorna `404 0`, sem criar nada.

---

## English

Account API with three endpoints (`POST /reset`, `GET /balance`, `POST /event`), written in plain PHP.
The focus is correct business logic, consistent state and simple code.

### Requirements

- PHP 8.3+
- Composer

### How to run

```bash
composer install
php -S 127.0.0.1:8000 public/index.php
```

The API is available at `http://127.0.0.1:8000`. Balances are stored in `storage/accounts.json`
(created on the first operation; ignored by git).

### How to run the tests

```bash
composer test
```

- `tests/Unit`: rules of `Bank` and `FileStore`, always checking the stored state after each operation.
- `tests/Feature`: the API end to end, including the official EBANX script and the error cases.

Tests use the real storage on a temporary file. There are no mocks.

### Endpoints

| Request | Success | Errors |
|---|---|---|
| `POST /reset` | `200 OK` | — |
| `GET /balance?account_id={id}` | `200 {balance}` | `404 0` non-existing account · `400 0` missing `account_id` |
| `POST /event` `{"type":"deposit","destination":"100","amount":10}` | `201 {"destination":{"id","balance"}}` | `400 0` invalid input |
| `POST /event` `{"type":"withdraw","origin":"100","amount":5}` | `201 {"origin":{"id","balance"}}` | `404 0` non-existing account · `400 0` insufficient funds |
| `POST /event` `{"type":"transfer","origin":"100","amount":15,"destination":"300"}` | `201 {"origin":{...},"destination":{...}}` | `404 0` non-existing origin · `400 0` insufficient funds or same account |

**Response pattern:** on success, the body carries the data. On error, the body is always `0`
and the status tells the reason (`404` missing resource, `400` invalid input or rule, `500` unexpected failure).

### Testing with the EBANX test tool

The EBANX test tool runs on the internet, so it needs a **public URL** that reaches your machine.
With the server running, open a tunnel in another terminal (example with [ngrok](https://ngrok.com)):

```bash
ngrok http 127.0.0.1:8000
```

Copy the `https://...` URL shown under `Forwarding` and paste it into the test tool, **with no trailing slash and no path**
(e.g. `https://xxxx.ngrok-free.app`).

#### Troubleshooting: `ECONNREFUSED`

**What it means:** the test tool tried to open a connection and no process was listening at the given address.
The request never reached the API, so this is a network issue, not an application error.

**Why it happens:** `localhost` and `127.0.0.1` always point to the machine making the request.
When the EBANX test tool receives `http://localhost:8000`, it looks for port 8000 **on its own server**, not on yours.

**How to fix it:**

1. Make sure the API is running locally: `http://127.0.0.1:8000/balance?account_id=1234` should answer `0`.
2. Open the tunnel with `ngrok http 127.0.0.1:8000`, keeping the PHP server running in another terminal.
   Use `127.0.0.1` instead of `localhost`, so ngrok does not try the IPv6 address (`::1`), where `php -S` is not listening.
3. Give the test tool the public `https://...` ngrok URL, not the local address.
4. To inspect what the test tool sent and what the API answered, use the ngrok dashboard at `http://127.0.0.1:4040`.

> On ngrok's free plan, a **browser** shows a warning page before the response.
> HTTP clients such as the test tool skip that page.

### Structure

```
public/index.php        entry point: wires dependencies and sends the response
src/Bank.php            business rules (deposit, withdraw, transfer, balance, reset)
src/Exceptions/         domain errors (account not found, insufficient funds, ...)
src/Storage/            atomic read and write of the state
src/Http/               routes, format validation and responses
tests/Unit, tests/Feature
```

### Decisions

- **No framework.** There are three routes. A framework would bring far more code and configuration than the problem needs.
- **State in a JSON file with `flock`.** PHP keeps no memory between requests, so the state has to live outside
  the process. Each operation runs entirely under an exclusive lock (read → change → write). If a rule fails
  halfway, nothing is written. This makes the transfer atomic: debit and credit are written together or not at all.
  Reads (`GET /balance`) use a shared lock and never write.
- **Rules separated from HTTP.** `Bank` receives already-typed values, returns balances and signals errors with domain exceptions.
  The `Http` layer only validates format and maps exceptions to status codes, in a single place.
- **Validation in two layers.** HTTP ensures the *format* (`amount` must be a JSON integer, ids must be strings).
  `Bank` ensures the *rules* (amount greater than zero, enough funds, origin different from destination).
- **No interfaces or containers.** There is a single storage implementation. An abstraction would be added
  only when a second one appears.

#### Rules not defined in the specification

- `amount` must be an integer greater than zero. Decimals, strings, zero and negatives return `400 0`.
- Insufficient funds on withdraw or transfer returns `400 0`, and balances do not change.
- A transfer to the same account returns `400 0`.
- A deposit or transfer to a non-existing account creates the destination account. A withdraw or transfer
  from a non-existing origin returns `404 0` and creates nothing.
