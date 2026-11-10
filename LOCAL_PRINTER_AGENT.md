# Agente Local de Impressao

Este e o modo recomendado de impressao. Substitui o microservico Node + ngrok (`printer-microservice/`).

O servidor hospedado nunca chama o restaurante. A maquina do restaurante e que consulta o servidor de poucos em poucos segundos, imprime os pedidos pendentes na impressora da rede local e confirma o resultado. Por isso nao precisa de ngrok, IP publico nem porta aberta no roteador.

## Como funciona

1. Quando um pedido precisa ser impresso, o servidor cria um `LocalPrintJob` com status `pending`.
2. O agente consulta `GET /api/local-printer/jobs`. O servidor **reserva** os jobs para ele (`printing`), entao nenhum outro agente ou consulta recebe o mesmo cupom.
3. O agente imprime e confirma (`complete`) ou informa o erro (`fail`).
4. Se a confirmacao nao chegar ao servidor (internet caiu), o agente reenvia no proximo ciclo.
5. Se o agente cair no meio da impressao, o job volta para a fila depois de `LOCAL_PRINT_CLAIM_TIMEOUT_SECONDS`.
6. Se falhar, o job e tentado de novo a cada `LOCAL_PRINT_RETRY_AFTER_SECONDS`, ate `LOCAL_PRINT_MAX_ATTEMPTS` vezes. Depois disso aparece no painel como **Falhou**, com botao **Tentar de novo**.
7. Cada consulta conta como sinal de vida. O painel mostra **Impressora online/offline** no topo de todas as paginas.

Nada se perde: se o computador do restaurante estiver desligado, os pedidos esperam na fila e saem quando ele voltar.

## Configuracao no servidor hospedado

No `.env` do servidor:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=local
LOCAL_PRINT_API_TOKEN=uma-chave-secreta-forte
LOCAL_PRINT_MAX_ATTEMPTS=10
LOCAL_PRINT_RETRY_AFTER_SECONDS=30
LOCAL_PRINT_CLAIM_TIMEOUT_SECONDS=120
LOCAL_PRINT_OFFLINE_AFTER_SECONDS=60
```

Se `print_connection` tambem estiver salvo na tabela `settings`, ele tem prioridade sobre o `.env` (a menos que `PRINT_PREFER_ENV=true`). Deixe os dois iguais.

Depois:

```bash
php artisan migrate
php artisan config:clear
```

Gere o token com algo como `php -r "echo bin2hex(random_bytes(32));"`.

## Configuracao na maquina do restaurante

A maquina precisa de PHP e de uma copia do projeto (com `composer install`). Ela deve estar na mesma rede da impressora.

`.env` da maquina do restaurante:

```env
# Impressora de rede (recomendado)
PRINT_ENABLED=true
PRINT_CONNECTION=network
PRINT_HOST=192.168.1.101
PRINT_PORT=9100
PRINT_STORE_NAME="Restaurante do Dimas"

# Ou impressora compartilhada no Windows
# PRINT_CONNECTION=windows
# PRINT_WINDOWS_CONNECTOR="POS80"

# Acesso ao servidor hospedado
LOCAL_PRINT_API_URL=https://seu-servidor.com
LOCAL_PRINT_API_TOKEN=mesma-chave-do-servidor
LOCAL_PRINT_POLL_INTERVAL=5
LOCAL_PRINT_AGENT_NAME=Caixa
```

O agente sempre usa a impressora definida neste `.env`. A tabela `settings` nao e consultada.

### 1. Teste a impressora

```bash
php scripts/test_local_printer.php
```

### 2. Instale o agente para iniciar com o Windows

Execute uma vez (duplo clique):

```
scripts\windows\instalar-agente-impressao.bat
```

Isso cria a tarefa agendada **"Dimas - Agente de Impressao"**. Ela inicia o agente em segundo plano sempre que o usuario entra no Windows e o reinicia sozinho se ele parar. Configure o Windows para entrar automaticamente nesse usuario, para que o agente volte depois de uma queda de energia.

- Log: `storage\logs\agente-impressao.log` (rotaciona em ~5 MB)
- Se o `php` nao estiver no PATH, edite `PHP_BIN` em `scripts\windows\agente-impressao.bat`
- Para remover: `scripts\windows\remover-agente-impressao.bat`
- So roda um agente por maquina. Uma segunda copia encerra sozinha.

Para rodar manualmente no terminal (diagnostico):

```bash
php scripts/local_printer_agent.php
```

### 3. Fixe o IP da impressora

No roteador, crie uma **reserva DHCP** para o endereco MAC da impressora, ou configure um IP fixo nela. Sem isso, o IP pode mudar depois que o roteador reinicia, e o agente passa a falhar com "Connection refused/timed out".

## No painel

- **Topo de todas as paginas:** "Impressora online" (verde) ou "offline ha X" (vermelho), quantos cupons estao na fila e quantos falharam.
- **Menu Impressao:** historico das ultimas impressoes, erro de cada falha e botoes **Tentar de novo** / **Reimprimir**.
- **Pagina do pedido:** botao **Reimprimir na Impressora**.

## Diagnostico rapido

| Painel mostra | Causa provavel |
|---|---|
| Impressora nunca conectou | Agente nao instalado, ou `LOCAL_PRINT_API_URL`/token errados (o log mostra `401`) |
| Offline | PC do restaurante desligado, sem internet, ou agente parado |
| Online, mas cupons com "Falhou" | Impressora desligada, sem papel, ou IP mudou (veja o erro na lista) |
| Pedidos nem aparecem na lista | `PRINT_CONNECTION` do servidor nao esta `local`, ou `PRINT_ENABLED=false` |
