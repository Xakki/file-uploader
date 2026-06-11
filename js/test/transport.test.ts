import { afterEach, describe, expect, it, vi } from 'vitest';
import { UploadError } from '../src/core/errors';
import { xhrTransport, type TransportRequest } from '../src/core/transport';

interface ProgressLike {
  lengthComputable: boolean;
  loaded: number;
  total: number;
}

class FakeXHR {
  static last: FakeXHR | null = null;

  method = '';

  url = '';

  withCredentials = false;

  headers: Record<string, string> = {};

  status = 0;

  responseText = '';

  responseURL = '';

  aborted = false;

  onload: (() => void) | null = null;

  onerror: (() => void) | null = null;

  onabort: (() => void) | null = null;

  upload: { onprogress: ((e: ProgressLike) => void) | null } = { onprogress: null };

  open(method: string, url: string): void {
    this.method = method;
    this.url = url;
  }

  setRequestHeader(name: string, value: string): void {
    this.headers[name] = value;
  }

  send(): void {
    FakeXHR.last = this;
  }

  abort(): void {
    this.aborted = true;
    this.onabort?.();
  }
}

function request(overrides: Partial<TransportRequest> = {}): TransportRequest {
  return { url: '/upload', method: 'POST', headers: {}, body: new FormData(), ...overrides };
}

describe('xhrTransport', () => {
  afterEach(() => {
    FakeXHR.last = null;
    vi.unstubAllGlobals();
  });

  it('resolves with status, body and final url on load, and sets headers', async () => {
    vi.stubGlobal('XMLHttpRequest', FakeXHR);
    const promise = xhrTransport(request({ headers: { 'X-Test': '1' } }));

    const xhr = FakeXHR.last!;
    expect(xhr.method).toBe('POST');
    expect(xhr.headers['X-Test']).toBe('1');
    xhr.status = 200;
    xhr.responseText = 'OK';
    xhr.responseURL = '/final';
    xhr.onload!();

    await expect(promise).resolves.toEqual({ status: 200, body: 'OK', url: '/final' });
  });

  it('rejects with a retryable UploadError on network error', async () => {
    vi.stubGlobal('XMLHttpRequest', FakeXHR);
    const promise = xhrTransport(request());
    FakeXHR.last!.onerror!();

    await expect(promise).rejects.toBeInstanceOf(UploadError);
    await expect(promise).rejects.toMatchObject({ status: 0, retryable: true });
  });

  it('rejects with a non-retryable UploadError on abort', async () => {
    vi.stubGlobal('XMLHttpRequest', FakeXHR);
    const promise = xhrTransport(request());
    FakeXHR.last!.onabort!();

    await expect(promise).rejects.toMatchObject({ status: 0, retryable: false });
  });

  it('forwards upload progress events', async () => {
    vi.stubGlobal('XMLHttpRequest', FakeXHR);
    const seen: Array<[number, number]> = [];
    const promise = xhrTransport(request({ onUploadProgress: (loaded, total) => seen.push([loaded, total]) }));

    const xhr = FakeXHR.last!;
    xhr.upload.onprogress!({ lengthComputable: true, loaded: 5, total: 10 });
    xhr.status = 200;
    xhr.onload!();
    await promise;

    expect(seen).toEqual([[5, 10]]);
  });

  it('enables withCredentials when credentials are "include"', async () => {
    vi.stubGlobal('XMLHttpRequest', FakeXHR);
    const promise = xhrTransport(request({ credentials: 'include' }));

    const xhr = FakeXHR.last!;
    expect(xhr.withCredentials).toBe(true);
    xhr.status = 204;
    xhr.onload!();
    await promise;
  });

  it('aborts immediately when the signal is already aborted', async () => {
    vi.stubGlobal('XMLHttpRequest', FakeXHR);
    const controller = new AbortController();
    controller.abort();

    await expect(xhrTransport(request({ signal: controller.signal }))).rejects.toMatchObject({ retryable: false });
  });
});
