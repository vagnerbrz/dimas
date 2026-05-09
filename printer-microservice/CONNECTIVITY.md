# Como conectar o Laravel hospedado ao micro serviço local

O Laravel hospedado precisa acessar o micro serviço Node que roda no seu PC local. Isso requer que o PC local seja acessível pela internet.

## Opções de conectividade

### 1. Túnel reverso (Recomendado - Seguro)

Use ferramentas como ngrok, Cloudflare Tunnel ou LocalTunnel para expor o micro serviço local na internet de forma segura.

#### Exemplo com ngrok:

1. Instale ngrok: https://ngrok.com/download
2. Execute o micro serviço local na porta 3000
3. Abra túnel: `ngrok http 3000`
4. Use a URL gerada (ex: `https://abc123.ngrok.io`) no `PRINT_MICROSERVICE_URL` do Laravel

#### Exemplo com Cloudflare Tunnel:

1. Instale cloudflared: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/tunnel-guide/
2. Configure túnel para a porta 3000
3. Use a URL do Cloudflare no `PRINT_MICROSERVICE_URL`

### 2. IP público direto (Não recomendado)

Se o seu PC local tem IP público fixo:

- Configure o roteador para redirecionar porta 3000 para o PC local
- Use `PRINT_MICROSERVICE_URL=http://seu-ip-publico:3000/print`

⚠️ **Riscos**: Exposição direta à internet, vulnerabilidades de segurança.

### 3. VPN

Configure VPN entre o servidor hospedado e a rede local do restaurante.

## Configuração no Laravel hospedado

Após expor o micro serviço local:

```env
PRINT_CONNECTION=microservice
PRINT_MICROSERVICE_URL=https://sua-url-exposta/print
PRINT_MICROSERVICE_TOKEN=seu_token_secreto
```

## Verificação

Teste se o Laravel consegue acessar o micro serviço:

```bash
php scripts/test_microservice_connectivity.php
```

Este script verifica:
- Configuração das variáveis de ambiente
- Conectividade HTTP com o micro serviço
- Autenticação via token
- Capacidade de enviar dados de impressão de teste

```bash
curl -H "Authorization: Bearer SEU_TOKEN" https://sua-url-exposta/health
```

Deve retornar: `{"status":"ok"}`

## Observações

- O micro serviço local precisa estar sempre rodando
- Use HTTPS quando possível para segurança
- Mantenha o token secreto compartilhado apenas entre Laravel e micro serviço
- Monitore logs do micro serviço para debug