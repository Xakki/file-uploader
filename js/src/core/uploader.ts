import { UploadError } from './errors';
import { wholeFileSha256 } from './hash';
import { type Transport, type TransportRequest, type TransportResponse, xhrTransport } from './transport';
import type { ChunkContext, FieldNames, UploadOptions } from './types';

const DEFAULT_FIELDS: FieldNames = {
  fileChunk: 'fileChunk',
  chunkIndex: 'chunkIndex',
  totalChunks: 'totalChunks',
  fileSize: 'fileSize',
  uploadId: 'uploadId',
  fileName: 'fileName',
  mimeType: 'mimeType',
  fileHash: 'fileHash',
  fileLastModified: 'fileLastModified',
  locale: 'locale',
};

const DEFAULTS = {
  chunkSize: 1024 * 1024,
  method: 'POST',
  maxRetries: 3,
  retryDelay: 3000,
  retryDelayIncrement: 1000,
  maxRetryDelay: 20000,
};

/** `upload-{epochMillis13}-{rand8}` per Upload Protocol v1 §2. */
export function generateUploadId(): string {
  const rand = Math.random().toString(36).slice(2, 10).padEnd(8, '0').slice(0, 8);

  return `upload-${Date.now()}-${rand}`;
}

function sleep(ms: number, signal?: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(resolve, ms);
    signal?.addEventListener(
      'abort',
      () => {
        clearTimeout(timer);
        reject(new UploadError('Upload aborted.', 0, false));
      },
      { once: true },
    );
  });
}

function parseJson(body: string): unknown {
  if (!body) {
    return undefined;
  }
  try {
    return JSON.parse(body) as unknown;
  } catch {
    return undefined;
  }
}

function buildForm(
  chunk: Blob,
  ctx: ChunkContext,
  fields: FieldNames,
  options: UploadOptions,
): FormData {
  const form = new FormData();
  form.append(fields.fileChunk, chunk, ctx.fileName);
  form.append(fields.chunkIndex, String(ctx.chunkIndex));
  form.append(fields.totalChunks, String(ctx.totalChunks));
  form.append(fields.fileSize, String(ctx.fileSize));
  form.append(fields.uploadId, ctx.uploadId);
  form.append(fields.fileName, ctx.fileName);
  form.append(fields.mimeType, ctx.mimeType);
  form.append(fields.fileLastModified, String(ctx.fileLastModified));
  if (ctx.fileHash) {
    form.append(fields.fileHash, ctx.fileHash);
  }
  if (options.locale != null) {
    form.append(fields.locale, options.locale);
  }

  const extra = typeof options.extraFields === 'function' ? options.extraFields(ctx) : options.extraFields;
  if (extra) {
    for (const [key, value] of Object.entries(extra)) {
      if (value != null) {
        form.append(key, value);
      }
    }
  }

  options.formDataBuilder?.(form, { ...ctx, chunk });

  return form;
}

async function sendWithRetries(
  transport: Transport,
  req: TransportRequest,
  options: Required<Pick<UploadOptions, 'maxRetries' | 'retryDelay' | 'retryDelayIncrement' | 'maxRetryDelay'>>,
  signal?: AbortSignal,
): Promise<TransportResponse> {
  let attempt = 0;
  let delay = options.retryDelay;

  for (;;) {
    attempt += 1;
    try {
      const res = await transport(req);
      if (res.status >= 200 && res.status < 300) {
        return res;
      }
      // Upload Protocol v1 §4: retry 5xx, never 4xx.
      const retryable = res.status >= 500;
      const err = new UploadError(`Server responded with status ${res.status}.`, res.status, retryable, res.body);
      if (!retryable || attempt >= options.maxRetries) {
        throw err;
      }
    } catch (e) {
      const err = e instanceof UploadError ? e : new UploadError(String(e), 0, true);
      if (!err.retryable || attempt >= options.maxRetries) {
        throw err;
      }
    }

    await sleep(delay, signal);
    delay = Math.min(delay + options.retryDelayIncrement, options.maxRetryDelay);
  }
}

/**
 * Upload a Blob/File in chunks per Upload Protocol v1. Backend-agnostic: point
 * `url` at any conforming endpoint and customise field names / completion /
 * result extraction as needed. Returns the extracted result of the final
 * (completed) response.
 */
export async function uploadFile<TResult = unknown>(
  blob: Blob,
  options: UploadOptions<TResult>,
  transport: Transport = xhrTransport,
): Promise<TResult> {
  if (!options.url) {
    throw new UploadError('options.url is required.', 0, false);
  }

  const fields: FieldNames = { ...DEFAULT_FIELDS, ...options.fieldNames };
  const chunkSize = options.chunkSize ?? DEFAULTS.chunkSize;
  const method = options.method ?? DEFAULTS.method;
  const retry = {
    maxRetries: options.maxRetries ?? DEFAULTS.maxRetries,
    retryDelay: options.retryDelay ?? DEFAULTS.retryDelay,
    retryDelayIncrement: options.retryDelayIncrement ?? DEFAULTS.retryDelayIncrement,
    maxRetryDelay: options.maxRetryDelay ?? DEFAULTS.maxRetryDelay,
  };

  const uploadId = options.uploadId ?? generateUploadId();
  const mimeType = options.mimeType ?? (blob.type || 'application/octet-stream');
  const fileName = options.fileName ?? ((blob as File).name || uploadId);
  const fileSize = blob.size;
  const totalChunks = Math.max(1, Math.ceil(fileSize / chunkSize));
  const fileLastModified = (blob as File).lastModified ?? 0;
  const fileHash = options.computeFileHash ? await wholeFileSha256(blob) : '';

  const isCompleted =
    options.isCompleted ?? ((json) => (json as { data?: { completed?: unknown } })?.data?.completed === true);
  const extractResult =
    options.extractResult ??
    ((json) => (json as { data?: { metadata?: unknown } })?.data?.metadata as TResult);

  for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
    if (options.signal?.aborted) {
      throw new UploadError('Upload aborted.', 0, false);
    }

    const start = chunkIndex * chunkSize;
    const end = Math.min(start + chunkSize, fileSize);
    const chunk = blob.slice(start, end);
    const ctx: ChunkContext = {
      uploadId,
      chunkIndex,
      totalChunks,
      fileName,
      mimeType,
      fileSize,
      fileHash,
      fileLastModified,
      isLastChunk: chunkIndex === totalChunks - 1,
    };

    const req: TransportRequest = {
      url: options.url,
      method,
      headers: typeof options.headers === 'function' ? options.headers() : { ...options.headers },
      body: buildForm(chunk, ctx, fields, options),
      credentials: options.credentials,
      signal: options.signal,
      onUploadProgress: options.onProgress
        ? (loaded, total) =>
            options.onProgress!({
              uploadId,
              chunkIndex,
              totalChunks,
              loaded,
              total,
              overall: (chunkIndex + (total ? loaded / total : 1)) / totalChunks,
            })
        : undefined,
    };

    const res = await sendWithRetries(transport, req, retry, options.signal);
    options.onProgress?.({
      uploadId,
      chunkIndex,
      totalChunks,
      loaded: chunk.size,
      total: chunk.size,
      overall: (chunkIndex + 1) / totalChunks,
    });

    const json = parseJson(res.body);
    if (isCompleted(json) || ctx.isLastChunk) {
      return extractResult(json);
    }
  }

  throw new UploadError('Upload finished without a completion response.', 0, false);
}
