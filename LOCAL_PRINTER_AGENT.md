# Agente Local de Impressão

Este serviço local roda no restaurante e imprime pedidos que o servidor hospedado envia para a fila de impressão.

## Como funciona

1. O servidor hospedado grava um `LocalPrintJob` para cada pedido que deve ser impresso localmente.
2. O agente local consulta o servidor periodicamente via API.
3. O agente recebe os pedidos pendentes, imprime na impressora local e confirma a ocorrência ao servidor.

## Instalação

1. Copie o repositório para a máquina local do restaurante.
2. Configure a impressão local no `.env` do projeto local, por exemplo:

```env
PRINT_CONNECTION=windows
PRINT_WINDOWS_CONNECTOR=\\\\COMPUTER\\PRINTER_NAME
```

ou, para rede:

```env
PRINT_CONNECTION=network
PRINT_HOST=192.168.0.100
PRINT_PORT=9100
```

3. Configure a API remota no `.env` local:

```env
LOCAL_PRINT_API_URL=https://seu-servidor-hosted.com
LOCAL_PRINT_API_TOKEN=uma-chave-secreta-fornecida
LOCAL_PRINT_POLL_INTERVAL=10
```

4. Execute o agente local:

```bash
php scripts/local_printer_agent.php
```

## Configuração no servidor hospedado

No servidor hospedado, defina:

```env
PRINT_CONNECTION=local
```

E crie a variável secreta `LOCAL_PRINT_API_TOKEN` com um valor compartilhado entre o servidor e o agente local.

## Observações

- O servidor precisa estar acessível pela máquina local do restaurante.
- A impressora local não precisa ser exposta diretamente na internet.
- O agente local deve ser executado na rede onde a impressora está conectada.
