import { UploadError } from './errors';

export interface TransportRequest {
  url: string;
  method: string;
  headers: Record<string, string>;
  body: FormData;
  credentials?: 'omit' | 'same-origin' | 'include';
  signal?: AbortSignal;
  onUploadProgress?: (loaded: number, total: number) => void;
}

export interface TransportResponse {
  status: number;
  body: string;
  /** Final URL after redirects, when the transport can report it. */
  url?: string;
}

/** Pluggable transport so the core runs in browsers (XHR) and tests (mock). */
export type Transport = (req: TransportRequest) => Promise<TransportResponse>;

/**
 * Default browser transport built on XMLHttpRequest so upload progress is
 * available. Rejects with a retryable UploadError on network failure.
 */
export const xhrTransport: Transport = (req) =>
  new Promise<TransportResponse>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open(req.method, req.url, true);

    if (req.credentials === 'include') {
      xhr.withCredentials = true;
    }

    for (const [name, value] of Object.entries(req.headers)) {
      if (value !== undefined && value !== null) {
        xhr.setRequestHeader(name, value);
      }
    }

    if (req.onUploadProgress) {
      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          req.onUploadProgress!(event.loaded, event.total);
        }
      };
    }

    xhr.onload = () => {
      resolve({ status: xhr.status, body: xhr.responseText, url: xhr.responseURL || undefined });
    };
    xhr.onerror = () => reject(new UploadError('Network error during chunk upload.', 0, true));
    xhr.onabort = () => reject(new UploadError('Upload aborted.', 0, false));

    if (req.signal) {
      if (req.signal.aborted) {
        xhr.abort();
      } else {
        req.signal.addEventListener('abort', () => xhr.abort(), { once: true });
      }
    }

    xhr.send(req.body);
  });
