# Guia de Configuração: n8n + WhatsApp para Restaurante Dimas

## 📋 Visão Geral
Integração do n8n (automação) com WhatsApp para atendimento automático de clientes.

## 🏗️ Arquitetura
```
Cliente WhatsApp → n8n (workflow) → API Laravel Dimas → Banco de Dados
```

## 🔧 Pré-requisitos

### 1. n8n Instalado
- **Opção A**: n8n.cloud (SaaS) - mais fácil
- **Opção B**: Auto-hospedado (Docker) - mais controle

### 2. WhatsApp Business API
- **Provedor**: Take Blip, Weni, Zenvia, ou similar
- **Número de WhatsApp** empresarial verificado

### 3. Sistema Dimas
- Laravel rodando (php artisan serve)
- Banco de dados configurado
- Redis para filas (opcional mas recomendado)

## ⚙️ Configuração do Laravel Dimas

### 1. Configurar Token de Segurança
No arquivo `.env`:
```env
N8N_SHARED_TOKEN=cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5
N8N_WHATSAPP_WEBHOOK_URL=http://localhost:5678/webhook/restaurante-atendente-pedidos-v2
```

### 2. Limpar Cache
```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Verificar API
```bash
# Testar endpoint do cardápio
curl -X GET "http://localhost:8000/api/n8n/menu" \
  -H "X-N8N-Token: cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5"
```

## 🔌 Endpoints da API n8n

### Autenticação
- **Header**: `X-N8N-Token: <seu_token>`
- **Alternativo**: `Authorization: Bearer <seu_token>`

### Endpoints Disponíveis

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/n8n/menu` | Cardápio ativo |
| `POST` | `/api/n8n/orders` | Criar pedido |
| `POST` | `/api/n8n/messages/text` | Enviar mensagem WhatsApp |
| `POST` | `/api/n8n/suspend` | Suspender conversa (transferir para humano) |
| `GET` | `/api/n8n/whatsapp-config` | Configuração do WPPConnect |
| `GET` | `/api/n8n/customers` | Listar clientes |
| `POST` | `/api/n8n/customers` | Criar cliente |
| `GET` | `/api/n8n/customers/{id}` | Detalhes do cliente |
| `PATCH` | `/api/n8n/customers/{id}` | Atualizar cliente |
| `GET` | `/api/n8n/customers/{id}/addresses` | Endereços do cliente |
| `POST` | `/api/n8n/customers/{id}/addresses` | Adicionar endereço |

## 🤖 Workflow n8n (Exemplo)

### Fluxo Básico de Atendimento
```
1. Receber mensagem WhatsApp
2. Extrair telefone e mensagem
3. Analisar intenção (cardápio, pedido, horário, etc.)
4. Consultar API Dimas para informações
5. Responder apropriadamente
6. Se for pedido: coletar dados e criar via API
```

### Nodes Essenciais
1. **WhatsApp Node** (do provedor)
2. **HTTP Request Node** (para API Dimas)
3. **Code Node** (lógica de negócio)
4. **Switch Node** (roteamento por intenção)
5. **Delay Node** (tempo entre mensagens)

## 📱 Configuração do WhatsApp no n8n

### Usando Take Blip (Recomendado)
1. Criar conta em [Take Blip](https://taketblip.com)
2. Configurar número WhatsApp Business
3. Instalar node "Take Blip" no n8n
4. Configurar credenciais

### Usando WhatsApp Business API via HTTP
```json
{
  "method": "POST",
  "url": "https://api.provedor.com/v1/messages",
  "headers": {
    "Authorization": "Bearer <token_provedor>",
    "Content-Type": "application/json"
  },
  "body": {
    "to": "{{$json.phone}}",
    "type": "text",
    "text": {
      "body": "{{$json.message}}"
    }
  }
}
```

## 🚀 Workflow de Exemplo

### 1. Recepção de Mensagem
```json
{
  "phone": "5511999999999",
  "message": "Oi, quero ver o cardápio",
  "contact_name": "João"
}
```

### 2. Consulta Cardápio
```http
GET http://localhost:8000/api/n8n/menu
X-N8N-Token: cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5
```

### 3. Resposta Formatada
```javascript
// Code Node
const menu = $input.first().json;

let response = "🍽️ *CARDÁPIO DO DIMAS*\n\n";
menu.items.forEach(item => {
  response += `*${item.name}* - R$ ${item.formatted_price}\n`;
  if (item.description) {
    response += `  _${item.description}_\n`;
  }
  response += "\n";
});

response += "\nPara pedir, digite o *número* do prato desejado.";

return { message: response };
```

### 4. Envio para WhatsApp
```json
{
  "to": "5511999999999",
  "type": "text",
  "text": {
    "body": "🍽️ *CARDÁPIO DO DIMAS* ..."
  }
}
```

## 🛒 Fluxo de Pedido Completo

### 1. Cliente seleciona prato
```
Cliente: "1" (número do prato)
```

### 2. n8n pergunta quantidade
```
Bot: "Quantas unidades do *Feijoada Completa* você deseja?"
```

### 3. Cliente informa quantidade
```
Cliente: "2"
```

### 4. n8n pergunta tipo de entrega
```
Bot: "É para *retirar no balcão* ou *delivery*?"
Opções: [1] Balcão | [2] Delivery
```

### 5. Se delivery, coletar endereço
```
Bot: "Por favor, compartilhe seu endereço ou localização."
```

### 6. Confirmar pedido via API
```http
POST http://localhost:8000/api/n8n/orders
X-N8N-Token: <token>

{
  "customer": {
    "phone": "5511999999999",
    "name": "João"
  },
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ],
  "fulfillment_type": "delivery",
  "payment_method": "pix",
  "address": {
    "street": "Rua Exemplo",
    "number": "123",
    "neighborhood": "Centro",
    "city": "São Paulo",
    "state": "SP"
  }
}
```

### 7. Responder confirmação
```
Bot: "✅ Pedido #123 confirmado! Tempo estimado: 30min. Valor: R$ 45,90"
```

## 🔐 Segurança

### 1. Token de API
- Gerar token seguro (32+ caracteres hex)
- Usar apenas em comunicação n8n → Dimas
- Rotacionar periodicamente

### 2. Validação de Entrada
- Todos os inputs validados na API
- Sanitização de telefones
- Limites de quantidade

### 3. Rate Limiting
```php
// Adicionar no Laravel (opcional)
RateLimiter::for('n8n-api', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});
```

## 🧪 Testes

### Testar API Localmente
```bash
# Testar cardápio
curl -X GET "http://localhost:8000/api/n8n/menu" \
  -H "X-N8N-Token: cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5"

# Testar criação de pedido
curl -X POST "http://localhost:8000/api/n8n/orders" \
  -H "X-N8N-Token: cf7b27eac99c771ee2bf6b420277075c36733de6f8292d926d7e4dd62e7ebea5" \
  -H "Content-Type: application/json" \
  -d '{
    "customer": {
      "phone": "5511999999999",
      "name": "Teste"
    },
    "items": [
      {
        "product_id": 1,
        "quantity": 1
      }
    ],
    "fulfillment_type": "counter",
    "payment_method": "pix"
  }'
```

### Testar Webhook n8n
```bash
# Simular mensagem recebida
curl -X POST "http://localhost:5678/webhook/restaurante-atendente-pedidos-v2" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "5511999999999",
    "message": "cardapio",
    "contact_name": "Teste"
  }'
```

## 🚨 Solução de Problemas

### API não responde
1. Verificar se Laravel está rodando: `php artisan serve`
2. Verificar token no `.env`
3. Limpar cache: `php artisan config:clear`

### n8n não conecta
1. Verificar URL da API: `http://localhost:8000`
2. Verificar headers de autenticação
3. Testar endpoint com curl primeiro

### WhatsApp não envia
1. Verificar credenciais do provedor
2. Verificar formato do número (55DDDNúmero)
3. Verificar se número está verificado no WhatsApp Business

## 📈 Melhorias Futuras

### 1. Estado da Conversação
- Salvar estado no Redis
- Continuar conversas interrompidas
- Timeout automático (15min)

### 2. Catálogo no WhatsApp
- Usar Catálogo do WhatsApp Business
- Imagens dos produtos
- Preços atualizados automaticamente

### 3. Pagamento via WhatsApp
- Integrar com WhatsApp Payments (quando disponível no BR)
- Pix automático
- Confirmação de pagamento

### 4. Analytics
- Dashboard de conversas
- Taxa de conversão
- Tempo médio de resposta

## 📞 Suporte

### Logs do Laravel
```bash
tail -f storage/logs/laravel.log
```

### Logs do n8n
- Interface web do n8n → Executions
- Verificar cada node individualmente

### Monitoramento
1. Health check da API
2. Monitorar filas do Redis
3. Alertas de falha no WhatsApp

---

## ✅ Checklist de Implementação

- [ ] Configurar `.env` com `N8N_SHARED_TOKEN`
- [ ] Testar endpoints da API com curl
- [ ] Instalar/configurar n8n
- [ ] Configurar provedor WhatsApp
- [ ] Criar workflow básico no n8n
- [ ] Testar fluxo completo
- [ ] Implementar tratamento de erros
- [ ] Configurar monitoramento
- [ ] Documentar processos da equipe

---

**Próximo passo**: Configurar o token no `.env` e testar a API, depois configurar o n8n.