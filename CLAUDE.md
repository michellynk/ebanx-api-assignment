# EBANX API assignment — regras do projeto

- (2026-11-26) Claude nunca faz commit, push ou cria branch neste projeto. Toda alteração fica no working tree para eu inspecionar, testar na minha máquina e commitar pelo GitHub Desktop. Ao terminar cada passo, Claude entrega: o que mudou, como testar e a mensagem de commit sugerida.
- Stack: PHP puro (8.2+) + PHPUnit como única dependência (dev). Estado em arquivo JSON protegido por `flock`.
- Decisões de regra: `amount` só inteiro > 0 (senão 400); saldo insuficiente → `400 0`; transferência para a própria conta → 400; destino inexistente em depósito/transferência é criado.
