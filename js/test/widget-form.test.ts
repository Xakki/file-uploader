// @vitest-environment happy-dom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { STRINGS } from '../src/widget/strings';
import type { FileRow } from '../src/widget/types';
import { createForm } from '../src/widget/widget';

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

describe('createForm (inline template)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
  });

  it('mounts into the provided container with the dropzone and visible list', () => {
    stubFetch({ data: { files: [] } });
    const host = document.createElement('div');
    document.body.appendChild(host);

    const form = createForm({ container: host, allowList: true });

    expect(form.root).toBe(host);
    // The inline form is NOT the floating widget — no fixed-position chrome.
    expect(host.classList.contains('fu-widget')).toBe(false);
    expect(host.classList.contains('fu-form')).toBe(true);
    expect(host.querySelector('.fu-dropzone')).not.toBeNull();
    // The list is shown by default — no toggle-to-reveal.
    expect(host.querySelector('.fu-list')?.classList.contains('fu-open')).toBe(true);
  });

  it('renders the file table with a row per file into the container', async () => {
    stubFetch({
      data: {
        files: [row(), row({ id: 'f2', name: 'old.txt', size: 0, url: null })],
      },
    });
    const host = document.createElement('div');
    document.body.appendChild(host);

    const form = createForm({ container: host, allowList: true, allowDelete: true, allowDeleteAllFiles: true });
    await form.refresh();

    const rows = host.querySelectorAll('.fu-table tbody tr');
    expect(rows).toHaveLength(2);
    expect(rows[0]!.querySelector('a')?.textContent).toBe('doc.pdf');
    expect(rows[0]!.querySelectorAll('td')[1]?.textContent).toBe('1.5 KB');
  });

  it('uses the configured locale for the title', () => {
    stubFetch({ data: { files: [] } });
    const host = document.createElement('div');
    document.body.appendChild(host);

    const form = createForm({ container: host, locale: 'ru' });

    expect(host.querySelector('.fu-title')?.textContent).toBe(STRINGS.ru!.title);
  });

  it('throws when no container is provided', () => {
    stubFetch({ data: { files: [] } });
    expect(() => createForm({ allowList: false })).toThrow();
  });
});
