import { describe, it, expect } from 'vitest';
import {
  alterada, arvoreDaApi, arvoreParaEnvio, atualizarItem, avisoDoDestino, avisosDaArvore, contarItens, errosDaApi,
  errosDaArvore, errosDoItem, itemVazio, moverNaLista, removerItem, rotuloDoLocal, tirarDoSubmenu, tornarSubitem, type ItemMenu,
} from '../src/utils/menusSite';
import type { SiteMenuItem } from '../src/types/site';

const API: SiteMenuItem[] = [
  { id: 1, rotulo: 'Áreas', tipo: 'ancora', ancora: 'expertise', pagina_id: null, url: null, destaque: false, filhos: [] },
  { id: 2, rotulo: 'Civil', tipo: 'pagina', pagina_id: 2, url: null, ancora: null, destaque: false, filhos: [
    { id: 5, rotulo: 'Família', tipo: 'pagina', pagina_id: 3, url: null, ancora: null, destaque: false, filhos: [] },
  ] },
  { id: 3, rotulo: 'Consulta Inicial', tipo: 'url', url: '/contato', pagina_id: null, ancora: null, destaque: true, filhos: [] },
];
const mk = (rotulo: string, extra: Partial<ItemMenu> = {}): ItemMenu => ({ ...itemVazio('url'), rotulo, url: '/x', ...extra });

describe('conversão', () => {
  it('lê a árvore da API e devolve só o destino de cada tipo', () => {
    const arvore = arvoreDaApi(API);

    expect(arvore).toHaveLength(3);
    expect(arvore[1].filhos[0]).toMatchObject({ rotulo: 'Família', pagina_id: 3 });
    expect(arvore[2]).toMatchObject({ destaque: true, url: '/contato', pagina_id: null });
    expect(arvoreParaEnvio(arvore)).toEqual([
      { rotulo: 'Áreas', tipo: 'ancora', destaque: false, ancora: 'expertise' },
      { rotulo: 'Civil', tipo: 'pagina', destaque: false, pagina_id: 2, filhos: [{ rotulo: 'Família', tipo: 'pagina', destaque: false, pagina_id: 3 }] },
      { rotulo: 'Consulta Inicial', tipo: 'url', destaque: true, url: '/contato' },
    ]);
  });

  it('trocar o tipo descarta o destino antigo no envio', () => {
    const [a] = arvoreDaApi(API);
    expect(arvoreParaEnvio([{ ...a, tipo: 'url', url: ' https://x.com ' }])).toEqual([{ rotulo: 'Áreas', tipo: 'url', destaque: false, url: 'https://x.com' }]);
  });

  it('detecta alteração só quando o envio muda', () => {
    const inicial = arvoreDaApi(API);
    expect(alterada(inicial, arvoreDaApi(API))).toBe(false);
    expect(alterada(inicial, [{ ...inicial[0], rotulo: 'Outro' }, ...inicial.slice(1)])).toBe(true);
    expect(contarItens(inicial)).toBe(4);
  });

  it('nome do local', () => {
    expect(rotuloDoLocal('cabecalho')).toBe('Cabeçalho');
    expect(rotuloDoLocal('rodape')).toBe('Rodapé');
    expect(rotuloDoLocal('menu_extra')).toBe('Menu extra');
  });
});

describe('edição da árvore', () => {
  const base = [mk('A'), mk('B', { filhos: [mk('B1'), mk('B2')] }), mk('C')];
  const nomes = (l: ItemMenu[]) => l.map((i) => i.rotulo);

  it('move no primeiro nível e nos subitens, sem mexer na entrada', () => {
    expect(nomes(moverNaLista(base, null, 0, 2))).toEqual(['B', 'C', 'A']);
    expect(nomes(moverNaLista(base, 1, 1, 0)[1].filhos)).toEqual(['B2', 'B1']);
    expect(nomes(base)).toEqual(['A', 'B', 'C']);
    expect(nomes(moverNaLista(base, null, 0, 9))).toEqual(['A', 'B', 'C']);
  });

  it('tornar subitem: vai para o item anterior; recusa o primeiro e quem já tem filhos', () => {
    const r = tornarSubitem(base, 2);
    expect(nomes(r)).toEqual(['A', 'B']);
    expect(nomes(r[1].filhos)).toEqual(['B1', 'B2', 'C']);
    expect(tornarSubitem(base, 0)).toBe(base);
    expect(tornarSubitem(base, 1)).toBe(base);
  });

  it('tirar do submenu: o filho vem logo depois do pai', () => {
    const r = tirarDoSubmenu(base, 1, 0);
    expect(nomes(r)).toEqual(['A', 'B', 'B1', 'C']);
    expect(nomes(r[1].filhos)).toEqual(['B2']);
  });

  it('remover e atualizar', () => {
    expect(nomes(removerItem(base, null, 1))).toEqual(['A', 'C']);
    expect(nomes(removerItem(base, 1, 0)[1].filhos)).toEqual(['B2']);
    expect(nomes(atualizarItem(base, null, 0, { ...base[0], rotulo: 'Z' }))).toEqual(['Z', 'B', 'C']);
    expect(atualizarItem(base, null, 1, { ...base[1], rotulo: 'Y', filhos: [] })[1].filhos).toHaveLength(2);
    expect(nomes(atualizarItem(base, 1, 1, mk('B2x'))[1].filhos)).toEqual(['B1', 'B2x']);
  });
});

describe('validação e avisos', () => {
  it('erros por tipo de destino', () => {
    expect(errosDoItem(mk('ok'))).toEqual({});
    expect(errosDoItem({ ...itemVazio('pagina'), rotulo: ' ' })).toEqual({ rotulo: expect.any(String), pagina_id: expect.any(String) });
    expect(errosDoItem(mk('x', { url: 'javascript:alert(1)' })).url).toMatch(/URL inválida/);
    for (const ok of ['https://a.com', 'mailto:a@b.com', 'tel:+5531999', '/noticias']) expect(errosDoItem(mk('x', { url: ok })), ok).toEqual({});
    expect(errosDoItem({ ...itemVazio('ancora'), rotulo: 'x', ancora: 'Contato' }).ancora).toMatch(/Âncora inválida/);
    expect(errosDoItem(mk('x'.repeat(101))).rotulo).toMatch(/100/);
  });

  it('erros da árvore usam os caminhos da API', () => {
    const erros = errosDaArvore([mk('ok'), mk('', { filhos: [mk('f', { url: 'x' })] })]);
    expect(Object.keys(erros).sort()).toEqual(['1.filhos.0.url', '1.rotulo']);
  });

  it('avisa destino em rascunho ou excluído', () => {
    const paginas = [{ id: 2, titulo: 'Civil', status: 'publicado' }, { id: 3, titulo: 'Família', status: 'rascunho' }];

    expect(avisoDoDestino({ tipo: 'pagina', pagina_id: 2 }, paginas)).toBeNull();
    expect(avisoDoDestino({ tipo: 'pagina', pagina_id: 3 }, paginas)).toMatch(/rascunho/);
    expect(avisoDoDestino({ tipo: 'pagina', pagina_id: 99 }, paginas)).toMatch(/excluída/);
    expect(avisoDoDestino({ tipo: 'url', pagina_id: null }, paginas)).toBeNull();
    expect(avisosDaArvore(arvoreDaApi(API), paginas)).toEqual(['"Família": A página "Família" está em rascunho: o link só funcionará depois de publicá-la.']);
  });

  it('separa erros da API de itens e outros', () => {
    expect(errosDaApi({ 'itens.0.rotulo': 'a', 'itens.1.filhos.0.url': 'b', local: 'c' })).toEqual({
      campos: { '0.rotulo': 'a', '1.filhos.0.url': 'b' }, outros: ['c'],
    });
  });
});
