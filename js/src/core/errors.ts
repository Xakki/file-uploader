/**
 * Error raised when a chunk request fails. `status` is the HTTP status (0 for
 * network/transport failures). `retryable` reflects Upload Protocol v1 §4:
 * network errors and 5xx are retryable; 4xx are not.
 */
export class UploadError extends Error {
  readonly status: number;

  readonly retryable: boolean;

  readonly body?: string;

  constructor(message: string, status: number, retryable: boolean, body?: string) {
    super(message);
    this.name = 'UploadError';
    this.status = status;
    this.retryable = retryable;
    this.body = body;
  }
}
