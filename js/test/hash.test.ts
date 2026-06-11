import { createHash } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { wholeFileSha256 } from '../src/core/hash';

describe('wholeFileSha256', () => {
  it('returns the lowercase hex sha256 of the blob contents', async () => {
    const bytes = new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8]);
    const expected = createHash('sha256').update(Buffer.from(bytes)).digest('hex');

    expect(await wholeFileSha256(new Blob([bytes]))).toBe(expected);
  });

  it('returns an empty string when the blob cannot expose an arrayBuffer', async () => {
    const fake = {} as unknown as Blob;

    expect(await wholeFileSha256(fake)).toBe('');
  });
});
