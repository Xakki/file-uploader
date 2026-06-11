/**
 * Lowercase hex sha256 of an entire Blob, computed once. Returns '' when the
 * WebCrypto SubtleCrypto API is unavailable (e.g. insecure browser context),
 * in which case the protocol treats fileHash as absent.
 */
export async function wholeFileSha256(blob: Blob): Promise<string> {
  const subtle = globalThis.crypto?.subtle;
  if (!subtle || typeof blob.arrayBuffer !== 'function') {
    return '';
  }

  const buffer = await blob.arrayBuffer();
  const digest = await subtle.digest('SHA-256', buffer);

  return Array.from(new Uint8Array(digest))
    .map((b) => b.toString(16).padStart(2, '0'))
    .join('');
}
