import express from 'express';
import net from 'net';
import fs from 'fs/promises';
import os from 'os';
import path from 'path';
import { spawn } from 'child_process';
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
  const printerName = process.env.DEFAULT_USB_PRINTER_NAME || process.env.PRINT_USB_PRINTER_NAME || process.env.PRINT_PRINTER_NAME || payload.printer_name || payload.printer;

  console.log(`[${new Date().toISOString()}] Print request received: connection=${connection}, host=${host || '-'}, port=${portNumber}, printer=${printerName || '-'}`);

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

    if (connection === 'usb' || connection === 'windows') {
      if (!printerName) {
        return res.status(400).json({ error: 'Missing printer_name for USB/Windows printing.' });
      }

      await sendToWindowsPrinter(printerName, buffer);
      console.log(`[${new Date().toISOString()}] Print sent successfully to Windows printer "${printerName}"`);
      return res.json({ success: true, printer: 'usb', printer_name: printerName });
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

async function sendToWindowsPrinter(printerName, content) {
  if (process.platform !== 'win32') {
    throw new Error('USB/Windows printing is only supported on Windows.');
  }

  const buffer = Buffer.isBuffer(content) ? content : Buffer.from(String(content), 'utf8');
  const tempDir = await fs.mkdtemp(path.join(os.tmpdir(), 'printer-microservice-'));
  const dataPath = path.join(tempDir, 'print-job.bin');
  const scriptPath = path.join(tempDir, 'raw-print.ps1');

  const script = `
param(
  [Parameter(Mandatory=$true)][string]$PrinterName,
  [Parameter(Mandatory=$true)][string]$FilePath
)

$source = @"
using System;
using System.Runtime.InteropServices;

public class RawPrinterHelper
{
  [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
  public class DOCINFOA
  {
    [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
    [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
    [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
  }

  [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

  [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool ClosePrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

  [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool EndDocPrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool StartPagePrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool EndPagePrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
  public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

  public static void SendBytes(string printerName, byte[] bytes)
  {
    IntPtr printerHandle;
    if (!OpenPrinter(printerName.Normalize(), out printerHandle, IntPtr.Zero)) {
      throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error());
    }

    IntPtr unmanagedBytes = IntPtr.Zero;
    try {
      DOCINFOA docInfo = new DOCINFOA();
      docInfo.pDocName = "Printer Microservice RAW Job";
      docInfo.pDataType = "RAW";

      if (!StartDocPrinter(printerHandle, 1, docInfo)) {
        throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error());
      }
      if (!StartPagePrinter(printerHandle)) {
        throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error());
      }

      unmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
      Marshal.Copy(bytes, 0, unmanagedBytes, bytes.Length);
      int written;
      if (!WritePrinter(printerHandle, unmanagedBytes, bytes.Length, out written) || written != bytes.Length) {
        throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error());
      }

      EndPagePrinter(printerHandle);
      EndDocPrinter(printerHandle);
    }
    finally {
      if (unmanagedBytes != IntPtr.Zero) {
        Marshal.FreeCoTaskMem(unmanagedBytes);
      }
      ClosePrinter(printerHandle);
    }
  }
}
"@

Add-Type -TypeDefinition $source
[RawPrinterHelper]::SendBytes($PrinterName, [System.IO.File]::ReadAllBytes($FilePath))
`;

  await fs.writeFile(dataPath, buffer);
  await fs.writeFile(scriptPath, script);

  try {
    await runPowerShell(scriptPath, ['-PrinterName', printerName, '-FilePath', dataPath]);
  } finally {
    await fs.rm(tempDir, { recursive: true, force: true });
  }
}

function runPowerShell(scriptPath, args) {
  return new Promise((resolve, reject) => {
    const child = spawn('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', scriptPath, ...args], {
      windowsHide: true,
    });

    let stderr = '';
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
    });

    child.on('error', reject);
    child.on('close', (code) => {
      if (code === 0) {
        resolve();
        return;
      }

      reject(new Error(stderr.trim() || `PowerShell exited with code ${code}`));
    });
  });
}

app.listen(port, () => {
  console.log(`Printer microservice listening on http://localhost:${port}`);
});
