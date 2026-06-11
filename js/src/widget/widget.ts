import { uploadFile } from '../core';
import { STRINGS, type Strings } from './strings';
import { CSS } from './styles';
import type { FileRow, WidgetConfig, WidgetInstance } from './types';

const DEFAULT_ROUTE_PLACEHOLDER = '__ID__';
const DEFAULT_CHUNK_SIZE = 1024 * 1024;

let styleInjected = false;
const hasClipboardSupport =
  typeof navigator !== 'undefined' && !!navigator.clipboard && typeof navigator.clipboard.writeText === 'function';

function injectStyles(raw?: string | null): void {
  if (!styleInjected) {
    const style = document.createElement('style');
    style.id = 'file-upload-widget-styles';
    style.textContent = CSS;
    document.head.appendChild(style);
    styleInjected = true;
  }
  if (raw && typeof raw === 'string') {
    const style = document.createElement('style');
    style.textContent = raw;
    document.head.appendChild(style);
  }
}

function bytesToHuman(bytes: number): string {
  const n = Number(bytes);
  if (Number.isNaN(n) || n === 0) {
    return '0 B';
  }
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const exponent = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1);
  const value = n / 1024 ** exponent;

  return `${value.toFixed(value < 10 && exponent > 0 ? 1 : 0)} ${units[exponent]}`;
}

function formatString(template: string, params: Record<string, string>): string {
  if (!template) {
    return '';
  }
  return template.replace(/:([a-zA-Z0-9_]+)/g, (match, key: string) => (key in params ? params[key]! : match));
}

function resolveStrings(locale: string, overrides?: Record<string, Partial<Strings>>): Strings {
  const language = (locale || 'en').toLowerCase();
  const base = STRINGS[language] ?? STRINGS.en!;

  return { ...base, ...(overrides?.[language]) };
}

function resolveToken(config: WidgetConfig): string | null {
  if (config.auth === 'bearer') {
    return config.token ?? null;
  }
  if (config.auth === 'csrf' || config.auth === undefined) {
    if (config.token) {
      return config.token;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
      return meta.getAttribute('content');
    }
  }

  return null;
}

function applyStyles(element: HTMLElement | null, styleObject?: Partial<CSSStyleDeclaration>): void {
  if (element && styleObject) {
    Object.assign(element.style, styleObject);
  }
}

function emitEvent(name: string, detail: unknown): void {
  if (typeof window.CustomEvent === 'function') {
    window.dispatchEvent(new CustomEvent(name, { detail }));
  }
}

function buildRoute(template: string, params: Record<string, string>, placeholder: string): string {
  let url = template;
  const original = url;
  for (const [key, value] of Object.entries(params)) {
    if (value == null) {
      continue;
    }
    const encoded = encodeURIComponent(value);
    url = url.split(`{${key}}`).join(encoded);
    if (placeholder) {
      url = url.split(placeholder).join(encoded);
    }
  }
  if (url === original && Object.prototype.hasOwnProperty.call(params, 'id')) {
    return `${url.replace(/\/+$/, '')}/${encodeURIComponent(params.id!)}`;
  }

  return url;
}

function createJsonClient(baseHeaders: Record<string, string>, auth: WidgetConfig['auth'], token: string | null) {
  const defaultHeaders: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...baseHeaders,
  };

  return async function json(url: string, init?: RequestInit): Promise<unknown> {
    const headers: Record<string, string> = { ...defaultHeaders, ...((init?.headers as Record<string, string>) ?? {}) };
    if ((auth === 'csrf' || auth === undefined) && token) {
      headers['X-CSRF-TOKEN'] = token;
    }
    if (auth === 'bearer' && token && !headers.Authorization) {
      headers.Authorization = `Bearer ${token}`;
    }
    const response = await fetch(url, { credentials: 'same-origin', ...init, headers });
    if (!response.ok) {
      throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json();
  };
}

async function copyToClipboard(value: string): Promise<boolean> {
  if (!hasClipboardSupport || !value) {
    return false;
  }
  try {
    await navigator.clipboard.writeText(value);
    return true;
  } catch {
    return false;
  }
}

function resolveContainer(container: WidgetConfig['container']): HTMLElement | null {
  if (!container) {
    return null;
  }
  if (typeof container === 'string') {
    return document.querySelector<HTMLElement>(container);
  }

  return container;
}

interface QueueEntry {
  item: HTMLElement;
  name: HTMLElement;
  meta: HTMLElement;
  bar: HTMLElement;
  file: File;
}

/** Build and mount the upload widget. Returns a handle to refresh or destroy it. */
export function createWidget(userConfig: WidgetConfig = {}): WidgetInstance {
  const config = {
    endpointBase: '/file-upload',
    allowList: true,
    allowDelete: true,
    allowDeleteAllFiles: false,
    allowCleanup: true,
    ...userConfig,
    // normalized fields win over raw userConfig
    locale: (userConfig.locale ?? 'en').toLowerCase(),
    auth: userConfig.auth ?? 'csrf',
    csrfTokenField: userConfig.csrfTokenField ?? '_token',
    method: userConfig.method ?? 'POST',
    chunkSize: userConfig.chunkSize ?? DEFAULT_CHUNK_SIZE,
  } satisfies WidgetConfig;

  injectStyles(config.styles?.raw ?? null);

  const strings = resolveStrings(config.locale, config.i18n);
  const token = resolveToken(config);
  const placeholder = config.routePlaceholder || DEFAULT_ROUTE_PLACEHOLDER;
  const routes = config.routes ?? {};
  const headers: Record<string, string> = { ...config.headers };
  const jsonClient = createJsonClient(headers, config.auth, token);

  const containerEl = resolveContainer(config.container);
  const root = containerEl ?? document.createElement('div');
  root.className = 'fu-widget';

  // --- DOM ---
  const toggle = el('button', 'fu-toggle', { type: 'button' });
  toggle.textContent = strings.toggle;

  const modal = el('div', 'fu-modal');
  const header = el('div', 'fu-header');
  const title = el('span', 'fu-title');
  title.textContent = strings.title;
  const close = el('button', 'fu-close', { type: 'button' });
  close.innerHTML = '&times;';
  header.append(title, close);

  const dropzone = el('label', 'fu-dropzone');
  dropzone.textContent = strings.drop;
  const input = el('input', '', { type: 'file' }) as HTMLInputElement;
  input.multiple = true;
  dropzone.appendChild(input);

  const status = el('div', 'fu-status');
  const queueContainer = el('div', 'fu-queue');

  const listActions = el('div', 'fu-list-actions');
  const toggleListBtn = el('button', 'fu-pill secondary', { type: 'button' });
  toggleListBtn.textContent = strings.showList;
  const refreshButton = el('button', 'fu-pill', { type: 'button' });
  refreshButton.textContent = strings.refresh;
  listActions.append(toggleListBtn, refreshButton);

  const listContainer = el('div', 'fu-list');
  const table = el('table', 'fu-table') as HTMLTableElement;
  const thead = document.createElement('thead');
  const headRow = document.createElement('tr');
  (['files', 'size', 'created', ''] as const).forEach((key) => {
    const th = document.createElement('th');
    th.textContent = key === '' ? '' : strings[key];
    headRow.appendChild(th);
  });
  thead.appendChild(headRow);
  const tbody = document.createElement('tbody');
  table.append(thead, tbody);
  const empty = el('div', 'fu-empty');
  empty.textContent = strings.empty;
  listContainer.append(table, empty);

  modal.append(header, dropzone, status, queueContainer);
  if (config.allowList) {
    modal.append(listActions, listContainer);
  }
  root.append(modal, toggle);
  if (!containerEl) {
    document.body.appendChild(root);
  }

  applyStyles(toggle, config.styles?.toggle);
  applyStyles(modal, config.styles?.modal);
  applyStyles(dropzone, config.styles?.dropzone);

  // --- state ---
  let isOpen = false;
  let listOpen = false;
  let uploading = false;
  const queue: File[] = [];
  const entries = new Map<File, QueueEntry>();

  function setStatus(message: string, type?: 'error'): void {
    status.textContent = message || '';
    status.style.color = type === 'error' ? '#dc2626' : '#4b5563';
  }

  function updateQueueVisibility(): void {
    queueContainer.style.display = queueContainer.childElementCount ? 'flex' : 'none';
  }

  function getEntry(file: File): QueueEntry {
    const existing = entries.get(file);
    if (existing) {
      return existing;
    }
    const item = el('div', 'fu-queue-item');
    const name = el('div', 'fu-queue-name');
    name.textContent = file.name;
    const meta = el('div', 'fu-queue-meta');
    const progress = el('div', 'fu-progress');
    const bar = el('span', '');
    progress.appendChild(bar);
    meta.appendChild(progress);
    item.append(name, meta);
    queueContainer.appendChild(item);
    const entry: QueueEntry = { item, name, meta, bar, file };
    entries.set(file, entry);
    updateQueueVisibility();

    return entry;
  }

  function setProgress(entry: QueueEntry, percent: number): void {
    entry.bar.style.width = `${Math.max(0, Math.min(100, percent || 0))}%`;
  }

  function removeEntry(entry: QueueEntry): void {
    entry.item.remove();
    entries.delete(entry.file);
    updateQueueVisibility();
  }

  function markSuccess(entry: QueueEntry, message: string, url: string | null): void {
    entry.meta.innerHTML = '';
    const text = el('span', 'fu-queue-success');
    text.textContent = message;
    entry.meta.appendChild(text);

    const copyBtn = el('button', 'fu-icon-btn', { type: 'button' }) as HTMLButtonElement;
    copyBtn.textContent = '📋';
    copyBtn.title = strings.copy;
    copyBtn.setAttribute('aria-label', strings.copy);
    if (url) {
      copyBtn.addEventListener('click', async () => {
        if (await copyToClipboard(url)) {
          setStatus(strings.copied);
          entry.item.classList.add('fu-fade-out');
          setTimeout(() => removeEntry(entry), 3000);
        } else {
          setStatus(strings.errorCopy, 'error');
        }
      });
    } else {
      copyBtn.disabled = true;
    }
    entry.meta.appendChild(copyBtn);
    entry.name.addEventListener('click', () => entry.item.remove());
  }

  function markError(entry: QueueEntry, message: string): void {
    entry.meta.innerHTML = '';
    const text = el('span', 'fu-queue-error');
    text.textContent = message;
    entry.meta.appendChild(text);
    entry.name.addEventListener('click', () => entry.item.remove());
  }

  function updateTable(files: FileRow[]): void {
    tbody.innerHTML = '';
    if (!files.length) {
      empty.style.display = 'block';
      table.style.display = 'none';
      return;
    }
    empty.style.display = 'none';
    table.style.display = 'table';
    for (const file of files) {
      const row = document.createElement('tr');

      const nameCell = document.createElement('td');
      if (file.url) {
        const link = document.createElement('a');
        link.href = file.url;
        link.textContent = file.name;
        link.title = strings.copy;
        link.target = '_blank';
        link.addEventListener('click', async () => {
          if (await copyToClipboard(file.url!)) {
            setStatus(strings.copied);
          }
        });
        nameCell.appendChild(link);
      } else {
        nameCell.textContent = file.name;
      }
      row.appendChild(nameCell);

      const sizeCell = document.createElement('td');
      sizeCell.textContent = bytesToHuman(file.size);
      row.appendChild(sizeCell);

      const createdCell = document.createElement('td');
      createdCell.textContent = file.createdAt ? new Date(file.createdAt).toLocaleString() : '';
      row.appendChild(createdCell);

      const actionsCell = document.createElement('td');
      if (config.allowDelete && (config.allowDeleteAllFiles || file.own)) {
        const del = el('button', 'fu-icon-btn', { type: 'button' });
        del.textContent = '🗑️';
        del.title = strings.delete;
        del.setAttribute('aria-label', strings.delete);
        del.addEventListener('click', () => void deleteFile(file.id));
        actionsCell.appendChild(del);
      }
      if (config.allowCleanup && file.deletedAt) {
        const restore = el('button', '', { type: 'button' });
        restore.textContent = strings.restore;
        restore.addEventListener('click', () => void restoreFile(file.id));
        actionsCell.appendChild(restore);
      }
      row.appendChild(actionsCell);
      tbody.appendChild(row);
    }
  }

  async function fetchFiles(): Promise<void> {
    if (!config.allowList) {
      return;
    }
    try {
      const url = buildRoute(routes.list ?? `${config.endpointBase}/files`, {}, placeholder);
      const response = (await jsonClient(url)) as { data?: { files?: FileRow[] } };
      updateTable(Array.isArray(response?.data?.files) ? response.data!.files! : []);
      setStatus('');
    } catch (error) {
      console.warn(error);
      setStatus(strings.failed, 'error');
    }
  }

  async function deleteFile(id: string): Promise<void> {
    if (!confirm(strings.delete)) {
      return;
    }
    try {
      const url = buildRoute(routes.delete ?? `${config.endpointBase}/files/${placeholder}`, { id }, placeholder);
      await jsonClient(url, { method: 'DELETE' });
      setStatus(strings.movedToTrash || strings.delete);
      await fetchFiles();
      emitEvent('file-uploader:deleted', { id });
    } catch (error) {
      console.warn(error);
      setStatus(strings.failed, 'error');
    }
  }

  async function restoreFile(id: string): Promise<void> {
    try {
      const url = buildRoute(
        routes.restore ?? `${config.endpointBase}/files/${placeholder}/restore`,
        { id },
        placeholder,
      );
      await jsonClient(url, { method: 'POST' });
      await fetchFiles();
    } catch (error) {
      console.warn(error);
      setStatus(strings.failed, 'error');
    }
  }

  async function processQueue(): Promise<void> {
    if (uploading) {
      return;
    }
    const file = queue.shift();
    if (!file) {
      updateQueueVisibility();
      return;
    }
    const entry = getEntry(file);
    uploading = true;

    const uploadUrl = buildRoute(routes.upload ?? `${config.endpointBase}/chunks`, {}, placeholder);
    const uploadHeaders: Record<string, string> = { ...headers };
    if ((config.auth === 'csrf' || config.auth === undefined) && token) {
      uploadHeaders['X-CSRF-TOKEN'] = token;
    }
    if (config.auth === 'bearer' && token) {
      uploadHeaders.Authorization = uploadHeaders.Authorization ?? `Bearer ${token}`;
    }

    setStatus(formatString(strings.uploading, { name: file.name }));
    setProgress(entry, 0);

    try {
      const metadata = (await uploadFile<FileRow | undefined>(file, {
        url: uploadUrl,
        method: config.method,
        chunkSize: config.chunkSize,
        headers: uploadHeaders,
        credentials: 'same-origin',
        locale: config.locale,
        fileName: file.name,
        extraFields:
          (config.auth === 'csrf' || config.auth === undefined) && token ? { [config.csrfTokenField]: token } : {},
        onProgress: ({ overall }) => setProgress(entry, overall * 100),
      })) as FileRow | undefined;

      setProgress(entry, 100);
      setStatus(strings.completed);
      if (config.allowList) {
        await fetchFiles();
      }
      markSuccess(entry, `${strings.completed}: ${file.name}`, metadata?.url ?? null);
      emitEvent('file-uploader:success', { file: file.name, metadata });
    } catch (error) {
      console.warn(error);
      setStatus(strings.failed, 'error');
      markError(entry, `${strings.failed}: ${file.name}`);
      emitEvent('file-uploader:error', { file: file.name, error });
    } finally {
      uploading = false;
      void processQueue();
    }
  }

  function enqueue(files: FileList | File[] | null): void {
    const incoming = Array.from(files ?? []);
    for (const file of incoming) {
      getEntry(file);
    }
    if (incoming.length) {
      queue.push(...incoming);
      void processQueue();
    }
  }

  // --- events ---
  toggle.addEventListener('click', () => {
    isOpen = !isOpen;
    modal.classList.toggle('fu-open', isOpen);
  });
  close.addEventListener('click', () => {
    isOpen = false;
    modal.classList.toggle('fu-open', false);
  });
  input.addEventListener('change', () => {
    enqueue(input.files);
    input.value = '';
  });
  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('fu-active');
  });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('fu-active'));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('fu-active');
    if (e.dataTransfer?.files) {
      enqueue(e.dataTransfer.files);
    }
  });
  dropzone.addEventListener('click', () => input.click());

  if (config.allowList) {
    toggleListBtn.addEventListener('click', () => {
      listOpen = !listOpen;
      listContainer.classList.toggle('fu-open', listOpen);
      toggleListBtn.textContent = listOpen ? strings.hideList : strings.showList;
      if (listOpen) {
        void fetchFiles();
      }
    });
    refreshButton.addEventListener('click', () => void fetchFiles());
  }

  return {
    root,
    refresh: fetchFiles,
    destroy: () => root.remove(),
  };
}

function el(tag: string, className: string, attrs?: Record<string, string>): HTMLElement {
  const node = document.createElement(tag);
  if (className) {
    node.className = className;
  }
  if (attrs) {
    for (const [k, v] of Object.entries(attrs)) {
      node.setAttribute(k, v);
    }
  }

  return node;
}
