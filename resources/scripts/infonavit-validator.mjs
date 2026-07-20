import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';
import WsWebSocket from 'ws';

const WebSocketClient = globalThis.WebSocket || WsWebSocket;

const [qrUrl, rfc, chromeBinary, timeoutValue = '120000', browserMode = 'headless'] = process.argv.slice(2);
const timeoutMs = Number.parseInt(timeoutValue, 10) || 120000;

if (!qrUrl || !rfc || !chromeBinary) {
  fail('missing_arguments');
}

const startedAt = Date.now();
const port = 9300 + Math.floor(Math.random() * 500);
const profile = await mkdtemp(join(tmpdir(), 'infonavit-chrome-'));
let chrome;
let stage = 'init';
const hardTimer = setTimeout(() => fail('script_hard_timeout'), timeoutMs + 10000);

function remainingMs() {
  return Math.max(1000, timeoutMs - (Date.now() - startedAt));
}

async function waitForCdp(port) {
  while (remainingMs() > 0) {
    try {
      const response = await fetchWithTimeout(`http://127.0.0.1:${port}/json/version`);
      if (response.ok) return;
    } catch {
      // Chrome is still starting.
    }
    await sleep(500);
  }
  throw new Error('chrome_cdp_timeout');
}

async function waitForInfonavitTab(port) {
  const fallbackAfter = Date.now() + 30000;
  let fallbackTab = null;

  while (remainingMs() > 0) {
    const response = await fetchWithTimeout(`http://127.0.0.1:${port}/json/list`);
    const tabs = await response.json();
    const tab = tabs.find((item) => item.url?.startsWith('https://portalmx.infonavit.org.mx/'));
    if (tab?.webSocketDebuggerUrl) return tab;

    fallbackTab ??= tabs.find((item) => item.webSocketDebuggerUrl);
    if (fallbackTab?.webSocketDebuggerUrl && Date.now() >= fallbackAfter) {
      return fallbackTab;
    }

    await sleep(500);
  }
  throw new Error('infonavit_tab_timeout');
}

function browserScript(expectedRfc) {
  return `
    (async () => {
      const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));
      const deadline = Date.now() + ${JSON.stringify(Math.max(1000, remainingMs() - 1000))};
      const expectedRfc = ${JSON.stringify(expectedRfc.toUpperCase())};

      const text = () => document.body?.innerText || '';
      const normalize = (value) => (value || '').normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase();
      const byPlaceholder = (placeholder) => [...document.querySelectorAll('input')]
        .find(input => normalize(input.placeholder) === normalize(placeholder));
      const consultButton = () => [...document.querySelectorAll('button')]
        .find(button => normalize(button.innerText.trim()) === 'consultar');

      while (Date.now() < deadline) {
        const pageText = normalize(text());
        if (pageText.includes('datos de identificacion del documento') || pageText.includes('estatus cumplimiento')) break;

        const rfcInput = byPlaceholder('RFC');
        if (rfcInput) {
          rfcInput.focus();
          rfcInput.value = expectedRfc;
          for (const type of ['input', 'keyup', 'change', 'blur']) {
            rfcInput.dispatchEvent(new Event(type, { bubbles: true }));
          }

          await sleep(750);
          const button = consultButton();
          if (button && !button.disabled) {
            button.click();
          }
        }

        await sleep(1000);
      }

      const bodyText = text();
      const lines = bodyText.split(/\\n+/).map(line => line.trim()).filter(Boolean);
      const field = (label) => {
        const target = normalize(label);
        for (let index = 0; index < lines.length; index += 1) {
          const line = lines[index];
          const normalizedLine = normalize(line);
          if (normalizedLine === target) {
            return lines[index + 1] || null;
          }
          if (normalizedLine.startsWith(target + ':')) {
            return line.slice(line.indexOf(':') + 1).trim() || null;
          }
        }
        return null;
      };

      return {
        url: location.href,
        title: document.title,
        raw_text: bodyText.slice(0, 8000),
        inputs: [...document.querySelectorAll('input')].map(input => ({
          name: input.name,
          id: input.id,
          placeholder: input.placeholder,
          value: input.value,
        })).slice(0, 30),
        buttons: [...document.querySelectorAll('button')].map(button => button.innerText.trim()).filter(Boolean).slice(0, 20),
        oficio: field('Numero de oficio') || field('Número de oficio'),
        issued_at: field('Fecha de oficio'),
        valid_until: field('Fecha fin de vigencia'),
        compliance_status: field('Estatus cumplimiento'),
        bimestre: field('Bimestre'),
        rfc: field('RFC'),
        name: field('Nombre o Razon Social') || field('Nombre o Razón Social'),
        total_nrp: field('Total NRP'),
        total_workers: field('Total trabajadores'),
      };
    })()
  `;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function fetchWithTimeout(url, ms = 5000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), ms);
  try {
    return await fetch(url, { signal: controller.signal });
  } finally {
    clearTimeout(timer);
  }
}

function cleanupChrome() {
  if (!chrome || chrome.killed || !chrome.pid) return;
  try {
    if (process.platform === 'win32') {
      chrome.kill('SIGKILL');
    } else {
      process.kill(-chrome.pid, 'SIGKILL');
    }
  } catch {
    try { chrome.kill('SIGKILL'); } catch {}
  }
}

function fail(message) {
  console.log(JSON.stringify({ ok: false, error: message, stage }));
  cleanupChrome();
  process.exit(1);
}

class CdpClient {
  static connect(url) {
    return new Promise((resolve, reject) => {
      const socket = new WebSocketClient(url);
      socket.addEventListener('open', () => resolve(new CdpClient(socket)));
      socket.addEventListener('error', () => reject(new Error('cdp_websocket_error')));
    });
  }

  constructor(socket) {
    this.socket = socket;
    this.nextId = 1;
    this.pending = new Map();
    socket.addEventListener('message', (event) => {
      const message = JSON.parse(event.data);
      if (message.id && this.pending.has(message.id)) {
        const { resolve, reject, timer } = this.pending.get(message.id);
        clearTimeout(timer);
        this.pending.delete(message.id);
        message.error ? reject(new Error(message.error.message)) : resolve(message);
      }
    });
  }

  send(method, params = {}, timeoutMs = 10000) {
    const id = this.nextId++;
    this.socket.send(JSON.stringify({ id, method, params }));

    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pending.delete(id);
        reject(new Error(`cdp_${method}_timeout`));
      }, timeoutMs);
      this.pending.set(id, { resolve, reject, timer });
    });
  }

  async evaluate(expression, timeoutMs) {
    const result = await Promise.race([
      this.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
      }, timeoutMs),
      sleep(timeoutMs).then(() => { throw new Error('infonavit_result_timeout'); }),
    ]);

    return result.result.result.value;
  }

  close() {
    this.socket.close();
  }
}

try {
  stage = 'spawn_chrome';
  const args = [
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-crash-reporter',
    '--disable-crashpad',
    '--ignore-certificate-errors',
    '--allow-running-insecure-content',
    '--no-first-run',
    '--disable-extensions',
    '--disable-background-networking',
    qrUrl,
  ];

  if (browserMode !== 'headed') {
    args.splice(2, 0, '--headless=new');
  }

  chrome = spawn(chromeBinary, args, {
    detached: process.platform !== 'win32',
    env: {
      ...process.env,
      HOME: profile,
      XDG_CONFIG_HOME: join(profile, 'config'),
      XDG_CACHE_HOME: join(profile, 'cache'),
    },
    stdio: 'ignore',
  });

  stage = 'wait_cdp';
  await waitForCdp(port);
  stage = 'wait_infonavit_tab';
  const tab = await waitForInfonavitTab(port);
  stage = 'connect_cdp';
  const cdp = await CdpClient.connect(tab.webSocketDebuggerUrl);

  try {
    stage = 'page_enable';
    await cdp.send('Page.enable');
    stage = 'runtime_enable';
    await cdp.send('Runtime.enable');
    await sleep(5000);
    stage = 'evaluate_page';
    const result = await cdp.evaluate(browserScript(rfc), remainingMs());
    stage = 'done';
    console.log(JSON.stringify({ ok: true, ...result }));
  } finally {
    await cdp.close();
  }
} catch (error) {
  fail(error?.message || 'browser_validation_failed');
} finally {
  clearTimeout(hardTimer);
  cleanupChrome();
  await rm(profile, { recursive: true, force: true }).catch(() => {});
}
