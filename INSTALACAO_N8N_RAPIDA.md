# Instalação Rápida do n8n para WhatsApp

## 🚀 Opção 1: n8n.cloud (Recomendado - 5 minutos)

### 1. Criar conta
- Acesse: https://n8n.cloud
- Clique em "Start Free"
- Use email e senha

### 2. Configurar workflow
- Dashboard → Workflows → "New workflow"
- Clique em "Import from file"
- Selecione: `n8n_workflow_example.json`
- Clique em "Import"

### 3. Configurar webhook
- No node "Webhook", clique nele
- Copie a URL do webhook (ex: `https://seu-subdominio.n8n.cloud/webhook/restaurante...`)
- Esta URL será usada pelo provedor WhatsApp

### 4. Ativar workflow
- Botão "Activate" no canto superior direito
- Workflow agora está ouvindo webhooks

## 🐳 Opção 2: Docker Local (10 minutos)

### 1. Instalar Docker
- Windows: https://docs.docker.com/desktop/install/windows-install/
- Linux: `sudo apt install docker.io docker-compose`

### 2. Executar n8n
```bash
docker run -it --rm \
  --name n8n \
  -p 5678:5678 \
  -v ~/.n8n:/home/node/.n8n \
  n8nio/n8n
```

### 3. Acessar interface
- Abra: http://localhost:5678
- Primeiro acesso: crie usuário/senha

### 4. Importar workflow
- Settings → Workflows → Import
- Selecione `n8n_workflow_example.json`
- Ativar workflow

## 🔌 Opção 3: n8n Self-Hosted (Produção)

### 1. docker-compose.yml
```yaml
version: '3.8'

services:
  n8n:
    image: n8nio/n8n
    container_name: n8n
    restart: unless-stopped
    ports:
      - "5678:5678"
    environment:
      - N8N_PROTOCOL=https
      - N8N_HOST=seu-dominio.com
      - N8N_PORT=5678
      - N8N_WEBHOOK_URL=https://seu-dominio.com
      - WEBHOOK_URL=https://seu-dominio.com
      - GENERIC_TIMEZONE=America/Sao_Paulo
    volumes:
      - n8n_data:/home/node/.n8n
    networks:
      - n8n_network

volumes:
  n8n_data:

networks:
  n8n_network:
```

### 2. Executar
```bash
docker-compose up -d
```

## 📱 Configurar Provedor WhatsApp

### Take Blip (Recomendado)
1. Acesse: https://taketblip.com
2. Criar conta → WhatsApp → Conectar número
3. Configurar webhook:
   - URL: `https://seu-n8n.n8n.cloud/webhook/restaurante-atendente-pedidos-v2`
   - Método: POST
   - Content-Type: application/json

### Formato esperado do webhook:
```json
{
  "phone": "5511999999999",
  "message": "cardapio",
  "contact_name": "João Silva"
}
```

## 🔧 Configuração do Dimas

### 1. Token já configurado
Verifique `.env`:
```env
N8N_SHARED_TOKEN=cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5
```

### 2. Testar API
```bash
php test_n8n_api.php
```

### 3. Iniciar servidor (se não estiver rodando)
```bash
php artisan serve
```

## 🧪 Teste Rápido

### 1. Simular mensagem
```bash
curl -X POST "http://localhost:5678/webhook/restaurante-atendente-pedidos-v2" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "5511999999999",
    "message": "cardapio",
    "contact_name": "Teste"
  }'
```

### 2. Verificar logs do n8n
- Interface do n8n → Executions
- Verifique cada node

### 3. Testar com WhatsApp real
- Envie "cardapio" para o número conectado
- Deve receber resposta automática

## 🚨 Solução de Problemas Comuns

### Webhook não chega
1. Verifique se workflow está ativo
2. Verifique URL do webhook
3. Teste com curl localmente

### API não responde
1. Verifique token no `.env`
2. Execute: `php artisan config:clear`
3. Teste: `php test_n8n_api.php`

### Mensagem não envia
1. Verifique credenciais do WhatsApp
2. Verifique formato do telefone (55DDDNúmero)
3. Verifique logs do provedor WhatsApp

## 📊 Monitoramento

### n8n.cloud
- Dashboard com estatísticas
- Logs de execução
- Alertas de erro

### Self-hosted
```bash
# Verificar container
docker logs n8n

# Verificar saúde
curl http://localhost:5678/healthz
```

## 🔄 Atualização

### n8n.cloud
- Automática

### Docker
```bash
docker pull n8nio/n8n
docker-compose down
docker-compose up -d
```

---

## ✅ Checklist de Go-Live

- [ ] n8n instalado e acessível
- [ ] Workflow importado e ativo
- [ ] Webhook URL copiada
- [ ] Provedor WhatsApp configurado
- [ ] Token configurado no `.env`
- [ ] API testada com `test_n8n_api.php`
- [ ] Servidor Laravel rodando
- [ ] Teste com mensagem real
- [ ] Logs verificados
- [ ] Time da equipe treinado

---

**Tempo estimado:** 15-30 minutos para configurar tudo.

**Custo:**
- n8n.cloud: Plano gratuito (500 execuções/mês)
- Take Blip: Teste gratuito, depois ~R$ 200/mês
- Self-hosted: Apenas custo do servidor (~R$ 50/mês)