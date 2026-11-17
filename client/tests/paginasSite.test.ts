import { describe, it, expect } from 'vitest';
import {
  avisoMudancaDeSlug, caminhoPublico, corpoCriacao, corpoEdicao, errosDePublicacao, formDaPagina, formVazio,
  gerarSlug, podeDuplicar, podeExcluir, podePublicar, slugValido, validarForm,
} from '../src/utils/paginasSite';
import type { SitePagina } from '../src/types/site';

const pagina = (extra: Partial<SitePagina> = {}): SitePagina => ({
  id: 2, slug: 'direito-civil', titulo: 'Direito Civil', tipo: 'area', layout: 'area', meta_title: 'Civil | Escritório',
  meta_description: 'Descrição', og_image_id: 4, status: 'publicado', publicado_em: '2026-10-08 10:00:00', ordem: 1,
  exibir_sitemap: 1, prioridade_sitemap: 0.8, changefreq: 'monthly', url: 'http://localhost/processos/pagina/direito-civil', ...extra,
});

describe('slug', () => {
  it('gera a partir do título, sem acentos nem símbolos', () => {
    expect(gerarSlug('Direito de Família & Sucessões')).toBe('direito-de-familia-sucessoes');
    expect(gerarSlug('  --Olá, Mundo!--  ')).toBe('ola-mundo');
    expect(gerarSlug('Cláusulas 2024/2025')).toBe('clausulas-2024-2025');
    expect(gerarSlug('???')).toBe('');
  });

  it('respeita 120 caracteres sem terminar em hífen', () => {
    const s = gerarSlug('palavra '.repeat(30));
    expect(s.length).toBeLessThanOrEqual(120);
    expect(s.endsWith('-')).toBe(false);
    expect(slugValido(s)).toBe(true);
  });

  it('valida o formato aceito pelo backend', () => {
    for (const ok of ['sobre', 'direito-civil', 'a1-b2']) expect(slugValido(ok)).toBe(true);
    for (const ruim of ['', 'Sobre', 'a--b', '-a', 'a-', 'a b', 'ação', 'a_b']) expect(slugValido(ruim), ruim).toBe(false);
    expect(slugValido('a'.repeat(121))).toBe(false);
  });
});

describe('lista', () => {
  it('home aparece como /, demais com o caminho público', () => {
    expect(caminhoPublico(pagina({ tipo: 'home', url: 'http://x/', slug: 'inicio' }))).toBe('/');
    expect(caminhoPublico(pagina())).toBe('/pagina/direito-civil');
    expect(caminhoPublico(pagina({ tipo: 'sistema', url: 'http://x/processos/noticias', slug: 'noticias' }))).toBe('/noticias');
    expect(caminhoPublico(pagina({ tipo: 'sistema', url: 'http://x/area-cliente/', slug: 'area-cliente' }))).toBe('/area-cliente');
    expect(caminhoPublico(pagina({ url: 'invalida' }))).toBe('/pagina/direito-civil');
  });

  it('ações por tipo: home não exclui nem publica; sistema só edita', () => {
    const t = (tipo: SitePagina['tipo']) => ({ tipo });
    expect([podeExcluir(t('home')), podeDuplicar(t('home')), podePublicar(t('home'))]).toEqual([false, true, false]);
    expect([podeExcluir(t('sistema')), podeDuplicar(t('sistema')), podePublicar(t('sistema'))]).toEqual([false, false, false]);
    expect([podeExcluir(t('pagina')), podeDuplicar(t('pagina')), podePublicar(t('pagina'))]).toEqual([true, true, true]);
    expect(podeExcluir(t('area'))).toBe(true);
  });
});

describe('formulário', () => {
  it('valida título, endereço, descrição e prioridade', () => {
    expect(validarForm({ ...formVazio(), titulo: 'A', slug: 'a' })).toEqual({});

    const erros = validarForm({ ...formVazio(), titulo: ' ', slug: 'Ruim Slug', meta_description: 'x'.repeat(161), prioridade_sitemap: 2 });
    expect(Object.keys(erros).sort()).toEqual(['meta_description', 'prioridade_sitemap', 'slug', 'titulo']);
    expect(validarForm({ ...formVazio(), titulo: 'A' }).slug).toMatch(/Informe o endereço/);
  });

  it('o formulário reflete a página (nulos viram vazio, 0/1 viram boolean)', () => {
    const f = formDaPagina(pagina({ meta_title: null, meta_description: null, og_image_id: null, exibir_sitemap: 0 }));

    expect(f).toMatchObject({ meta_title: '', meta_description: '', og_image_id: null, exibir_sitemap: false, tipo: 'area', prioridade_sitemap: 0.8 });
    expect(formDaPagina(pagina({ tipo: 'home' })).tipo).toBe('pagina');
  });

  it('criação manda só o que foi preenchido', () => {
    const corpo = corpoCriacao({ ...formVazio(), titulo: ' Previdenciário ', slug: 'previdenciario', tipo: 'area' });

    expect(corpo).toEqual({
      titulo: 'Previdenciário', slug: 'previdenciario', tipo: 'area', exibir_sitemap: true, prioridade_sitemap: 0.8, changefreq: 'monthly',
    });
    expect(corpoCriacao({ ...formVazio(), titulo: 'a', slug: 'a', layout: 'main', meta_title: 'T', meta_description: 'D', og_image_id: 3 }))
      .toMatchObject({ layout: 'main', meta_title: 'T', meta_description: 'D', og_image_id: 3 });
  });

  it('edição manda só o que mudou, e limpar SEO manda vazio', () => {
    const original = formDaPagina(pagina());

    expect(corpoEdicao(original, { ...original })).toEqual({});
    expect(corpoEdicao(original, { ...original, titulo: 'Civil', slug: 'civil', prioridade_sitemap: 0.5 }))
      .toEqual({ titulo: 'Civil', slug: 'civil', prioridade_sitemap: 0.5 });
    expect(corpoEdicao(original, { ...original, meta_title: '  ', og_image_id: null, exibir_sitemap: false }))
      .toEqual({ meta_title: '', og_image_id: null, exibir_sitemap: false });
  });
});

describe('avisos', () => {
  it('mudar o endereço de página publicada avisa; rascunho e mesmo endereço não', () => {
    const aviso = avisoMudancaDeSlug({ status: 'publicado', slug: 'civil' }, 'direito-civil');

    expect(aviso).toContain('/pagina/civil');
    expect(aviso).toMatch(/deixará de funcionar/);
    expect(avisoMudancaDeSlug({ status: 'rascunho', slug: 'civil' }, 'outro')).toBeNull();
    expect(avisoMudancaDeSlug({ status: 'publicado', slug: 'civil' }, 'civil')).toBeNull();
  });

  it('erros de publicação agrupados por seção', () => {
    expect(errosDePublicacao({
      'secoes.34.titulo': 'Campo obrigatório.',
      'secoes.34.botoes.1.rotulo': 'Campo obrigatório.',
      'secoes.40.imagem': 'Mídia não encontrada.',
      pagina: 'Páginas de sistema não têm rascunho.',
    })).toEqual([
      { secaoId: 34, mensagens: ['titulo: Campo obrigatório.', 'botoes.1.rotulo: Campo obrigatório.'] },
      { secaoId: 40, mensagens: ['imagem: Mídia não encontrada.'] },
    ]);
  });
});
