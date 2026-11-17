// Inject browser globals to avoid ReferenceError when importing browser-targeted files in Node
(globalThis as any).window = {
  __APP_CONFIG__: {
    apiUrl: 'http://localhost/processos/api/v2/',
    baseUrl: 'http://localhost/processos/'
  },
  BASE_URL: 'http://localhost/processos/'
};

(globalThis as any).document = {
  querySelector: (selector: string) => {
    if (selector === 'meta[name="csrf-token"]') {
      return { content: 'csrf-token-mock-value' };
    }
    return null;
  }
};
