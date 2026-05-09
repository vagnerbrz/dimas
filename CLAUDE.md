# 🍽️ Restaurante Dimas - Documentação do Sistema

Este documento serve como a "fonte da verdade" para o desenvolvimento e manutenção do sistema de gestão do Restaurante Dimas, focado em alto volume de vendas, produtividade operacional e autoatendimento via WhatsApp.

## 🚀 Visão Geral
Sistema de gestão de restaurante com foco em pratos do dia, operando em dois fluxos principais:
1.  **Venda Direta (Balcão)**: Pedidos manuais via painel administrativo.
2.  **Autoatendimento (WhatsApp)**: Fluxo automatizado via State Machine para captação de pedidos.

## 🛠️ Stack Tecnológica
- **Backend**: Laravel 11 (PHP 8.3+)
- **Frontend**: Livewire 3, Alpine.js, Tailwind CSS
- **Banco de Dados**: MySQL 8.0
- **Cache & Queues**: Redis (via Predis)
- **Integração WhatsApp**: WPPConnect (Servidor Node.js externo $\rightarrow$ Webhook Laravel)
- **Impressão**: ESC/POS via Filas (Redis)

## 🏗️ Arquitetura de Dados

### Modelos Principais
- `User`: Administradores do sistema.
- `Customer`: Clientes (cadastrados manualmente ou via WhatsApp).
- `Address`: Endereços de entrega (Relação 1:N com Customer). Possui flag `is_primary`.
- `Product`: Pratos do cardápio. Possui flag `is_active` (Toggle rápido para pratos do dia).
- `Order`: Pedidos. Tipos: `counter` (Balcão) ou `delivery` (Entrega).
- `OrderItem`: Itens do pedido com snapshot de preço unitário.
- `WhatsAppSession`: Controle de estado da conversa e carrinho temporário.

### Fluxo de Relações
`Customer` $\rightarrow$ `Address` (1:N)
`Customer` $\rightarrow$ `Order` (1:N)
`Order` $\rightarrow$ `OrderItem` (1:N)
`OrderItem` $\rightarrow$ `Product` (N:1)

## 🤖 Máquina de Estados do WhatsApp (State Machine)

O processamento de mensagens é gerenciado pelo `WhatsAppService`. A conversa flui através dos seguintes estados:

| Estado | Ação do Usuário | Resposta do Sistema | Próximo Estado |
| :--- | :--- | :--- | :--- |
| `start` | Envia "Oi" / Qualquer mensagem | Envia Cardápio Ativo | `selecting_product` |
| `selecting_product` | Digita o número do prato | Confirma prato e pede quantidade | `selecting_quantity` |
| `selecting_quantity` | Digita quantidade | Adiciona ao carrinho e pede endereço | `confirming_address` |
| `confirming_address` | Envia endereço (ou confirma principal) | Consolida dados do endereço | `confirmation` |
| `confirmation` | Digita "1" ou "Sim" | Salva Pedido no banco e finaliza | `start` (Sessão deletada) |

**Carrinho Temporário**: Armazenado no campo JSON `temporary_data` da tabela `whatsapp_sessions` até a confirmação final.

## ⚙️ Configurações e Operação

### Infraestrutura de Performance
- **Redis**: Essencial para processar as mensagens do WhatsApp e a impressão térmica sem travar a requisição HTTP.
- **Jobs**: `SendWhatsAppMessage` e `PrintOrderReceipt` processam tarefas assíncronas.

### Endpoints Críticos
- `POST /api/whatsapp/webhook`: Endpoint público que recebe eventos do WPPConnect.

### Regras de Negócio Importantes
- **Toggle de Produtos**: Apenas produtos com `is_active = true` aparecem no WhatsApp e no fluxo de novo pedido.
- **Validação de Delivery**: Pedidos do tipo `delivery` exigem obrigatoriamente um `address_id` válido.
- **Endereço Principal**: Somente um endereço por cliente pode ser definido como `is_primary`.

## 📅 Roadmap de Implementação
- [x] Núcleo de Produtos e Cardápio (CRUD + Toggle)
- [x] Gestão de Clientes e Múltiplos Endereços
- [x] Sistema de Pedidos (Balcão vs Delivery)
- [x] Dashboard Operacional (Livewire)
- [x] Sistema de Autenticação Admin
- [x] Infraestrutura Redis & Queues
- [x] State Machine do WhatsApp (Backend)
- [x] Integração Real com WPPConnect (Webhook)
- [ ] Sistema de Impressão Térmica ESC/POS
Hé uma prioridade total em **estabilidade** e **velocidade de resposta**.
