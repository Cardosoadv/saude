import { describe, it, expect } from 'vitest';
import { deflateRawSync } from 'node:zlib';
import { lerManifestoDoZip } from '../src/utils/zipManifesto';

interface Arq { nome: string; dados: Buffer; deflate?: boolean }

/** Monta um .zip mínimo (cabeçalhos locais + diretório central + EOCD), sem CRC (a leitura não o confere). */
function zip(arquivos: Arq[]): Blob {
  const locais: Buffer[] = [];
  const centrais: Buffer[] = [];
  let offset = 0;
  for (const a of arquivos) {
    const nome = Buffer.from(a.nome);
    const dados = a.deflate ? deflateRawSync(a.dados) : a.dados;
    const metodo = a.deflate ? 8 : 0;

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(metodo, 8);
    local.writeUInt32LE(dados.length, 18);
    local.writeUInt32LE(a.dados.length, 22);
    local.writeUInt16LE(nome.length, 26);
    locais.push(local, nome, dados);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(metodo, 10);
    central.writeUInt32LE(dados.length, 20);
    central.writeUInt32LE(a.dados.length, 24);
    central.writeUInt16LE(nome.length, 28);
    central.writeUInt32LE(offset, 42);
    centrais.push(central, nome);
    offset += 30 + nome.length + dados.length;
  }
  const diretorio = Buffer.concat(centrais);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(arquivos.length, 8);
  eocd.writeUInt16LE(arquivos.length, 10);
  eocd.writeUInt32LE(diretorio.length, 12);
  eocd.writeUInt32LE(offset, 16);
  return new Blob([Buffer.concat([...locais, diretorio, eocd])]);
}

const manifesto = (extra: Record<string, unknown> = {}) =>
  Buffer.from(JSON.stringify({ slug: 'moderno', nome: 'Moderno', versao: '1.2.0', autor: 'Estúdio', motor: 'twig', ...extra }));

describe('lerManifestoDoZip', () => {
  it('lê o template.json na raiz, sem compressão', async () => {
    const m = await lerManifestoDoZip(zip([{ nome: 'layouts/main.twig', dados: Buffer.from('x') }, { nome: 'template.json', dados: manifesto() }]));

    expect(m).toEqual({ slug: 'moderno', nome: 'Moderno', versao: '1.2.0', autor: 'Estúdio', motor: 'twig' });
  });

  it('lê com compressão deflate', async () => {
    const m = await lerManifestoDoZip(zip([{ nome: 'template.json', dados: manifesto({ nome: 'Clássico Ç' }), deflate: true }]));

    expect(m?.nome).toBe('Clássico Ç');
  });

  it('aceita uma única pasta no topo', async () => {
    const m = await lerManifestoDoZip(zip([{ nome: 'moderno-v1/template.json', dados: manifesto(), deflate: true }]));

    expect(m?.slug).toBe('moderno');
  });

  it('não confunde com template.json mais fundo na árvore', async () => {
    expect(await lerManifestoDoZip(zip([{ nome: 'a/b/template.json', dados: manifesto() }]))).toBeNull();
  });

  it('devolve null para o que não dá para ler', async () => {
    expect(await lerManifestoDoZip(new Blob(['isto não é um zip']))).toBeNull();
    expect(await lerManifestoDoZip(new Blob([]))).toBeNull();
    expect(await lerManifestoDoZip(zip([{ nome: 'layouts/main.twig', dados: Buffer.from('x') }]))).toBeNull();
    expect(await lerManifestoDoZip(zip([{ nome: 'template.json', dados: Buffer.from('{quebrado') }]))).toBeNull();
    expect(await lerManifestoDoZip(zip([{ nome: 'template.json', dados: Buffer.from(JSON.stringify({ nome: 'sem slug' })) }]))).toBeNull();
  });
});
