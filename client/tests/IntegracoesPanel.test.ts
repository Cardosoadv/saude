// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ salvarIntegracoes: vi.fn() }));
vi.mock('../src/services/integracoesApi', async (original) => ({
  ...(await original<typeof import('../src/services/integracoesApi')>()),
  salvarIntegracoes: api.salvarIntegracoes,
}));

import IntegracoesPanel from '../src/components/Configuracoes/IntegracoesPanel.vue';
import { ErroValidacaoIntegracao, type GrupoIntegracao } from '../src/services/integracoesApi';

const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

const grupo = (): GrupoIntegracao => ({
  slug: 'ia', titulo: 'Inteligência artificial', descricao: 'Provedores de IA.', icone: 'bi-stars',
  secoes: [
    { titulo: 'Gemini', campos: [
      { chave: 'API_KEY_GEMINI', rotulo: 'Chave da API', tipo: 'segredo', descricao: '', valor: null, tem_valor: true, origem: 'banco' },
      { chave: 'API_MODEL_GEMINI', rotulo: 'Modelo', tipo: 'texto', descricao: '', valor: 'gemini-pro', tem_valor: true, origem: 'banco' },
      { chave: 'API_URL_GEMINI', rotulo: 'Endereço base', tipo: 'url', descricao: '', valor: '', tem_valor: false, origem: 'vazio' },
    ] },
    { titulo: 'Provedor por função', campos: [
      { chave: 'AI_PROVIDER_DEFAULT', rotulo: 'Padrão', tipo: 'opcao', descricao: '', valor: '', tem_valor: false, origem: 'vazio',
        opcoes: { '': 'Usar o padrão', gemini: 'Gemini', deepseek: 'DeepSeek' } },
      { chave: 'API_KEY_DEEPSEEK', rotulo: 'Chave DeepSeek', tipo: 'segredo', descricao: '', valor: null, tem_valor: true, origem: 'env' },
    ] },
  ],
});

function montar(props: Record<string, unknown> = {}) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const eventos = { toasts: [] as any[], salvo: 0 };
  const app = createApp(IntegracoesPanel, {
    grupo: grupo(), criptografia: true, ...props,
    onToast: (t: any) => eventos.toasts.push(t),
    onSalvo: () => { eventos.salvo++; },
  });
  app.mount(raiz);
  return { raiz, eventos, desmontar: () => { app.unmount(); raiz.remove(); } };
}

const campo = (raiz: HTMLElement, rotulo: string) => raiz.querySelector(`[aria-label="${rotulo}"]`) as HTMLInputElement | HTMLSelectElement;

function digitar(raiz: HTMLElement, rotulo: string, valor: string) {
  const el = campo(raiz, rotulo);
  el.value = valor;
  el.dispatchEvent(new Event('input'));
  el.dispatchEvent(new Event('change'));
}

const botao = (raiz: HTMLElement, id: string) => raiz.querySelector(`[data-testid="${id}"]`) as HTMLButtonElement;

describe('IntegracoesPanel', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    api.salvarIntegracoes.mockReset();
    api.salvarIntegracoes.mockResolvedValue({ alteradas: [] });
  });
  afterEach(() => { montado?.desmontar(); montado = null; });

  it('mostra as seções, os campos e o título do grupo', async () => {
    montado = montar();
    await espera();
    const texto = montado.raiz.textContent ?? '';

    expect(texto).toContain('Inteligência artificial');
    expect(texto).toContain('Gemini');
    expect(texto).toContain('Provedor por função');
    expect(campo(montado.raiz, 'Modelo').value).toBe('gemini-pro');
  });

  it('segredo é campo de senha, vem vazio e nunca mostra o valor', async () => {
    montado = montar();
    await espera();
    const segredo = campo(montado.raiz, 'Chave da API') as HTMLInputElement;

    expect(segredo.type).toBe('password');
    expect(segredo.value).toBe('');
    expect(segredo.placeholder).toContain('manter');
    expect(segredo.autocomplete).toBe('new-password');
  });

  it('selos: configurado, valor do servidor e não configurado', async () => {
    montado = montar();
    await espera();

    expect(montado.raiz.querySelector('[data-testid="selo-API_KEY_GEMINI"]')?.textContent).toBe('Configurado');
    expect(montado.raiz.querySelector('[data-testid="selo-API_KEY_DEEPSEEK"]')?.textContent).toContain('.env');
    expect(montado.raiz.querySelector('[data-testid="selo-API_URL_GEMINI"]')?.textContent).toBe('Não configurado');
    expect(montado.raiz.textContent).toContain('Vem da configuração do servidor');
  });

  it('Salvar começa desligado e liga ao editar', async () => {
    montado = montar();
    await espera();
    expect(botao(montado.raiz, 'salvar').disabled).toBe(true);

    digitar(montado.raiz, 'Modelo', 'gemini-flash');
    await espera();

    expect(botao(montado.raiz, 'salvar').disabled).toBe(false);
  });

  it('envia só o que mudou e avisa o pai para recarregar', async () => {
    api.salvarIntegracoes.mockResolvedValue({ alteradas: ['API_MODEL_GEMINI', 'API_KEY_GEMINI'] });
    montado = montar();
    await espera();

    digitar(montado.raiz, 'Modelo', 'gemini-flash');
    digitar(montado.raiz, 'Chave da API', 'nova-chave');
    digitar(montado.raiz, 'Padrão', 'deepseek');
    await espera();
    botao(montado.raiz, 'salvar').click();
    await espera();

    expect(api.salvarIntegracoes).toHaveBeenCalledWith(
      { API_MODEL_GEMINI: 'gemini-flash', API_KEY_GEMINI: 'nova-chave', AI_PROVIDER_DEFAULT: 'deepseek' },
      [],
    );
    expect(montado.eventos.salvo).toBe(1);
    expect(montado.eventos.toasts.at(-1)).toMatchObject({ type: 'success' });
  });

  it('segredo em branco não é enviado', async () => {
    montado = montar();
    await espera();

    digitar(montado.raiz, 'Modelo', 'x');
    await espera();
    botao(montado.raiz, 'salvar').click();
    await espera();

    const [valores] = api.salvarIntegracoes.mock.calls[0];
    expect(valores).toEqual({ API_MODEL_GEMINI: 'x' });
    expect('API_KEY_GEMINI' in valores).toBe(false);
  });

  it('apagar segredo: marca, mostra o aviso, desfaz e envia em "limpar"', async () => {
    montado = montar();
    await espera();

    botao(montado.raiz, 'apagar-API_KEY_GEMINI').click();
    await espera();
    expect(montado.raiz.textContent).toContain('Será apagado ao salvar');
    expect(campo(montado.raiz, 'Chave da API').disabled).toBe(true);

    botao(montado.raiz, 'salvar').click();
    await espera();
    expect(api.salvarIntegracoes).toHaveBeenCalledWith({}, ['API_KEY_GEMINI']);
  });

  it('só oferece apagar quando o valor é deste escritório (não o do servidor)', async () => {
    montado = montar();
    await espera();

    expect(botao(montado.raiz, 'apagar-API_KEY_GEMINI')).not.toBeNull();
    expect(botao(montado.raiz, 'apagar-API_KEY_DEEPSEEK')).toBeNull();
  });

  it('erro de validação aparece no campo e nada é descartado', async () => {
    api.salvarIntegracoes.mockRejectedValue(new ErroValidacaoIntegracao('Corrija os campos indicados.', { API_URL_GEMINI: 'Informe um endereço completo.' }));
    montado = montar();
    await espera();

    digitar(montado.raiz, 'Endereço base', 'isto-nao-e-url');
    await espera();
    botao(montado.raiz, 'salvar').click();
    await espera();

    expect(montado.raiz.querySelector('[data-testid="erro-API_URL_GEMINI"]')?.textContent?.trim()).toBe('Informe um endereço completo.');
    expect(campo(montado.raiz, 'Endereço base').value).toBe('isto-nao-e-url');
    expect(montado.eventos.salvo).toBe(0);
    expect(montado.eventos.toasts.at(-1)).toMatchObject({ type: 'error' });
  });

  it('descartar volta ao valor original', async () => {
    montado = montar();
    await espera();

    digitar(montado.raiz, 'Modelo', 'outro');
    await espera();
    (montado.raiz.querySelector('button.btn-ghost[type="button"]:not([data-testid])') as HTMLButtonElement).click();
    await espera();

    expect(campo(montado.raiz, 'Modelo').value).toBe('gemini-pro');
    expect(botao(montado.raiz, 'salvar').disabled).toBe(true);
  });

  it('avisa quando o servidor não criptografa os segredos', async () => {
    montado = montar({ criptografia: false });
    await espera();
    expect(montado.raiz.querySelector('[data-testid="aviso-criptografia"]')).not.toBeNull();
    montado.desmontar();

    montado = montar({ criptografia: true });
    await espera();
    expect(montado.raiz.querySelector('[data-testid="aviso-criptografia"]')).toBeNull();
  });

  it('recomeça com os valores novos quando o catálogo é recarregado', async () => {
    const raiz = document.createElement('div');
    document.body.appendChild(raiz);
    const { h, ref } = await import('vue');
    const atual = ref(grupo());
    const app = createApp({ render: () => h(IntegracoesPanel, { grupo: atual.value, criptografia: true }) });
    app.mount(raiz);
    await espera();

    digitar(raiz, 'Chave da API', 'digitada');
    await espera();
    const recarregado = grupo();
    recarregado.secoes[0].campos[1].valor = 'gemini-novo';
    atual.value = recarregado;
    await espera();

    expect((campo(raiz, 'Chave da API') as HTMLInputElement).value).toBe('');
    expect(campo(raiz, 'Modelo').value).toBe('gemini-novo');
    app.unmount();
    raiz.remove();
  });
});
