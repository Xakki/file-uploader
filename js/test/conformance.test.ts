import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import type { Transport, TransportRequest } from '../src/core/transport';
import { uploadFile } from '../src/core/uploader';

interface FixtureFile {
  name: string;
  mime: string;
  sizeBytes: number;
  fillByte: number;
  lastModified: number;
}

interface FixtureExpect {
  status: number;
  success: boolean;
  data?: { completed?: boolean; metadata?: Record<string, unknown> };
  errors?: string[];
}

interface FixtureRequest {
  fields: Record<string, string | number>;
  chunkBytes: number;
  expect: FixtureExpect;
}

interface Fixture {
  name: string;
  file: FixtureFile;
  requests: FixtureRequest[];
}

const fixturesDir = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'protocol', 'fixtures');

const fixtures: Fixture[] = readdirSync(fixturesDir)
  .filter((f) => f.endsWith('.json'))
  .map((f) => JSON.parse(readFileSync(join(fixturesDir, f), 'utf8')) as Fixture);

// The fixtures are server scenarios. The negative ones exercise server-side validation
// (a missing/invalid field) that the client cannot reproduce — the client always sends a
// well-formed request — so the client suite runs only the all-success scenarios. The PHP
// server suites cover the negative fixtures.
const clientRunnable = fixtures.filter((fx) => fx.requests.every((r) => r.expect.success === true));

describe('Upload Protocol v1 — client conformance', () => {
  it('has positive fixtures to run', () => {
    expect(clientRunnable.length).toBeGreaterThanOrEqual(2);
  });

  for (const fx of clientRunnable) {
    it(fx.name, async () => {
      const { file } = fx;
      const blob = new File([new Uint8Array(file.sizeBytes).fill(file.fillByte)], file.name, {
        type: file.mime,
        lastModified: file.lastModified,
      });

      const calls: TransportRequest[] = [];
      const transport: Transport = async (req) => {
        const step = fx.requests[calls.length]!;
        calls.push(req);

        return {
          status: step.expect.status,
          body: JSON.stringify({ success: step.expect.success, message: 'ok', data: step.expect.data }),
        };
      };

      const result = await uploadFile(
        blob,
        { url: '/file-upload/chunks', chunkSize: fx.requests[0]!.chunkBytes, uploadId: String(fx.requests[0]!.fields.uploadId) },
        transport,
      );

      // The client produced exactly the fixture's request sequence...
      expect(calls).toHaveLength(fx.requests.length);
      fx.requests.forEach((step, i) => {
        const body = calls[i]!.body;
        for (const [key, value] of Object.entries(step.fields)) {
          expect(String(body.get(key))).toBe(String(value));
        }
        const chunk = body.get('fileChunk');
        expect(chunk).toBeInstanceOf(Blob);
        expect((chunk as Blob).size).toBe(step.chunkBytes);
      });

      // ...and returned the metadata from the completing response.
      const finalMetadata = fx.requests[fx.requests.length - 1]!.expect.data?.metadata;
      if (finalMetadata) {
        expect(result).toMatchObject(finalMetadata);
      }
    });
  }
});
