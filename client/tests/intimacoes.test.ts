import './setup-globals';
import { describe, it, expect, vi } from 'vitest';

async function syncUrlsConcurrently(urls, concurrencyLimit, fetchFn, processFn) {
  let sucessos = 0;
  let falhas = 0;
  let totalProcessadas = 0;

  let index = 0;

  async function worker() {
    while (index < urls.length) {
      const url = urls[index++];
      try {
        const response = await fetchFn(url);
        if (!response.ok) {
           throw new Error(`Erro na API externa: ${response.status}`);
        }
        const data = await response.json();

        if (data.items && data.items.length > 0) {
           const sendRes = await processFn('intimacoes/processarIntimacoes', {
             method: 'POST',
             body: data
           });
           if (sendRes && sendRes.success) {
             sucessos++;
             totalProcessadas += (sendRes.processadas || 0);
           } else {
             falhas++;
           }
        } else {
           sucessos++;
        }
      } catch(e) {
        falhas++;
      }
    }
  }

  const workerCount = Math.min(concurrencyLimit, urls.length);
  const workers = Array.from({ length: workerCount }, () => worker());
  await Promise.all(workers);

  return { sucessos, falhas, totalProcessadas };
}

async function syncUrlsSerially(urls, fetchFn, processFn) {
  let sucessos = 0;
  let falhas = 0;
  let totalProcessadas = 0;

  for (const url of urls) {
    try {
      const response = await fetchFn(url);
      if (!response.ok) {
         throw new Error(`Erro na API externa: ${response.status}`);
      }
      const data = await response.json();

      if (data.items && data.items.length > 0) {
         const sendRes = await processFn('intimacoes/processarIntimacoes', {
           method: 'POST',
           body: data
         });
         if (sendRes && sendRes.success) {
           sucessos++;
           totalProcessadas += (sendRes.processadas || 0);
         } else {
           falhas++;
         }
      } else {
         sucessos++;
      }
    } catch(e) {
      falhas++;
    }
  }

  return { sucessos, falhas, totalProcessadas };
}

describe('Intimações sync URL concurrency benchmark & verification', () => {
  it('should restrict concurrent fetch calls to concurrency limit of 5', async () => {
    let activeRequests = 0;
    let maxActiveRequests = 0;

    const mockFetch = vi.fn().mockImplementation(async (url) => {
      activeRequests++;
      if (activeRequests > maxActiveRequests) {
        maxActiveRequests = activeRequests;
      }
      await new Promise((resolve) => setTimeout(resolve, 20));
      activeRequests--;
      return {
        ok: true,
        json: async () => ({ items: [{ id: 1 }] })
      };
    });

    const mockProcess = vi.fn().mockImplementation(async () => {
      return { success: true, processadas: 1 };
    });

    const urls = Array.from({ length: 15 }, (_, i) => `https://djen.jus.br/api/v1/intimacoes?advogado=${i}`);

    const result = await syncUrlsConcurrently(urls, 5, mockFetch, mockProcess);

    expect(result.sucessos).toBe(15);
    expect(result.falhas).toBe(0);
    expect(result.totalProcessadas).toBe(15);
    expect(maxActiveRequests).toBeLessThanOrEqual(5);
  });

  it('demonstrates measurable speedup of concurrent workers over serial loop', async () => {
    const mockDelay = 30; // 30ms network delay per URL
    const mockFetch = vi.fn().mockImplementation(async (url) => {
      await new Promise((resolve) => setTimeout(resolve, mockDelay));
      return {
        ok: true,
        json: async () => ({ items: [] })
      };
    });

    const urls = Array.from({ length: 10 }, (_, i) => `https://djen.jus.br/api/v1/intimacoes?advogado=${i}`);

    const startSerial = Date.now();
    await syncUrlsSerially(urls, mockFetch, null);
    const serialDuration = Date.now() - startSerial;

    const startConcurrent = Date.now();
    await syncUrlsConcurrently(urls, 5, mockFetch, null);
    const concurrentDuration = Date.now() - startConcurrent;

    expect(concurrentDuration).toBeLessThan(serialDuration);
    // 10 items * 30ms serial = ~300ms. Concurrent with 5 workers = ~60ms.
    // Speedup should be roughly ~3-5x.
    expect(serialDuration / concurrentDuration).toBeGreaterThan(2);
  });
});
