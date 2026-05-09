# Agente Local de Impressao

Este servico roda na maquina do restaurante e imprime pedidos que o servidor hospedado deixa em uma fila.

Essa abordagem e mais indicada do que expor um microservico local por tunel: o agente faz uma conexao de saida para o servidor hospedado, entao a impressora continua protegida dentro da rede local do restaurante.

## Como funciona

1. O servidor hospedado cria um `LocalPrintJob` quando um pedido precisa ser impresso.
2. O agente local consulta o servidor periodicamente via API.
3. O agente baixa os pedidos pendentes, imprime na impressora local e confirma o resultado ao servidor.
4. Se a impressao falhar, o job entra em retry automatico conforme `LOCAL_PRINT_MAX_ATTEMPTS` e `LOCAL_PRINT_RETRY_AFTER_SECONDS`.

## Configuracao no servidor hospedado

No `.env` do servidor hospedado:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=local
LOCAL_PRINT_API_TOKEN=uma-chave-secreta-forte
LOCAL_PRINT_MAX_ATTEMPTS=10
LOCAL_PRINT_RETRY_AFTER_SECONDS=30
```

Depois rode as migrations no servidor hospedado:

```bash
php artisan migrate
```

Se a fila nao estiver em modo `sync`, mantenha o worker do Laravel rodando:

```bash
php artisan queue:work
```

## Configuracao na maquina do restaurante

Copie o projeto para a maquina que esta na mesma rede da impressora e configure o `.env` local.

Para impressora de rede:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=network
PRINT_HOST=192.168.0.100
PRINT_PORT=9100
PRINT_STORE_NAME="Restaurante do Dimas"
```

Para impressora compartilhada no Windows:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=windows
PRINT_WINDOWS_CONNECTOR=POS80
PRINT_STORE_NAME="Restaurante do Dimas"
```

Configure tambem o acesso ao servidor hospedado:

```env
LOCAL_PRINT_API_URL=https://seu-servidor.com
LOCAL_PRINT_API_TOKEN=mesma-chave-secreta-do-servidor
LOCAL_PRINT_POLL_INTERVAL=10
```

Execute o agente local:

```bash
php scripts/local_printer_agent.php
```

Tambem e possivel passar os parametros direto:

```bash
php scripts/local_printer_agent.php https://seu-servidor.com mesma-chave-secreta-do-servidor 10
```

## Testes rapidos

No servidor hospedado, confirme que existe a tabela:

```bash
php artisan migrate:status
```

Na maquina do restaurante, teste a impressora local diretamente:

```bash
php scripts/test_local_printer.php
```

Depois deixe o agente rodando e crie ou aceite um pedido no sistema hospedado. O terminal do agente deve mostrar o job sendo baixado e impresso.

## Observacoes

- A impressora nao precisa ser exposta na internet.
- O agente deve rodar na mesma rede da impressora.
- O `.env` local da maquina do restaurante define a impressora usada pelo agente.
- O token do servidor e o token do agente precisam ser iguais.
