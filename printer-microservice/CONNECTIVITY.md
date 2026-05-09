# Conectividade da impressora local

O Laravel hospedado nao consegue acessar diretamente uma impressora que esta na rede local do restaurante. Existem duas formas de resolver isso:

## Opcao recomendada: agente local sem tunel

Use o agente PHP da raiz do projeto:

```bash
php scripts/local_printer_agent.php
```

Nesse modelo, a maquina do restaurante consulta o servidor hospedado de tempos em tempos e imprime os pedidos pendentes. A conexao sai da rede do restaurante para o servidor, entao nao precisa expor porta local, ngrok, IP publico nem redirecionamento no roteador.

Configure no `.env` do servidor hospedado:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=local
LOCAL_PRINT_API_TOKEN=uma-chave-secreta-forte
```

Configure no `.env` da raiz do projeto na maquina do restaurante:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=network
PRINT_HOST=192.168.0.100
PRINT_PORT=9100

LOCAL_PRINT_API_URL=https://seu-servidor.com
LOCAL_PRINT_API_TOKEN=mesma-chave-secreta-forte
LOCAL_PRINT_POLL_INTERVAL=10
```

Teste a impressora local:

```bash
php scripts/test_local_printer.php
```

Depois inicie o agente:

```bash
php scripts/local_printer_agent.php
```

## Opcao alternativa: microservico exposto por tunel

Use esta opcao apenas se voce realmente quiser que o servidor hospedado chame um endpoint HTTP rodando no computador do restaurante.

### Erro ERR_NGROK_4018

O erro abaixo significa que o ngrok esta instalado, mas nao autenticado:

```text
authentication failed: Usage of ngrok requires a verified account and authtoken.
ERR_NGROK_4018
```

Para continuar com ngrok:

1. Crie/verifique uma conta no ngrok.
2. Copie o authtoken no painel do ngrok.
3. Rode na maquina do restaurante:

```bash
ngrok config add-authtoken SEU_AUTHTOKEN
```

4. Inicie novamente:

```bash
npm run start-tunnel
```

O script deve mostrar uma URL parecida com:

```text
PRINT_MICROSERVICE_URL=https://abc123.ngrok-free.app/print
```

No Laravel hospedado, configure:

```env
PRINT_ENABLED=true
PRINT_CONNECTION=microservice
PRINT_MICROSERVICE_URL=https://abc123.ngrok-free.app/print
PRINT_MICROSERVICE_TOKEN=mesmo-token-do-microservico
```

No `.env` de `printer-microservice`, o token esperado pelo servidor Node e:

```env
LOCAL_PRINT_API_TOKEN=mesmo-token-do-microservico
```

## Observacoes

- Para producao em restaurante, prefira o agente local sem tunel.
- URLs gratuitas do ngrok podem mudar ao reiniciar, exigindo atualizar `PRINT_MICROSERVICE_URL` no servidor.
- Se um token foi compartilhado em conversa, log ou print, gere outro antes de usar em producao.
