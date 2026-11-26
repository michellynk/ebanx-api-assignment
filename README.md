# EBANX — Take-home assignment

API de contas com três endpoints (`POST /reset`, `GET /balance`, `POST /event`), escrita em PHP puro.
O foco é lógica de negócio correta, estado consistente e código simples.

## Requisitos

- PHP 8.3+
- Composer

## Como rodar

```bash
composer install
php -S 127.0.0.1:8000 public/index.php
```

A API fica disponível em `http://127.0.0.1:8000`. Os saldos são gravados em `storage/accounts.json`
(criado na primeira operação; ignorado pelo git).

## Como rodar os testes

```bash
composer test
```

- `tests/Unit`: regras do `Bank` e do `FileStore`, sempre conferindo o estado gravado depois de cada operação.
- `tests/Feature`: a API de ponta a ponta, incluindo o script oficial da EBANX e os casos de erro.

Os testes usam o armazenamento real em um arquivo temporário. Não há mocks.

## Endpoints

| Requisição | Sucesso | Erros |
|---|---|---|
| `POST /reset` | `200 OK` | — |
| `GET /balance?account_id={id}` | `200 {saldo}` | `404 0` conta inexistente · `400 0` sem `account_id` |
| `POST /event` `{"type":"deposit","destination":"100","amount":10}` | `201 {"destination":{"id","balance"}}` | `400 0` entrada inválida |
| `POST /event` `{"type":"withdraw","origin":"100","amount":5}` | `201 {"origin":{"id","balance"}}` | `404 0` conta inexistente · `400 0` saldo insuficiente |
| `POST /event` `{"type":"transfer","origin":"100","amount":15,"destination":"300"}` | `201 {"origin":{...},"destination":{...}}` | `404 0` origem inexistente · `400 0` saldo insuficiente ou mesma conta |

**Padrão de resposta:** em caso de sucesso, o corpo traz o dado. Em caso de erro, o corpo é sempre `0`
e o status indica o motivo (`404` recurso inexistente, `400` entrada ou regra inválida, `500` falha inesperada).

## Testando com o testador da EBANX

O testador da EBANX roda na internet, então precisa de uma **URL pública** que chegue até a sua máquina.
Com o servidor rodando, abra um túnel em outro terminal (exemplo com [ngrok](https://ngrok.com)):

```bash
ngrok http 127.0.0.1:8000
```

Copie a URL `https://...` exibida em `Forwarding` e informe-a no testador, **sem barra final e sem caminho**
(ex.: `https://xxxx.ngrok-free.app`).

### Solução de problemas: `ECONNREFUSED`

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

## Estrutura

```
public/index.php        ponto de entrada: monta as dependências e envia a resposta
src/Bank.php            regras de negócio (deposit, withdraw, transfer, balance, reset)
src/Exceptions/         erros de domínio (conta inexistente, saldo insuficiente, ...)
src/Storage/            leitura e gravação atômica do estado
src/Http/               rotas, validação de formato e respostas
tests/Unit, tests/Feature
```

## Decisões

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

### Regras não definidas na especificação

- `amount` precisa ser um inteiro maior que zero. Decimais, textos, zero e negativos retornam `400 0`.
- Saldo insuficiente em saque ou transferência retorna `400 0`, e os saldos não mudam.
- Transferência para a própria conta retorna `400 0`.
- Depósito ou transferência para uma conta inexistente cria a conta de destino. Saque ou transferência
  de uma origem inexistente retorna `404 0`, sem criar nada.
