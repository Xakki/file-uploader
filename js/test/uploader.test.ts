import { createHash } from 'node:crypto';
import { describe, expect, it } from 'vitest';
import { UploadError } from '../src/core/errors';
import type { Transport, TransportRequest } from '../src/core/transport';
import { uploadFile } from '../src/core/uploader';

function envelope(data: unknown, success = true) {
  return JSON.stringify({ success, message: 'ok', data });
}

describe('uploadFile', () => {
  it('uploads a multi-chunk file and returns the extracted metadata', async () => {
    const blob = new Blob([new Uint8Array(1500)]);
    const calls: TransportRequest[] = [];

    const transport: Transport = async (req) => {
      calls.push(req);
      const idx = Number(req.body.get('chunkIndex'));
      const total = Number(req.body.get('totalChunks'));
      const completed = idx + 1 >= total;
      return {
        status: 200,
        body: envelope(
          completed
            ? { completed: true, metadata: { id: 'abc', name: 'sample.bin', size: 1500, mime: 'application/octet-stream' } }
            : { completed: false },
        ),
      };
    };

    const result = await uploadFile(
      blob,
      { url: '/file-upload/chunks', chunkSize: 1000, fileName: 'sample.bin', mimeType: 'application/octet-stream' },
      transport,
    );

    expect(calls).toHaveLength(2);
    expect(result).toEqual({ id: 'abc', name: 'sample.bin', size: 1500, mime: 'application/octet-stream' });

    const first = calls[0]!.body;
    expect(String(first.get('uploadId'))).toMatch(/^upload-\d{13}-[a-z0-9]{8}$/);
    expect(Number(first.get('totalChunks'))).toBe(2);
    expect(Number(first.get('fileSize'))).toBe(1500);
    expect(first.get('fileName')).toBe('sample.bin');
    expect(first.get('mimeType')).toBe('application/octet-stream');
  });

  it('returns early when the server reports completion (dedup short-circuit)', async () => {
    const blob = new Blob([new Uint8Array(5000)]); // would be 5 chunks at 1000
    let calls = 0;
    const transport: Transport = async () => {
      calls += 1;
      return { status: 200, body: envelope({ completed: true, metadata: { id: 'dedup' } }) };
    };

    const result = await uploadFile(blob, { url: '/c', chunkSize: 1000 }, transport);
    expect(calls).toBe(1);
    expect(result).toEqual({ id: 'dedup' });
  });

  it('does NOT retry on 4xx (Upload Protocol v1 §4)', async () => {
    let calls = 0;
    const transport: Transport = async () => {
      calls += 1;
      return { status: 422, body: envelope({}, false) };
    };

    await expect(
      uploadFile(new Blob([new Uint8Array(10)]), { url: '/c', chunkSize: 1000 }, transport),
    ).rejects.toMatchObject({ status: 422, retryable: false });
    expect(calls).toBe(1);
  });

  it('retries on 5xx then succeeds', async () => {
    let calls = 0;
    const transport: Transport = async () => {
      calls += 1;
      if (calls === 1) {
        return { status: 503, body: '' };
      }
      return { status: 200, body: envelope({ completed: true, metadata: { ok: 1 } }) };
    };

    const result = await uploadFile(
      new Blob([new Uint8Array(500)]),
      { url: '/c', chunkSize: 1000, retryDelay: 1, retryDelayIncrement: 0 },
      transport,
    );
    expect(calls).toBe(2);
    expect(result).toEqual({ ok: 1 });
  });

  it('sends the whole-file sha256 (not per-chunk) when computeFileHash is set', async () => {
    const bytes = new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8]);
    const expected = createHash('sha256').update(Buffer.from(bytes)).digest('hex');
    const seen = new Set<string>();

    const transport: Transport = async (req) => {
      seen.add(String(req.body.get('fileHash')));
      const idx = Number(req.body.get('chunkIndex'));
      const total = Number(req.body.get('totalChunks'));
      return { status: 200, body: envelope(idx + 1 >= total ? { completed: true, metadata: {} } : { completed: false }) };
    };

    await uploadFile(new Blob([bytes]), { url: '/c', chunkSize: 4, computeFileHash: true }, transport);
    // 2 chunks, both carry the identical whole-file hash.
    expect(seen).toEqual(new Set([expected]));
  });

  it('supports a custom result extractor (e.g. a backend-specific id)', async () => {
    const transport: Transport = async () =>
      ({ status: 200, body: envelope({ completed: true, summaryUuid: 'xyz-123' }) });

    const result = await uploadFile<string>(
      new Blob([new Uint8Array(3)]),
      {
        url: '/api/v1/summaries/upload',
        extractResult: (json) => (json as { data?: { summaryUuid?: string } })?.data?.summaryUuid ?? '',
      },
      transport,
    );
    expect(result).toBe('xyz-123');
  });

  it('throws UploadError when url is missing', async () => {
    await expect(uploadFile(new Blob([new Uint8Array(1)]), { url: '' })).rejects.toBeInstanceOf(UploadError);
  });
});
