import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';

const [qrUrl, rfc, chromeBinary, timeoutValue = '120000'] = process.argv.slice(2);
const timeoutMs = Number.parseInt(timeoutValue, 10) || 120000;

if (!qrUrl || !rfc || !chromeBinary) {
  fail('missing_arguments');
}

const startedAt = Date.now();
const port = 9300 + Math.floor(Math.random() * 500);
const profile = await mkdtemp(join(tmpdir(), 'infonavit-chrome-'));
let chrome;

function remainingMs() {
  return Math.max(1000, timeoutMs - (Date.now() - startedAt));
}

async function waitForCdp(port) {
  while (remainingMs() > 0) {
    try {
      const response = await fetch(`http://127.0.0.1:${port}/json/version`);
      if (response.ok) return;
    } catch {
      // Chrome is still starting.
    }
    await sleep(500);
  }
  throw new Error('chrome_cdp_timeout');
}

async function waitForInfonavitTab(port) {
  while (remainingMs() > 0) {
    const response = await fetch(`http://127.0.0.1:${port}/json/list`);
    const tabs = await response.json();
    const tab = tabs.find((item) => item.url?.startsWith('https://portalmx.infonavit.org.mx/'));
    if (tab?.webSocketDebuggerUrl) return tab;
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
      const byPlaceholder = (placeholder) => [...document.querySelectorAll('input')]
        .find(input => (input.placeholder || '').toLowerCase() === placeholder.toLowerCase());
      const consultButton = () => [...document.querySelectorAll('button')]
        .find(button => button.innerText.trim().toLowerCase() === 'consultar');

      while (Date.now() < deadline) {
        if (text().includes('Datos de identificación del documento')) break;

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
      const field = (label) => {
        const escaped = label.replace(/[.*+?^${'${'}()|[\\]\\\\]/g, '\\\\$&');
        const match = bodyText.match(new RegExp(escaped + '\\\\s*:?\\\\s*([^\\\\n]+)', 'i'));
        return match ? match[1].trim() : null;
      };

      return {
        url: location.href,
        title: document.title,
        raw_text: bodyText.slice(0, 8000),
        oficio: field('Número de oficio'),
        issued_at: field('Fecha de oficio'),
        valid_until: field('Fecha fin de vigencia'),
        compliance_status: field('Estatus cumplimiento'),
        bimestre: field('Bimestre'),
        rfc: field('RFC'),
        name: field('Nombre o Razón Social'),
        total_nrp: field('Total NRP'),
        total_workers: field('Total trabajadores'),
      };
    })()
  `;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function fail(message) {
  console.log(JSON.stringify({ ok: false, error: message }));
  process.exit(1);
}

class CdpClient {
  static connect(url) {
    return new Promise((resolve, reject) => {
      const socket = new WebSocket(url);
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
        const { resolve, reject } = this.pending.get(message.id);
        this.pending.delete(message.id);
        message.error ? reject(new Error(message.error.message)) : resolve(message);
      }
    });
  }

  send(method, params = {}) {
    const id = this.nextId++;
    this.socket.send(JSON.stringify({ id, method, params }));

    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
    });
  }

  async evaluate(expression, timeoutMs) {
    const result = await Promise.race([
      this.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
      }),
      sleep(timeoutMs).then(() => { throw new Error('infonavit_result_timeout'); }),
    ]);

    return result.result.result.value;
  }

  close() {
    this.socket.close();
  }
}

try {
  chrome = spawn(chromeBinary, [
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${profile}`,
    '--no-first-run',
    '--disable-extensions',
    '--disable-background-networking',
    qrUrl,
  ], { stdio: 'ignore' });

  await waitForCdp(port);
  const tab = await waitForInfonavitTab(port);
  const cdp = await CdpClient.connect(tab.webSocketDebuggerUrl);

  try {
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await sleep(5000);
    const result = await cdp.evaluate(browserScript(rfc), remainingMs());
    console.log(JSON.stringify({ ok: true, ...result }));
  } finally {
    await cdp.close();
  }
} catch (error) {
  fail(error?.message || 'browser_validation_failed');
} finally {
  if (chrome && !chrome.killed) {
    chrome.kill();
  }
  await rm(profile, { recursive: true, force: true }).catch(() => {});
}
