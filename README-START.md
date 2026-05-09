# 🚀 Inicialização do Sistema Restaurante Dimas

## 📋 Pré-requisitos

Antes de iniciar, certifique-se de ter instalado:

1. **Node.js** (v16+): https://nodejs.org/
2. **PHP** (8.1+): https://www.php.net/downloads.php
3. **Composer**: https://getcomposer.org/download/
4. **MySQL** (ou outro banco configurado no .env)

## 🏃‍♂️ Iniciando o Sistema

### Método 1: Usando start.bat (Recomendado)
Execute o arquivo `start.bat` na raiz do projeto:
```
C:\sistema\dimas\start.bat
```

### Método 2: Manualmente
Abra 4 terminais separados e execute:

**Terminal 1 - Laravel:**
```bash
cd C:\sistema\dimas
php artisan serve --port=8000
```

**Terminal 2 - WebSockets:**
```bash
cd C:\sistema\dimas
php artisan websockets:serve
```

**Terminal 3 - WPPConnect Server:**
```bash
cd C:\sistema\wppconnect-server
npm start
```

**Terminal 4 - n8n (Opcional):**
```bash
cd C:\sistema\n8n
npx n8n start
```

## 🔧 Configuração do WPPConnect

### 1. Configurar Webhook no WPPConnect
Após iniciar o WPPConnect Server (http://localhost:21465):

1. Acesse a interface do WPPConnect
2. Vá para configurações de webhook
3. Configure o webhook para:
   ```
   URL: http://localhost:8000/api/whatsapp/webhook
   Método: POST
   Content-Type: application/json
   ```

### 2. Configurar Sessão WhatsApp
1. No WPPConnect, inicie uma nova sessão
2. Escaneie o QR Code com seu WhatsApp
3. A sessão será salva automaticamente

## 🌐 URLs dos Serviços

| Serviço | URL | Status |
|---------|-----|--------|
| **Laravel** | http://localhost:8000 | ✅ Principal |
| **Dashboard** | http://localhost:8000/dashboard | ✅ Chat PDV |
| **WebSockets** | ws://localhost:6001 | ✅ Tempo real |
| **WPPConnect** | http://localhost:21465 | ✅ WhatsApp |
| **n8n** | http://localhost:5678 | ⚠️ Opcional |

## 🛠️ Comandos Úteis

### Banco de Dados
```bash
# Rodar migrations
php artisan migrate

# Rollback
php artisan migrate:rollback

# Status das migrations
php artisan migrate:status
```

### Cache e Otimização
```bash
# Limpar cache
php artisan optimize:clear

# Cache de configuração
php artisan config:cache

# Cache de rotas
php artisan route:cache
```

### Desenvolvimento
```bash
# Tinker (console interativo)
php artisan tinker

# Listar rotas
php artisan route:list

# Verificar ambiente
php artisan about
```

## 🔍 Verificação do Sistema

Para verificar se tudo está funcionando:

1. **Laravel**: Acesse http://localhost:8000
2. **WebSockets**: Verifique se `php artisan websockets:serve` está rodando
3. **WPPConnect**: Acesse http://localhost:21465
4. **Chat PDV**: Acesse http://localhost:8000/dashboard

## 🚨 Solução de Problemas

### "Port already in use"
```bash
# Verificar processos usando a porta
netstat -ano | findstr :8000

# Matar processo (substituir PID)
taskkill /PID <PID> /F
```

### "WebSocket connection failed"
- Verifique se `php artisan websockets:serve` está rodando
- Confirme as configurações no `.env` (PUSHER_*)
- Verifique o firewall

### "WPPConnect não conecta"
- Verifique se o Node.js está instalado
- Confirme se `npm start` está rodando no diretório correto
- Verifique logs do WPPConnect Server

## 📞 Suporte

Em caso de problemas:
1. Verifique os logs em `storage/logs/laravel.log`
2. Confirme todas as portas estão livres
3. Reinicie os serviços com `stop.bat` e `start.bat`

---

**⚠️ Importante**: Mantenha todos os serviços rodando durante o uso do sistema!