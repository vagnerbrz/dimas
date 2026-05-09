#!/bin/bash

# Script para iniciar o micro serviço com túnel reverso
# Requer: npm, ngrok instalado

set -e

echo "🚀 Iniciando Printer Microservice com túnel reverso..."

# Verificar se ngrok está instalado
if ! command -v ngrok &> /dev/null; then
    echo "❌ ngrok não encontrado. Instale em: https://ngrok.com/download"
    exit 1
fi

# Verificar se .env existe
if [ ! -f ".env" ]; then
    echo "❌ Arquivo .env não encontrado. Copie .env.example para .env"
    exit 1
fi

# Instalar dependências se node_modules não existir
if [ ! -d "node_modules" ]; then
    echo "📦 Instalando dependências..."
    npm install
fi

# Iniciar ngrok em background
echo "🌐 Iniciando túnel ngrok..."
ngrok http 3000 > ngrok.log 2>&1 &
NGROK_PID=$!

# Aguardar ngrok inicializar
sleep 3

# Obter URL do ngrok
NGROK_URL=$(curl -s http://localhost:4040/api/tunnels | jq -r '.tunnels[0].public_url')

if [ -z "$NGROK_URL" ]; then
    echo "❌ Falha ao obter URL do ngrok"
    kill $NGROK_PID 2>/dev/null || true
    exit 1
fi

echo "✅ Túnel ativo: $NGROK_URL"
echo "📝 Configure no Laravel hospedado:"
echo "   PRINT_MICROSERVICE_URL=$NGROK_URL/print"
echo ""

# Iniciar servidor
echo "🖨️  Iniciando micro serviço..."
npm start

# Cleanup
kill $NGROK_PID 2>/dev/null || true