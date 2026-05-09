import { existsSync } from 'fs';
import { spawn, execSync } from 'child_process';
import http from 'http';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.join(__dirname, '.env');

function exitWithError(message) {
  console.error(`❌ ${message}`);
  process.exit(1);
}

function runCommand(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { stdio: 'inherit', shell: false, ...options });

    child.on('error', (error) => reject(error));
    child.on('exit', (code) => {
      if (code === 0) {
        resolve();
      } else {
        reject(new Error(`Command exited with code ${code}`));
      }
    });
  });
}

function isNgrokInstalled() {
  try {
    if (process.platform === 'win32') {
      execSync('where ngrok', { stdio: 'ignore' });
    } else {
      execSync('command -v ngrok', { stdio: 'ignore' });
    }
    return true;
  } catch {
    return false;
  }
}

function getNgrokUrl() {
  return new Promise((resolve, reject) => {
    const request = http.get('http://127.0.0.1:4040/api/tunnels', (response) => {
      let body = '';

      response.on('data', (chunk) => {
        body += chunk.toString();
      });

      response.on('end', () => {
        try {
          const data = JSON.parse(body);
          const url = data?.tunnels?.[0]?.public_url;

          if (url) {
            resolve(url);
          } else {
            reject(new Error('Não encontrou URL do túnel na resposta do ngrok.'));
          }
        } catch (error) {
          reject(error);
        }
      });
    });

    request.on('error', reject);
  });
}

async function waitForNgrokUrl(timeout = 15000) {
  const start = Date.now();

  while (Date.now() - start < timeout) {
    try {
      const url = await getNgrokUrl();
      return url;
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 500));
    }
  }

  throw new Error('Tempo esgotado esperando o ngrok iniciar.');
}

async function main() {
  console.log('🚀 Iniciando Printer Microservice com túnel reverso...');

  if (!existsSync(envPath)) {
    exitWithError('Arquivo .env não encontrado. Copie .env.example para .env');
  }

  if (!isNgrokInstalled()) {
    exitWithError('ngrok não encontrado. Instale em: https://ngrok.com/download');
  }

  if (!existsSync(path.join(__dirname, 'node_modules'))) {
    console.log('📦 Instalando dependências...');
    await runCommand('npm', ['install'], { cwd: __dirname });
  }

  console.log('🌐 Iniciando túnel ngrok...');
  const ngrok = spawn('ngrok', ['http', '3000'], { cwd: __dirname, shell: false, stdio: ['ignore', 'pipe', 'pipe'] });

  ngrok.stdout.on('data', (chunk) => {
    process.stdout.write(chunk);
  });

  ngrok.stderr.on('data', (chunk) => {
    process.stderr.write(chunk);
  });

  ngrok.on('error', (error) => {
    console.error('❌ Erro ao iniciar ngrok:', error.message);
    process.exit(1);
  });

  process.on('exit', () => {
    if (!ngrok.killed) {
      ngrok.kill();
    }
  });

  try {
    const url = await waitForNgrokUrl();
    console.log(`✅ Túnel ativo: ${url}`);
    console.log('📝 Configure no Laravel hospedado:');
    console.log(`   PRINT_MICROSERVICE_URL=${url}/print`);
    console.log('');
  } catch (error) {
    ngrok.kill();
    exitWithError(error.message);
  }

  console.log('🖨️  Iniciando micro serviço...');
  await runCommand('npm', ['start'], { cwd: __dirname });
}

main().catch((error) => {
  exitWithError(error.message);
});
