import { describe, it, expect, vi } from 'vitest';
import { TAMANHO_MAXIMO, enviarVarios, formatarTamanho, validarArquivo } from '../src/utils/midias';

const arq = (name: string, type: string, size = 1000) => ({ name, type, size }) as File;

describe('validarArquivo', () => {
  it('aceita JPEG, PNG, GIF, WebP e ICO dentro do limite', () => {
    for (const tipo of ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon']) {
      expect(validarArquivo(arq('a', tipo))).toBeNull();
    }
    expect(validarArquivo(arq('a.png', 'image/png', TAMANHO_MAXIMO))).toBeNull();
  });

  it('aceita .ico que o navegador informa sem tipo', () => {
    expect(validarArquivo(arq('favicon.ico', ''))).toBeNull();
    expect(validarArquivo(arq('favicon.ICO', 'application/octet-stream'))).toBeNull();
  });

  it('recusa SVG, PDF e tipo desconhecido', () => {
    expect(validarArquivo(arq('logo.svg', 'image/svg+xml'))).toMatch(/JPEG, PNG, GIF, WebP ou ICO/);
    expect(validarArquivo(arq('x.pdf', 'application/pdf'))).toMatch(/"x.pdf"/);
    expect(validarArquivo(arq('semtipo', ''))).not.toBeNull();
  });

  it('recusa acima de 5 MB e arquivo vazio', () => {
    expect(validarArquivo(arq('grande.png', 'image/png', TAMANHO_MAXIMO + 1))).toMatch(/máximo é 5,0 MB/);
    expect(validarArquivo(arq('vazio.png', 'image/png', 0))).toMatch(/vazio/);
  });

  it('formata o tamanho', () => {
    expect(formatarTamanho(512)).toBe('512 B');
    expect(formatarTamanho(2048)).toBe('2 KB');
    expect(formatarTamanho(1572864)).toBe('1,5 MB');
  });
});

describe('enviarVarios', () => {
  it('envia em ordem, pula o inválido sem chamar a API e relata cada resultado', async () => {
    const ordem: string[] = [];
    const enviar = vi.fn(async (a: File) => { ordem.push(a.name); if (a.name === 'quebra.png') throw new Error('falhou'); return { id: ordem.length }; });
    const progresso: string[] = [];

    const res = await enviarVarios(
      [arq('a.png', 'image/png'), arq('b.svg', 'image/svg+xml'), arq('quebra.png', 'image/png'), arq('c.jpg', 'image/jpeg')],
      enviar, (e) => (e as Error).message, (r) => progresso.push(`${r.nome}:${r.ok}`),
    );

    expect(ordem).toEqual(['a.png', 'quebra.png', 'c.jpg']);
    expect(res.map((r) => r.ok)).toEqual([true, false, false, true]);
    expect(res[1].erro).toMatch(/b.svg/);
    expect(res[2].erro).toBe('"quebra.png": falhou');
    expect(progresso).toEqual(['a.png:true', 'b.svg:false', 'quebra.png:false', 'c.jpg:true']);
  });
});
