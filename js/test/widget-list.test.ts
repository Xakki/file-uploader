// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { STRINGS } from '../src/widget/strings';
import type { FileRow } from '../src/widget/types';
import { createWidget } from '../src/widget/widget';

function stubFetch(payload: unknown): void {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => ({ ok: true, status: 200, json: async () => payload })),
  );
}

function row(overrides: Partial<FileRow> = {}): FileRow {
  return {
    id: 'f1',
    name: 'doc.pdf',
    size: 1536,
    mime: 'application/pdf',
    url: 'https://example.test/doc.pdf',
    createdAt: '2026-01-01T00:00:00Z',
    deletedAt: null,
    lastModified: null,
    own: true,
    ...overrides,
  };
}

describe('createWidget (rendering)', () => {
  // Default fetch stub so createWidget's mount-time fetchFiles() (allowList defaults on)
  // never hits the real network. Tests that need a specific payload override via stubFetch.
  beforeEach(() => {
    stubFetch({ data: { files: [] } });
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('renders the Russian locale strings', () => {
    const widget = createWidget({ locale: 'ru' });

    expect(widget.root.querySelector('.fu-title')?.textContent).toBe(STRINGS.ru!.title);
  });

  it('applies i18n overrides over the base locale', () => {
    const widget = createWidget({ i18n: { en: { title: 'My Uploader' } } });

    expect(widget.root.querySelector('.fu-title')?.textContent).toBe('My Uploader');
  });

  it('mounts into a provided container', () => {
    const host = document.createElement('div');
    document.body.appendChild(host);

    const widget = createWidget({ container: host });

    expect(widget.root).toBe(host);
    expect(host.classList.contains('fu-widget')).toBe(true);
  });

  it('renders a row per file with human size, link and management actions', async () => {
    stubFetch({
      data: {
        files: [
          row(),
          row({ id: 'f2', name: 'old.txt', size: 0, url: null, createdAt: '', deletedAt: '2026-02-02T00:00:00Z' }),
        ],
      },
    });

    const widget = createWidget({ allowList: true, allowDelete: true, allowDeleteAllFiles: true, allowCleanup: true });
    await widget.refresh();

    const rows = widget.root.querySelectorAll('.fu-table tbody tr');
    expect(rows).toHaveLength(2);
    expect(rows[0]!.querySelector('a')?.textContent).toBe('doc.pdf');
    expect(rows[0]!.querySelectorAll('td')[1]?.textContent).toBe('1.5 KB');
    expect(widget.root.querySelectorAll('tbody button[aria-label]')).toHaveLength(2);
    expect(widget.root.querySelector('tbody')?.textContent).toContain(STRINGS.en!.restore);
  });

  it('hides delete actions when the user may not manage the file', async () => {
    stubFetch({ data: { files: [row({ own: false, url: null })] } });

    const widget = createWidget({ allowList: true, allowDelete: true, allowDeleteAllFiles: false, allowCleanup: false });
    await widget.refresh();

    expect(widget.root.querySelectorAll('tbody button[aria-label]')).toHaveLength(0);
  });

  it('toggles the modal open and closed', () => {
    const widget = createWidget();
    const modal = widget.root.querySelector('.fu-modal')!;
    const toggle = widget.root.querySelector('.fu-toggle') as HTMLElement;
    const close = widget.root.querySelector('.fu-close') as HTMLElement;

    toggle.click();
    expect(modal.classList.contains('fu-open')).toBe(true);

    close.click();
    expect(modal.classList.contains('fu-open')).toBe(false);
  });
});
