// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  obterConfiguracoes: vi.fn(),
  obterSchema: vi.fn(),
  salvarConfiguracoes: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/siteApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/siteApi')>('../src/services/siteApi');
  return {
    ...real,
    ...api,
    obterMidia: vi.fn(async (id: number) => ({ data: { id, nome_original: 'logo.png', alt: 'Logo', largura: 10, altura: 10, miniatura_url: 'http://x/t.png', url: 'http://x/l.png' } })),
  };
});

import ConfiguracoesSiteView from '../src/views/Site/ConfiguracoesSiteView.vue';
import { temAlteracoesNaoSalvas, marcarSujo } from '../src/composables/useSaidaProtegida';

const CONFIG = {
  identidade: [{ chave: 'identidade.nome', tipo: 'string', grupo: 'identidade', valor: 'Silva & Souza' }],
  contato: [
    { chave: 'contato.telefone', tipo: 'string', grupo: 'contato', valor: '(11) 4000-1111' },
    { chave: 'contato.enderecos', tipo: 'json', grupo: 'contato', valor: [] },
  ],
  seo: [{ chave: 'seo.titulo_padrao', tipo: 'string', grupo: 'seo', valor: 'Advocacia' }],
  textos: [{ chave: 'textos.contato.nome', tipo: 'string', grupo: 'textos', valor: 'Seu nome' }],
};
const SCHEMA = { data: { textos: { 'contato.nome': 'Nome completo *', 'contato.telefone': 'Telefone' } } };

const espera = async () => { for (let i = 0; i < 5; i++) { await nextTick(); await Promise.resolve(); } };

async function montar() {
  const toasts: Array<{ msg: string; type: string }> = [];
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const app = createApp(ConfiguracoesSiteView, { onToast: (t: any) => toasts.push(t) });
  app.mount(raiz);
  await espera();
  return { raiz, toasts, app };
}

function digitar(el: Element | null, texto: string) {
  (el as HTMLInputElement).value = texto;
  el!.dispatchEvent(new Event('input', { bubbles: true }));
}
const campo = (raiz: HTMLElement, chave: string) => raiz.querySelector(`[data-chave="${chave}"]`) as HTMLElement;
const botao = (raiz: HTMLElement, texto: string) =>
  [...raiz.querySelectorAll('button')].find((b) => b.textContent?.includes(texto)) as HTMLButtonElement;

describe('ConfiguracoesSiteView', () => {
  beforeEach(() => {
    marcarSujo(false);
    document.body.innerHTML = '';
    api.obterConfiguracoes.mockReset().mockResolvedValue({ data: CONFIG });
    api.obterSchema.mockReset().mockResolvedValue(SCHEMA);
    api.salvarConfiguracoes.mockReset();
  });

  it('carrega as configurações e mostra as abas', async () => {
    const { raiz } = await montar();

    const abas = [...raiz.querySelectorAll('[role="tab"]')].map((a) => a.textContent?.trim());
    expect(abas.join('|')).toMatch(/Identidade.*Contato.*Redes sociais.*SEO.*Integrações.*Textos do site/);
    expect((campo(raiz, 'identidade.nome').querySelector('input') as HTMLInputElement).value).toBe('Silva & Souza');
    expect(botao(raiz, 'Salvar').disabled).toBe(true);
  });

  it('só envia o que mudou (PUT em lote) e zera o estado depois', async () => {
    api.salvarConfiguracoes.mockResolvedValue({ data: { ...CONFIG, contato: [{ ...CONFIG.contato[0], valor: '(11) 4000-2222' }, CONFIG.contato[1]] } });
    const { raiz, toasts } = await montar();

    digitar(campo(raiz, 'contato.telefone').querySelector('input'), '(11) 4000-2222');
    await espera();
    expect(botao(raiz, 'Salvar').disabled).toBe(false);
    expect(temAlteracoesNaoSalvas()).toBe(true);
    expect(raiz.textContent).toContain('Alterações não salvas');

    botao(raiz, 'Salvar').click();
    await espera();

    expect(api.salvarConfiguracoes).toHaveBeenCalledWith({ 'contato.telefone': '(11) 4000-2222' });
    expect(botao(raiz, 'Salvar').disabled).toBe(true);
    expect(temAlteracoesNaoSalvas()).toBe(false);
    expect(toasts.at(-1)?.type).toBe('success');
  });

  it('chave nova leva o tipo (redes sociais)', async () => {
    api.salvarConfiguracoes.mockResolvedValue({ data: CONFIG });
    const { raiz } = await montar();

    digitar(campo(raiz, 'redes.instagram').querySelector('input'), 'https://instagram.com/silva');
    await espera();
    botao(raiz, 'Salvar').click();
    await espera();

    expect(api.salvarConfiguracoes).toHaveBeenCalledWith({ 'redes.instagram': { valor: 'https://instagram.com/silva', tipo: 'string' } });
  });

  it('ID do Google Analytics inválido não chega à API e abre a aba do erro', async () => {
    const { raiz, toasts } = await montar();

    digitar(campo(raiz, 'integracoes.ga_id').querySelector('input'), 'UA-123');
    await espera();
    botao(raiz, 'Salvar').click();
    await espera();

    expect(api.salvarConfiguracoes).not.toHaveBeenCalled();
    expect(campo(raiz, 'integracoes.ga_id').textContent).toContain('ID do Google Analytics inválido');
    expect(toasts.at(-1)?.type).toBe('warning');
    expect(raiz.querySelector('[role="tab"][aria-selected="true"]')?.textContent).toContain('Integrações');
  });

  it('erro 422 da API aparece no campo e nada é descartado', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.salvarConfiguracoes.mockRejectedValue(new ApiError('422', 422, { messages: { 'contato.telefone': 'Máximo de 500 caracteres.' } }));
    const { raiz, toasts } = await montar();

    digitar(campo(raiz, 'contato.telefone').querySelector('input'), '123');
    await espera();
    botao(raiz, 'Salvar').click();
    await espera();

    expect(campo(raiz, 'contato.telefone').textContent).toContain('Máximo de 500 caracteres.');
    expect((campo(raiz, 'contato.telefone').querySelector('input') as HTMLInputElement).value).toBe('123');
    expect(toasts.at(-1)?.type).toBe('danger');
    expect(botao(raiz, 'Salvar').disabled).toBe(false);
  });

  it('descartar volta ao valor salvo', async () => {
    const { raiz } = await montar();

    digitar(campo(raiz, 'identidade.nome').querySelector('input'), 'Outro nome');
    await espera();
    botao(raiz, 'Descartar').click();
    await espera();

    expect((campo(raiz, 'identidade.nome').querySelector('input') as HTMLInputElement).value).toBe('Silva & Souza');
    expect(temAlteracoesNaoSalvas()).toBe(false);
  });

  it('textos do template: padrão no placeholder e restaurar padrão envia vazio', async () => {
    api.salvarConfiguracoes.mockResolvedValue({ data: { ...CONFIG, textos: [] } });
    const { raiz } = await montar();

    const input = campo(raiz, 'textos.contato.nome').querySelector('input') as HTMLInputElement;
    expect(input.placeholder).toBe('Nome completo *');
    expect(input.value).toBe('Seu nome');
    expect(campo(raiz, 'textos.contato.nome').textContent).toContain('personalizado');

    botao(raiz, 'Restaurar padrão').click();
    await espera();
    botao(raiz, 'Salvar').click();
    await espera();

    expect(api.salvarConfiguracoes).toHaveBeenCalledWith({ 'textos.contato.nome': '' });
  });

  it('prévia no Google acompanha os campos de SEO', async () => {
    const { raiz } = await montar();

    digitar(campo(raiz, 'seo.titulo_padrao').querySelector('input'), 'Novo título');
    digitar(campo(raiz, 'seo.sufixo_titulo').querySelector('input'), ' | Silva');
    await espera();

    expect(raiz.querySelector('.serp-titulo')?.textContent).toBe('Novo título | Silva');
  });

  it('adicionar e remover unidades', async () => {
    api.salvarConfiguracoes.mockResolvedValue({ data: CONFIG });
    const { raiz } = await montar();

    botao(raiz, 'Adicionar unidade').click();
    await espera();
    expect(raiz.querySelectorAll('[data-unidade]')).toHaveLength(1);

    digitar(raiz.querySelector('[data-unidade="0"] input[placeholder="MG"]'), 'sp');
    await espera();
    botao(raiz, 'Salvar').click();
    await espera();
    expect((api.salvarConfiguracoes.mock.calls[0][0] as any)['contato.enderecos'][0].uf).toBe('sp');
  });

  it('remover uma unidade a tira do formulário e conta como alteração', async () => {
    const { raiz } = await montar();

    botao(raiz, 'Adicionar unidade').click();
    await espera();
    digitar(raiz.querySelector('[data-unidade="0"] input[placeholder="MG"]'), 'sp');
    await espera();
    (raiz.querySelector('[data-unidade="0"] button[title="Remover unidade"]') as HTMLButtonElement).click();
    await espera();

    expect(raiz.querySelectorAll('[data-unidade]')).toHaveLength(0);
    expect(botao(raiz, 'Salvar').disabled).toBe(true);
  });

  it('falha ao carregar mostra o erro com nova tentativa', async () => {
    api.obterConfiguracoes.mockRejectedValueOnce(new Error('rede caiu')).mockResolvedValue({ data: CONFIG });
    const { raiz } = await montar();

    expect(raiz.textContent).toContain('rede caiu');
    botao(raiz, 'Tentar de novo').click();
    await espera();
    expect(raiz.querySelector('[role="tab"]')).not.toBeNull();
  });
});
