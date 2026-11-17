// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  obterTema: vi.fn(), salvarTema: vi.fn(), restaurarTema: vi.fn(), listarPaginas: vi.fn(), tokenPreview: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/siteApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/siteApi')>('../src/services/siteApi');
  return { ...real, ...api };
});

import AparenciaSiteView from '../src/views/Site/AparenciaSiteView.vue';
import { temAlteracoesNaoSalvas, marcarSujo } from '../src/composables/useSaidaProtegida';

const cor = (padrao: string, atual = padrao) => ({ padrao, atual, personalizado: padrao !== atual });
const TEMA = () => ({
  cores: {
    'navy-dark': cor('#0D1B2A'),
    cream: cor('#FAF9F6'),
    gold: cor('#C9A84C', '#B08E35'),
    'text-primary': cor('#FAF9F6'),
    'text-dark': cor('#1E293B'),
  },
  fontes: {
    serif: { padrao: 'Cormorant Garamond', atual: 'Lora', personalizado: true },
    sans: { padrao: 'Outfit', atual: 'Outfit', personalizado: false },
  },
  fontes_disponiveis: { 'Cormorant Garamond': 'serif', Lora: 'serif', Outfit: 'sans', Inter: 'sans' },
});
const espera = async () => { for (let i = 0; i < 8; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  createApp(AparenciaSiteView, { onToast: (t: any) => toasts.push(t) }).mount(raiz);
  return { raiz, toasts };
}
const q = (raiz: HTMLElement, sel: string) => raiz.querySelector(sel) as HTMLElement;
const clicar = (el: Element | null) => (el as HTMLElement).click();
function digitar(el: Element | null, texto: string) {
  (el as HTMLInputElement).value = texto;
  el!.dispatchEvent(new Event('input', { bubbles: true }));
}
const hex = (raiz: HTMLElement, token: string) => q(raiz, `[data-cor="${token}"] [data-hex]`) as HTMLInputElement;

describe('AparenciaSiteView', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.head.innerHTML = '';
    marcarSujo(false);
    Object.values(api).forEach((f) => f.mockReset());
    api.obterTema.mockResolvedValue({ data: TEMA() });
    api.listarPaginas.mockResolvedValue({ data: [{ id: 1, tipo: 'home' }] });
    api.tokenPreview.mockResolvedValue({ data: { token: 't', url: 'about:blank#t', expira_em: 'x' } });
    (window as any).confirm = vi.fn(() => true);
    (window as any).happyDOM.settings.disableIframePageLoading = true;
  });

  it('lista os tokens com valor atual, padrão e marca os personalizados', async () => {
    const { raiz } = montar();
    await espera();

    expect(raiz.querySelectorAll('[data-cor]')).toHaveLength(5);
    expect(hex(raiz, 'gold').value).toBe('#B08E35');
    expect(q(raiz, '[data-cor="gold"] .padrao').textContent).toContain('#C9A84C');
    expect(q(raiz, '[data-cor="gold"]').textContent).toContain('personalizada');
    expect(q(raiz, '[data-cor="cream"]').textContent).not.toContain('personalizada');
    expect((q(raiz, '[data-cor="gold"] [data-seletor]') as HTMLInputElement).value).toBe('#b08e35');
  });

  it('fontes: só as famílias da categoria, com amostra e padrão indicado', async () => {
    const { raiz } = montar();
    await espera();

    const serif = [...raiz.querySelectorAll('[data-fonte="serif"] option')].map((o) => o.textContent);
    expect(serif).toEqual(['Cormorant Garamond (padrão)', 'Lora']);
    expect((q(raiz, '[data-fonte="serif"] select') as HTMLSelectElement).value).toBe('Lora');
    expect([...raiz.querySelectorAll('[data-fonte="sans"] option')].map((o) => o.textContent)).toEqual(['Outfit (padrão)', 'Inter']);
    expect(q(raiz, '[data-fonte="serif"] [data-amostra]').getAttribute('style')).toContain('Lora');
  });

  it('carrega as amostras do Google Fonts só com famílias da lista', async () => {
    montar();
    await espera();

    const links = [...document.head.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.getAttribute('href'));
    expect(links).toHaveLength(1);
    expect(links[0]).toContain('family=Lora');
    expect(links[0]).toContain('family=Outfit');
    expect(links[0]).not.toContain('Inter');
  });

  it('editar o hexadecimal ou o seletor marca como não salvo', async () => {
    const { raiz } = montar();
    await espera();
    expect(temAlteracoesNaoSalvas()).toBe(false);

    digitar(hex(raiz, 'navy-dark'), '#102030');
    await espera();
    expect(temAlteracoesNaoSalvas()).toBe(true);
    expect((q(raiz, '[data-cor="navy-dark"] [data-seletor]') as HTMLInputElement).value).toBe('#102030');

    digitar(q(raiz, '[data-cor="cream"] [data-seletor]'), '#eeeeee');
    await espera();
    expect(hex(raiz, 'cream').value).toBe('#eeeeee');
  });

  it('salvar manda só o que mudou e recarrega a prévia', async () => {
    const novo = TEMA();
    novo.cores['navy-dark'] = { padrao: '#0D1B2A', atual: '#112233', personalizado: true };
    api.salvarTema.mockResolvedValue({ data: novo });
    const { raiz, toasts } = montar();
    await espera();
    api.tokenPreview.mockClear();

    digitar(hex(raiz, 'navy-dark'), '#123');
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(api.salvarTema).toHaveBeenCalledWith({ cores: { 'navy-dark': '#112233' } });
    expect(temAlteracoesNaoSalvas()).toBe(false);
    expect(hex(raiz, 'navy-dark').value).toBe('#112233');
    expect(toasts.at(-1)?.type).toBe('success');
    expect(api.tokenPreview).toHaveBeenCalled();
  });

  it('restaurar o padrão de um token manda vazio', async () => {
    api.salvarTema.mockResolvedValue({ data: (() => { const t = TEMA(); t.cores.gold = cor('#C9A84C'); return t; })() });
    const { raiz } = montar();
    await espera();

    clicar(q(raiz, '[data-cor="gold"] [data-restaurar-token]'));
    await espera();
    expect(hex(raiz, 'gold').value).toBe('#C9A84C');
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(api.salvarTema).toHaveBeenCalledWith({ cores: { gold: '' } });
  });

  it('trocar e restaurar a fonte', async () => {
    api.salvarTema.mockResolvedValue({ data: TEMA() });
    const { raiz } = montar();
    await espera();

    const sans = q(raiz, '[data-fonte="sans"] select') as HTMLSelectElement;
    sans.value = 'Inter';
    sans.dispatchEvent(new Event('change', { bubbles: true }));
    clicar(q(raiz, '[data-fonte="serif"] [data-restaurar-fonte]'));
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(api.salvarTema).toHaveBeenCalledWith({ fontes: { sans: 'Inter', serif: '' } });
  });

  it('cor digitada inválida não vai para a API', async () => {
    const { raiz, toasts } = montar();
    await espera();

    digitar(hex(raiz, 'cream'), 'creme');
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(api.salvarTema).not.toHaveBeenCalled();
    expect(q(raiz, '[data-cor="cream"] .erro').textContent).toMatch(/hexadecimal/);
    expect(toasts.at(-1)?.type).toBe('warning');
  });

  it('erro 422 da API aparece no token', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.salvarTema.mockRejectedValue(new ApiError('422', 422, { messages: { 'cores.gold': 'O template ativo não tem o token.' } }));
    const { raiz, toasts } = montar();
    await espera();

    digitar(hex(raiz, 'gold'), '#112233');
    await espera();
    clicar(q(raiz, '[data-salvar]'));
    await espera();

    expect(q(raiz, '[data-cor="gold"] .erro').textContent).toContain('não tem o token');
    expect(toasts.at(-1)?.type).toBe('danger');
    expect(hex(raiz, 'gold').value).toBe('#112233');
  });

  it('avisa contraste insuficiente (WCAG AA) ao vivo, sem impedir o salvamento', async () => {
    api.salvarTema.mockResolvedValue({ data: TEMA() });
    const { raiz } = montar();
    await espera();
    expect(raiz.querySelector('[data-avisos]')).toBeNull();

    digitar(hex(raiz, 'navy-dark'), '#e8e8e8');
    await espera();

    const aviso = q(raiz, '[data-avisos]').textContent ?? '';
    expect(aviso).toContain('"text-primary" sobre "navy-dark"');
    expect(aviso).toContain('WCAG AA');
    expect((q(raiz, '[data-salvar]') as HTMLButtonElement).disabled).toBe(false);

    digitar(hex(raiz, 'navy-dark'), '#0d1b2a');
    await espera();
    expect(raiz.querySelector('[data-avisos]')).toBeNull();
  });

  it('descartar volta ao salvo', async () => {
    const { raiz } = montar();
    await espera();

    digitar(hex(raiz, 'cream'), '#000000');
    await espera();
    clicar(q(raiz, '[data-descartar]'));
    await espera();

    expect(hex(raiz, 'cream').value).toBe('#FAF9F6');
    expect(temAlteracoesNaoSalvas()).toBe(false);
  });

  it('restaurar tudo pede confirmação e usa o DELETE', async () => {
    api.restaurarTema.mockResolvedValue({ data: (() => { const t = TEMA(); t.cores.gold = cor('#C9A84C'); t.fontes.serif.atual = 'Cormorant Garamond'; t.fontes.serif.personalizado = false; return t; })() });
    const { raiz } = montar();
    await espera();

    (window.confirm as any).mockReturnValueOnce(false);
    clicar(q(raiz, '[data-restaurar-tudo]'));
    await espera();
    expect(api.restaurarTema).not.toHaveBeenCalled();

    clicar(q(raiz, '[data-restaurar-tudo]'));
    await espera();
    expect(api.restaurarTema).toHaveBeenCalledTimes(1);
    expect(hex(raiz, 'gold').value).toBe('#C9A84C');
    expect((q(raiz, '[data-restaurar-tudo]') as HTMLButtonElement).disabled).toBe(true);
  });

  it('prévia usa o token da página inicial; sem home avisa', async () => {
    const a = montar();
    await espera();
    expect(api.listarPaginas).toHaveBeenCalledWith({ tipo: 'home' });
    expect(api.tokenPreview).toHaveBeenCalledWith(1);
    expect(q(a.raiz, '[data-iframe]').getAttribute('src')).toBe('about:blank#t');

    document.body.innerHTML = '';
    api.listarPaginas.mockResolvedValue({ data: [] });
    const b = montar();
    await espera();
    expect(b.raiz.textContent).toContain('ainda não tem página inicial');
  });

  it('falha ao carregar mostra o erro com nova tentativa', async () => {
    api.obterTema.mockRejectedValueOnce(new Error('rede caiu')).mockResolvedValue({ data: TEMA() });
    const { raiz } = montar();
    await espera();

    expect(raiz.textContent).toContain('rede caiu');
    clicar(raiz.querySelector('[role="alert"] button'));
    await espera();
    expect(raiz.querySelectorAll('[data-cor]')).toHaveLength(5);
  });
});
