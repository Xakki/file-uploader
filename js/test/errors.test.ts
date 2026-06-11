import { describe, expect, it } from 'vitest';
import { UploadError } from '../src/core/errors';

describe('UploadError', () => {
  it('captures status, retryability and an optional body', () => {
    const err = new UploadError('boom', 503, true, 'server exploded');

    expect(err).toBeInstanceOf(Error);
    expect(err.name).toBe('UploadError');
    expect(err.message).toBe('boom');
    expect(err.status).toBe(503);
    expect(err.retryable).toBe(true);
    expect(err.body).toBe('server exploded');
  });

  it('leaves the body undefined when omitted', () => {
    const err = new UploadError('nope', 422, false);

    expect(err.body).toBeUndefined();
    expect(err.retryable).toBe(false);
  });
});
