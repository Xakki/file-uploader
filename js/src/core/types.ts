/**
 * Upload Protocol v1 — headless client types. See /protocol/SPEC.md.
 */

/** Protocol field names. Override only if your backend renames them. */
export interface FieldNames {
  fileChunk: string;
  chunkIndex: string;
  totalChunks: string;
  fileSize: string;
  uploadId: string;
  fileName: string;
  mimeType: string;
  fileHash: string;
  fileLastModified: string;
  locale: string;
}

export interface ProgressEvent {
  uploadId: string;
  chunkIndex: number;
  totalChunks: number;
  /** Bytes uploaded for the current chunk (when the transport reports it). */
  loaded: number;
  /** Total bytes of the current chunk (when the transport reports it). */
  total: number;
  /** 0..1 across the whole file. */
  overall: number;
}

export interface ChunkContext {
  uploadId: string;
  chunkIndex: number;
  totalChunks: number;
  fileName: string;
  mimeType: string;
  fileSize: number;
  fileHash: string;
  fileLastModified: number;
  isLastChunk: boolean;
}

export type Headers = Record<string, string>;

export interface UploadOptions<TResult = unknown> {
  /** Chunk endpoint URL ({prefix}/chunks). */
  url: string;
  /** Bytes per chunk. Default 1 MiB. */
  chunkSize?: number;
  /** HTTP method. Default POST. */
  method?: string;
  /** Static headers, or a factory invoked per request. */
  headers?: Headers | (() => Headers);
  /** Sent with the browser-managed cookies when using the default transport. */
  credentials?: 'omit' | 'same-origin' | 'include';
  /** Override protocol field names (partial). */
  fieldNames?: Partial<FieldNames>;
  /** Extra form fields, static or computed per chunk. */
  extraFields?: Headers | ((ctx: ChunkContext) => Headers);
  /** Fully customise the multipart body. Receives the prepared FormData + context. */
  formDataBuilder?: (form: FormData, ctx: ChunkContext & { chunk: Blob }) => void;
  /** Compute the WHOLE-FILE sha256 once and send it as fileHash (enables server dedup/verify). Default false. */
  computeFileHash?: boolean;
  /** Provide an explicit uploadId (must match the protocol pattern). Generated otherwise. */
  uploadId?: string;
  /** Override the file name / mime sent to the server. */
  fileName?: string;
  mimeType?: string;
  locale?: string;
  /** Retry policy (network errors and 5xx only — never 4xx). */
  maxRetries?: number;
  retryDelay?: number;
  retryDelayIncrement?: number;
  maxRetryDelay?: number;
  signal?: AbortSignal;
  onProgress?: (p: ProgressEvent) => void;
  /** Decide whether a parsed response envelope means the upload finished. Default: data.completed === true. */
  isCompleted?: (json: unknown) => boolean;
  /** Extract the result from the final parsed envelope. Default: data.metadata. */
  extractResult?: (json: unknown) => TResult;
}
