// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, h, nextTick, reactive } from 'vue';

const api = vi.hoisted(() => ({ obterIndice: vi.fn(), obterPagina: vi.fn(), buscar: vi.fn(), obterPaginaDaTela: vi.fn() }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/documentacaoApi', () => api);

import { ApiError } from '../src/services/api';
import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import AjudaContextual from '../src/components/documentacao/AjudaContextual.vue';

const PAGINA = {
  slug: 'processos', secao: 'processos', capa: true, titulo: 'Processos', resumo: 'A carteira de processos.',
  html: '<p><a id="interno" href="#/documentacao/processos/cadastro#partes">cadastro</a> <a id="tela" href="#/tarefas">tarefas</a></p>',
  sumario: [], anterior: null, proxima: null, atualizado_em: '2026-10-13T10:00:00-03:00',
};

const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

// Os apps são desmontados ao fim de cada teste: o painel é teleportado para o body.
const montados: { unmount(): void }[] = [];

function montar(view = 'list') {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const props = reactive({ currentView: view });
  const aberturas: any[] = [];
  const app = createApp({ render: () => h(AjudaContextual, { ...props, onAbrirDocumentacao: (d: any) => aberturas.push(d) }) });
  app.mount(raiz);
  montados.push(app);
  return { raiz, props, aberturas, app };
}
const botao = () => document.querySelector('[data-ajuda]') as HTMLElement | null;
const painel = () => document.querySelector('#ajuda-contextual') as HTMLElement | null;
const tecla = (key: string, alvo: EventTarget = document.body, extra: KeyboardEventInit = {}) => {
  const ev = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...extra });
  alvo.dispatchEvent(ev);
  return ev;
};

describe('AjudaContextual', () => {
  afterEach(() => {
    montados.splice(0).forEach((app) => { try { app.unmount(); } catch { /* já desmontado */ } });
  });

  beforeEach(() => {
    document.body.innerHTML = '';
    Object.values(api).forEach((f) => f.mockReset());
    limparAcesso();
    aplicarAcesso({ permissoes: ['documentacao.access', 'processos.access'], modulos_ativos: ['documentacao', 'processos'] });
    api.obterPaginaDaTela.mockImplementation(async (tela: string) => {
      if (tela === 'list') return { success: true, data: structuredClone(PAGINA) };
      throw new ApiError('Esta tela não tem página de ajuda.', 404);
    });
  });

  it('mostra o botão só quando a tela tem ajuda, e guarda a resposta na sessão', async () => {
    const { props } = montar('list');
    await espera();
    expect(api.obterPaginaDaTela).toHaveBeenCalledWith('list');
    expect(botao()).not.toBeNull();

    props.currentView = 'sem-ajuda-1';
    await espera();
    expect(botao()).toBeNull();

    props.currentView = 'list';
    await espera();
    props.currentView = 'sem-ajuda-1';
    await espera();
    expect(api.obterPaginaDaTela.mock.calls.filter((c) => c[0] === 'list')).toHaveLength(1);
    expect(api.obterPaginaDaTela.mock.calls.filter((c) => c[0] === 'sem-ajuda-1')).toHaveLength(1);
  });

  it('abre o painel, fecha pelo botão, pelo fundo e por Esc', async () => {
    montar('list');
    await espera();

    botao()!.click();
    await espera();
    expect(painel()!.querySelector('h2')!.textContent).toBe('Processos');
    expect(botao()!.getAttribute('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(painel());

    (document.querySelector('[data-fechar-ajuda]') as HTMLElement).click();
    await nextTick();
    expect(painel()).toBeNull();

    botao()!.click();
    await espera();
    tecla('Escape');
    await nextTick();
    expect(painel()).toBeNull();

    botao()!.click();
    await espera();
    (document.querySelector('[data-fechar-rodape]') as HTMLElement).click();
    await nextTick();
    expect(painel()).toBeNull();
  });

  it('atalhos F1 e ?, mas não enquanto se digita', async () => {
    montar('list');
    await espera();

    expect(tecla('F1').defaultPrevented).toBe(true);
    await espera();
    expect(painel()).not.toBeNull();
    tecla('F1');
    await nextTick();
    expect(painel()).toBeNull();

    const campo = document.createElement('input');
    document.body.appendChild(campo);
    tecla('?', campo, { shiftKey: true });
    await nextTick();
    expect(painel()).toBeNull();

    tecla('?', document.body, { shiftKey: true });
    await espera();
    expect(painel()).not.toBeNull();
  });

  it('links para a documentação abrem a tela Documentação; "Abrir na documentação" leva à página', async () => {
    const { aberturas } = montar('list');
    await espera();
    botao()!.click();
    await espera();

    const ev = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
    painel()!.querySelector('#interno')!.dispatchEvent(ev);
    expect(ev.defaultPrevented).toBe(true);
    expect(aberturas).toEqual([{ slug: 'processos/cadastro', ancora: 'partes' }]);
    await nextTick();
    expect(painel()).toBeNull();

    botao()!.click();
    await espera();
    (document.querySelector('[data-abrir-documentacao]') as HTMLElement).click();
    expect(aberturas.at(-1)).toEqual({ slug: 'processos', ancora: null });
  });

  it('some com o módulo documentacao desativado e não aparece na própria Documentação', async () => {
    const { props } = montar('list');
    await espera();
    expect(botao()).not.toBeNull();

    aplicarAcesso({ permissoes: ['documentacao.access', 'processos.access'], modulos_ativos: ['processos'] });
    await espera();
    expect(botao()).toBeNull();

    aplicarAcesso({ permissoes: ['documentacao.access', 'processos.access'], modulos_ativos: ['documentacao', 'processos'] });
    props.currentView = 'documentacao';
    await espera();
    expect(botao()).toBeNull();
    expect(api.obterPaginaDaTela).not.toHaveBeenCalledWith('documentacao');
  });

  it('erro de rede esconde o botão, mas tenta de novo na próxima visita', async () => {
    api.obterPaginaDaTela.mockRejectedValueOnce(new ApiError('Falhou', 500));
    const { props } = montar('list');
    await espera();
    expect(botao()).toBeNull();

    props.currentView = 'outra';
    await espera();
    props.currentView = 'list';
    await espera();
    expect(botao()).not.toBeNull();
  });

  it('para de ouvir o teclado ao desmontar', async () => {
    const { app } = montar('list');
    await espera();
    app.unmount();
    montados.pop();
    expect(tecla('F1').defaultPrevented).toBe(false);
  });
});
