import express from 'express';
import net from 'net';
import fs from 'fs/promises';
import dotenv from 'dotenv';

dotenv.config();

const app = express();
const port = Number(process.env.PORT || 3000);
const apiToken = process.env.LOCAL_PRINT_API_TOKEN || '';

function ensureAuth(req, res, next) {
  const auth = req.header('Authorization') || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7).trim() : '';
  const headerToken = (req.header('X-LOCAL-PRINT-TOKEN') || '').trim();
  const validToken = token || headerToken;

  if (!apiToken || !validToken || validToken !== apiToken) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  next();
}

app.use(express.json({ limit: '1mb' }));

app.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

app.post('/print', ensureAuth, async (req, res) => {
  const payload = req.body;
  const connection = process.env.DEFAULT_PRINT_CONNECTION || process.env.PRINT_CONNECTION || payload.connection || 'network';
  const encoding = (payload.encoding || 'utf8').toString().toLowerCase();
  const content = payload.content || payload.raw || '';
  const host = process.env.DEFAULT_PRINT_HOST || process.env.PRINT_HOST || payload.host;
  const portNumber = Number(process.env.DEFAULT_PRINT_PORT || process.env.PRINT_PORT || payload.port || 9100);
  const filePath = process.env.DEFAULT_PRINT_FILE_PATH || process.env.PRINT_FILE_CONNECTOR || payload.file_path;

  console.log(`[${new Date().toISOString()}] Print request received: connection=${connection}, host=${host || '-'}, port=${portNumber}`);

  if (!content) {
    return res.status(400).json({ error: 'Missing content to print.' });
  }

  let buffer;
  try {
    if (encoding === 'base64') {
      if (typeof content !== 'string') {
        return res.status(400).json({ error: 'Content must be a base64 string when encoding is base64.' });
      }
      buffer = Buffer.from(content, 'base64');
    } else if (encoding === 'utf8') {
      buffer = Buffer.from(String(content), 'utf8');
    } else {
      return res.status(400).json({ error: `Unsupported encoding: ${encoding}` });
    }
  } catch (error) {
    return res.status(400).json({ error: 'Invalid content encoding.', details: error instanceof Error ? error.message : String(error) });
  }

  try {
    if (connection === 'network') {
      if (!host) {
        return res.status(400).json({ error: 'Missing host for network printing.' });
      }

      await sendToNetworkPrinter(host, portNumber, buffer);
      console.log(`[${new Date().toISOString()}] Print sent successfully to ${host}:${portNumber}`);
      return res.json({ success: true, printer: 'network', host, port: portNumber });
    }

    if (connection === 'file') {
      if (!filePath) {
        return res.status(400).json({ error: 'Missing file_path for file printing.' });
      }

      await fs.writeFile(filePath, buffer);
      console.log(`[${new Date().toISOString()}] Print written successfully to ${filePath}`);
      return res.json({ success: true, printer: 'file', file_path: filePath });
    }

    return res.status(400).json({ error: `Unsupported connection type: ${connection}` });
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error(`[${new Date().toISOString()}] Print failed: ${message}`);
    return res.status(500).json({ error: 'Print failed.', details: message });
  }
});

function sendToNetworkPrinter(host, port, content) {
  return new Promise((resolve, reject) => {
    const socket = new net.Socket();
    const buffer = Buffer.isBuffer(content) ? content : Buffer.from(String(content), 'utf8');

    socket.setTimeout(15000);

    socket.on('connect', () => {
      socket.write(buffer, (err) => {
        if (err) {
          socket.destroy();
          return reject(err);
        }
        socket.end();
      });
    });

    socket.on('timeout', () => {
      socket.destroy();
      reject(new Error('Connection timed out.'));
    });

    socket.on('error', (err) => {
      reject(err);
    });

    socket.on('close', (hadError) => {
      if (!hadError) {
        resolve();
      }
    });

    socket.connect(port, host);
  });
}

app.listen(port, () => {
  console.log(`Printer microservice listening on http://localhost:${port}`);
});
