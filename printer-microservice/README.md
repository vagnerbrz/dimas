# Printer Microservice

Um micro servidor Node.js separado que recebe requisições HTTP do host principal e envia comandos para a impressora local.

## Como usar

1. Copie a pasta `printer-microservice` para a máquina local do restaurante.
2. Instale dependências:

```bash
cd printer-microservice
npm install
```

3. Copie o `.env.example` para `.env` e configure:

- `LOCAL_PRINT_API_TOKEN`: segredo compartilhado com o host principal
- `DEFAULT_PRINT_CONNECTION`: `network` ou `file`
- `DEFAULT_PRINT_HOST`: IP da impressora de rede
- `DEFAULT_PRINT_PORT`: porta da impressora (normalmente `9100`)
- `DEFAULT_PRINT_FILE_PATH`: caminho de saída, se usar `file`

4. Inicie o serviço:

```bash
npm start
```

5. **IMPORTANTE**: Exponha o serviço para a internet usando túnel reverso (veja CONNECTIVITY.md)

   **Opção rápida com ngrok:**
   ```bash
   npm run start-tunnel
   ```
   Isso iniciará automaticamente o ngrok + micro serviço.

6. Configure o Laravel hospedado para usar este micro serviço:

```env
PRINT_CONNECTION=microservice
PRINT_MICROSERVICE_URL=https://sua-url-exposta/print
PRINT_MICROSERVICE_TOKEN=seu_token_secreto
```

7. Teste a conectividade:

```bash
php scripts/test_microservice_connectivity.php
```

## Endpoints


- `GET /health` - retorna status do serviço
- `POST /print` - envia conteúdo para a impressora

## Observações

- Este micro servidor roda separado do projeto Laravel.
- O host principal deve chamar este serviço via HTTP.
- O serviço suporta impressão via rede e também gravação em arquivo.
- Para impressoras USB/compartilhadas no Windows, use o modo de arquivo ou um driver local apropriado.
