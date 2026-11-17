// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';

vi.mock('../src/utils/acessoTelas', () => ({ telaLiberada: () => true }));

import Sidebar from '../src/components/layout/Sidebar.vue';

const CHAVE = 'ordo_sidebar_secoes_fechadas';
const montados: { unmount(): void }[] = [];

function montar(view = 'dashboard', globalStats: Record<string, number> = {}) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const props = reactive({ currentView: view, globalStats });
  const app = createApp({ render: () => h(Sidebar, { ...props }) });
  app.mount(raiz);
  montados.push(app);
  return { raiz, props };
}

const lista = (raiz: HTMLElement, secao: string) => raiz.querySelector(`#sidebar-secao-${secao}`) as HTMLElement;
const botao = (raiz: HTMLElement, secao: string) =>
  raiz.querySelector(`[aria-controls="sidebar-secao-${secao}"]`) as HTMLButtonElement;
const visivel = (el: HTMLElement) => el.style.display !== 'none';

beforeEach(() => sessionStorage.clear());
afterEach(() => { montados.splice(0).forEach((a) => a.unmount()); document.body.innerHTML = ''; });

describe('Sidebar — acordeon das seções', () => {
  it('começa com todas as seções abertas', () => {
    const { raiz } = montar();
    for (const s of ['principal', 'processos', 'financeiro', 'contabil', 'gestao', 'site']) {
      expect(visivel(lista(raiz, s))).toBe(true);
      expect(botao(raiz, s).getAttribute('aria-expanded')).toBe('true');
    }
  });

  it('recolhe e expande a seção ao clicar no título e grava no sessionStorage', async () => {
    const { raiz } = montar();
    botao(raiz, 'financeiro').click();
    await nextTick();
    expect(visivel(lista(raiz, 'financeiro'))).toBe(false);
    expect(botao(raiz, 'financeiro').getAttribute('aria-expanded')).toBe('false');
    expect(JSON.parse(sessionStorage.getItem(CHAVE)!)).toEqual(['financeiro']);

    botao(raiz, 'financeiro').click();
    await nextTick();
    expect(visivel(lista(raiz, 'financeiro'))).toBe(true);
    expect(JSON.parse(sessionStorage.getItem(CHAVE)!)).toEqual([]);
  });

  it('restaura as seções fechadas da sessão ao montar', () => {
    sessionStorage.setItem(CHAVE, JSON.stringify(['gestao', 'site']));
    const { raiz } = montar();
    expect(visivel(lista(raiz, 'gestao'))).toBe(false);
    expect(visivel(lista(raiz, 'site'))).toBe(false);
    expect(visivel(lista(raiz, 'principal'))).toBe(true);
  });

  it('ignora valor inválido no sessionStorage', () => {
    sessionStorage.setItem(CHAVE, '{quebrado');
    const { raiz } = montar();
    expect(visivel(lista(raiz, 'principal'))).toBe(true);
  });

  it('abre a seção fechada quando a tela ativa pertence a ela', async () => {
    sessionStorage.setItem(CHAVE, JSON.stringify(['financeiro', 'gestao']));
    const { raiz, props } = montar('dre');
    await nextTick();
    expect(visivel(lista(raiz, 'financeiro'))).toBe(true);
    expect(visivel(lista(raiz, 'gestao'))).toBe(false);

    props.currentView = 'tarefas';
    await nextTick(); await nextTick();
    expect(visivel(lista(raiz, 'gestao'))).toBe(true);
    expect(JSON.parse(sessionStorage.getItem(CHAVE)!)).toEqual([]);
  });

  it('mostra os contadores no título só quando a seção está fechada', async () => {
    const stats = { qteReceitasNaoRecebidas: 3, qteDespesasNaoPagas: 0, qteTarefas: 7, qteIntimacoesNaoTratadas: 2 };
    const { raiz } = montar('dashboard', stats);
    const badges = (s: string) => [...botao(raiz, s).querySelectorAll('.sidebar-section-badge')].map((b) => b.textContent);

    expect(badges('financeiro')).toEqual([]);
    botao(raiz, 'financeiro').click();
    botao(raiz, 'gestao').click();
    await nextTick();
    expect(badges('financeiro')).toEqual(['3']); // despesas zeradas não aparecem
    expect(badges('gestao')).toEqual(['7']);
    expect(badges('processos')).toEqual([]); // aberta

    botao(raiz, 'financeiro').click();
    await nextTick();
    expect(badges('financeiro')).toEqual([]);
  });
});
