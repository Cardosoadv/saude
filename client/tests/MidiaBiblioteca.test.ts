// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarMidias: vi.fn(), enviarMidia: vi.fn(), atualizarAltMidia: vi.fn(),
  excluirMidia: vi.fn(), usosMidia: vi.fn(), obterMidia: vi.fn(),
}));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/siteApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/siteApi')>('../src/services/siteApi');
  return { ...real, ...api };
});

import MidiaBiblioteca from '../src/components/site/MidiaBiblioteca.vue';
import MidiaPicker from '../src/components/site/MidiaPicker.vue';
import { limparCacheDeMidias } from '../src/composables/useMidiaCache';

const midia = (id: number, extra: Record<string, unknown> = {}) => ({
  id, arquivo: `2026/10/${id}.png`, nome_original: `foto-${id}.png`, mime: 'image/png', tamanho: 2048,
  largura: 800, altura: 600, alt: `Alt ${id}`, url: `http://x/${id}.png`, miniatura_url: `http://x/${id}-t.png`, ...extra,
});
const pagina = (itens: any[], page = 1, last = 1, total = itens.length) => ({ data: itens, pager: { page, per_page: 24, total, last_page: last } });
const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

function montar(componente: any, props: Record<string, any> = {}) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const eventos: Record<string, any[]> = {};
  const app = createApp(componente, {
    ...props,
    onToast: (t: any) => toasts.push(t),
    'onUpdate:modelValue': (v: any) => (eventos.modelValue ||= []).push(v),
    onSelecionar: (m: any) => (eventos.selecionar ||= []).push(m),
  });
  app.mount(raiz);
  return { raiz, toasts, eventos };
}

function escolherArquivos(raiz: HTMLElement, arquivos: File[]) {
  const input = raiz.querySelector('[data-arquivos]') as HTMLInputElement;
  Object.defineProperty(input, 'files', { value: arquivos, configurable: true });
  input.dispatchEvent(new Event('change', { bubbles: true }));
}
const png = (nome = 'a.png', tamanho = 100) => new File([new Uint8Array(tamanho)], nome, { type: 'image/png' });

describe('MidiaBiblioteca', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    limparCacheDeMidias();
    Object.values(api).forEach((f) => f.mockReset());
    api.listarMidias.mockResolvedValue(pagina([midia(1), midia(2, { alt: null })], 1, 3, 50));
    (window as any).confirm = vi.fn(() => true);
  });
  afterEach(() => { vi.useRealTimers(); });

  it('lista a grade com total e paginação', async () => {
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    expect(raiz.querySelectorAll('[data-midia]')).toHaveLength(2);
    expect(raiz.textContent).toContain('50 imagens');
    expect(raiz.textContent).toContain('Página 1 de 3');
    expect(raiz.querySelector('[data-midia="2"]')?.textContent).toContain('Sem texto alternativo');
    expect((raiz.querySelector('[data-anterior]') as HTMLButtonElement).disabled).toBe(true);

    api.listarMidias.mockResolvedValue(pagina([midia(3)], 2, 3, 50));
    (raiz.querySelector('[data-proxima]') as HTMLButtonElement).click();
    await espera();

    expect(api.listarMidias).toHaveBeenLastCalledWith({ page: 2, per_page: 24, busca: '' });
    expect(raiz.textContent).toContain('Página 2 de 3');
  });

  it('busca com atraso e volta para a primeira página', async () => {
    vi.useFakeTimers();
    const { raiz } = montar(MidiaBiblioteca);
    await vi.advanceTimersByTimeAsync(0);
    api.listarMidias.mockClear();

    const campo = raiz.querySelector('input[type="search"]') as HTMLInputElement;
    campo.value = 'fachada';
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    await vi.advanceTimersByTimeAsync(100);
    expect(api.listarMidias).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(300);
    expect(api.listarMidias).toHaveBeenCalledWith({ page: 1, per_page: 24, busca: 'fachada' });
  });

  it('mensagem de biblioteca vazia e de busca sem resultado', async () => {
    api.listarMidias.mockResolvedValue(pagina([]));
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    expect(raiz.textContent).toContain('A biblioteca ainda não tem imagens.');
  });

  it('envia vários arquivos, recusa o inválido no cliente e recarrega', async () => {
    api.enviarMidia.mockResolvedValue({ data: midia(9) });
    const { raiz, toasts } = montar(MidiaBiblioteca);
    await espera();
    api.listarMidias.mockClear();

    const svg = new File(['<svg/>'], 'logo.svg', { type: 'image/svg+xml' });
    escolherArquivos(raiz, [png('a.png'), svg, png('b.png')]);
    await espera();

    expect(api.enviarMidia).toHaveBeenCalledTimes(2);
    expect(raiz.querySelector('.envios')?.textContent).toContain('"a.png" enviado.');
    expect(raiz.querySelector('.envios')?.textContent).toContain('"logo.svg": use uma imagem JPEG, PNG, GIF, WebP ou ICO.');
    expect(toasts.map((t) => t.type)).toEqual(['success', 'warning']);
    expect(api.listarMidias).toHaveBeenCalled();
  });

  it('mostra o erro da API no envio (422 em arquivo)', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.enviarMidia.mockRejectedValue(new ApiError('422', 422, { messages: { arquivo: 'A imagem deve ter no máximo 8000 px por lado.' } }));
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    escolherArquivos(raiz, [png('enorme.png')]);
    await espera();

    expect(raiz.querySelector('.envios')?.textContent).toContain('"enorme.png": A imagem deve ter no máximo 8000 px por lado.');
  });

  it('arrastar e soltar envia os arquivos', async () => {
    api.enviarMidia.mockResolvedValue({ data: midia(9) });
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    const soltar = new Event('drop', { bubbles: true, cancelable: true }) as any;
    soltar.dataTransfer = { files: [png('solta.png')] };
    raiz.querySelector('[data-envio]')!.dispatchEvent(soltar);
    await espera();

    expect(api.enviarMidia).toHaveBeenCalledTimes(1);
  });

  it('edita o texto alternativo', async () => {
    api.atualizarAltMidia.mockResolvedValue({ data: midia(2, { alt: 'Sala de reuniões' }) });
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    (raiz.querySelector('[data-midia="2"] [data-editar-alt]') as HTMLButtonElement).click();
    await espera();
    const campo = raiz.querySelector('[data-midia="2"] [data-alt-input]') as HTMLInputElement;
    campo.value = ' Sala de reuniões ';
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    (raiz.querySelector('[data-midia="2"] [data-salvar-alt]') as HTMLButtonElement).click();
    await espera();

    expect(api.atualizarAltMidia).toHaveBeenCalledWith(2, 'Sala de reuniões');
    expect(raiz.querySelector('[data-midia="2"]')?.textContent).toContain('Sala de reuniões');
  });

  it('mídia em uso: mostra o erro da API e não some da grade', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.excluirMidia.mockRejectedValue(new ApiError('422', 422, { messages: { midia: 'A mídia está em uso e não pode ser excluída: página "Início".' } }));
    const { raiz, toasts } = montar(MidiaBiblioteca);
    await espera();

    (raiz.querySelector('[data-midia="1"] [data-excluir]') as HTMLButtonElement).click();
    await espera();

    expect(raiz.querySelector('.erro')?.textContent).toContain('A mídia está em uso');
    expect(toasts.at(-1)?.type).toBe('danger');
    expect(raiz.querySelector('[data-midia="1"]')).not.toBeNull();
  });

  it('exclui após confirmar; cancelar não chama a API', async () => {
    api.excluirMidia.mockResolvedValue({ id: 1 });
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    (window.confirm as any).mockReturnValueOnce(false);
    (raiz.querySelector('[data-midia="1"] [data-excluir]') as HTMLButtonElement).click();
    await espera();
    expect(api.excluirMidia).not.toHaveBeenCalled();

    (raiz.querySelector('[data-midia="1"] [data-excluir]') as HTMLButtonElement).click();
    await espera();
    expect(api.excluirMidia).toHaveBeenCalledWith(1);
  });

  it('lista onde a imagem é usada', async () => {
    api.usosMidia.mockResolvedValue({ data: [{ tipo: 'configuracao', chave: 'identidade.logo' }, { tipo: 'secao', secao_id: 7, secao_tipo: 'hero', pagina_id: 1, titulo: 'Início' }] });
    const { raiz } = montar(MidiaBiblioteca);
    await espera();

    (raiz.querySelector('[data-midia="1"] [data-usos]') as HTMLButtonElement).click();
    await espera();

    const texto = raiz.querySelector('[data-lista-usos]')?.textContent ?? '';
    expect(texto).toContain('Configuração do site: identidade.logo');
    expect(texto).toContain('Seção "hero" da página "Início"');
  });

  it('em modo seleção, clicar na miniatura escolhe a imagem', async () => {
    const { raiz, eventos } = montar(MidiaBiblioteca, { modoSelecao: true, selecionadoId: 1 });
    await espera();

    expect(raiz.querySelector('[data-midia="1"]')?.classList.contains('cartao--escolhida')).toBe(true);
    (raiz.querySelector('[data-midia="2"] .miniatura') as HTMLButtonElement).click();

    expect(eventos.selecionar[0].id).toBe(2);
  });
});

describe('MidiaPicker', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    limparCacheDeMidias();
    Object.values(api).forEach((f) => f.mockReset());
    api.listarMidias.mockResolvedValue(pagina([midia(1), midia(2, { alt: null })]));
  });

  it('sem imagem: convida a escolher', async () => {
    const { raiz } = montar(MidiaPicker, { modelValue: null });
    await espera();

    expect(raiz.textContent).toContain('Nenhuma imagem escolhida.');
    expect(raiz.querySelector('[data-abrir]')?.textContent).toContain('Escolher imagem');
    expect(raiz.querySelector('[data-remover]')).toBeNull();
  });

  it('com imagem: busca a mídia pelo id e mostra miniatura e texto', async () => {
    api.obterMidia.mockResolvedValue({ data: midia(5) });
    const { raiz } = montar(MidiaPicker, { modelValue: 5 });
    await espera();

    expect(api.obterMidia).toHaveBeenCalledWith(5);
    expect(raiz.querySelector('.picker-miniatura img')?.getAttribute('src')).toBe('http://x/5-t.png');
    expect(raiz.textContent).toContain('foto-5.png');
    expect(raiz.querySelector('[data-abrir]')?.textContent).toContain('Trocar');
  });

  it('imagem que não existe mais é avisada', async () => {
    api.obterMidia.mockRejectedValue(new Error('404'));
    const { raiz } = montar(MidiaPicker, { modelValue: 77 });
    await espera();

    expect(raiz.textContent).toContain('A imagem #77 não foi encontrada na biblioteca.');
  });

  it('abre a biblioteca, escolhe e devolve o id (v-model)', async () => {
    api.obterMidia.mockResolvedValue({ data: midia(1) });
    const { raiz, eventos } = montar(MidiaPicker, { modelValue: 1 });
    await espera();

    (raiz.querySelector('[data-abrir]') as HTMLButtonElement).click();
    await espera();
    expect(raiz.querySelector('[role="dialog"]')).not.toBeNull();

    (raiz.querySelector('[data-midia="1"] .miniatura') as HTMLButtonElement).click();
    await espera();

    expect(eventos.modelValue).toEqual([1]);
    expect(raiz.querySelector('[role="dialog"]')).toBeNull();
  });

  it('remover devolve null', async () => {
    api.obterMidia.mockResolvedValue({ data: midia(1) });
    const { raiz, eventos } = montar(MidiaPicker, { modelValue: 1 });
    await espera();

    (raiz.querySelector('[data-remover]') as HTMLButtonElement).click();

    expect(eventos.modelValue).toEqual([null]);
  });

  it('exigir-alt: imagem sem descrição só é usada depois de descrita', async () => {
    api.atualizarAltMidia.mockResolvedValue({ data: midia(2, { alt: 'Recepção do escritório' }) });
    const { raiz, eventos } = montar(MidiaPicker, { modelValue: null, exigirAlt: true });
    await espera();
    (raiz.querySelector('[data-abrir]') as HTMLButtonElement).click();
    await espera();

    (raiz.querySelector('[data-midia="2"] .miniatura') as HTMLButtonElement).click();
    await espera();
    expect(raiz.querySelector('[data-alt-obrigatorio]')).not.toBeNull();
    expect(eventos.modelValue).toBeUndefined();

    // tentar usar sem descrever
    (raiz.querySelector('[data-usar]') as HTMLButtonElement).click();
    await espera();
    expect(raiz.querySelector('.picker-erro')?.textContent).toContain('Informe o texto alternativo');
    expect(api.atualizarAltMidia).not.toHaveBeenCalled();

    const campo = raiz.querySelector('[data-alt-obrigatorio] [data-alt-input]') as HTMLInputElement;
    campo.value = 'Recepção do escritório';
    campo.dispatchEvent(new Event('input', { bubbles: true }));
    (raiz.querySelector('[data-usar]') as HTMLButtonElement).click();
    await espera();

    expect(api.atualizarAltMidia).toHaveBeenCalledWith(2, 'Recepção do escritório');
    expect(eventos.modelValue).toEqual([2]);
  });

  it('exigir-alt: imagem que já tem descrição é usada direto', async () => {
    const { raiz, eventos } = montar(MidiaPicker, { modelValue: null, exigirAlt: true });
    await espera();
    (raiz.querySelector('[data-abrir]') as HTMLButtonElement).click();
    await espera();

    (raiz.querySelector('[data-midia="1"] .miniatura') as HTMLButtonElement).click();
    await espera();

    expect(raiz.querySelector('[data-alt-obrigatorio]')).toBeNull();
    expect(eventos.modelValue).toEqual([1]);
  });

  it('sem exigir-alt (configurações) usa a imagem mesmo sem descrição', async () => {
    const { raiz, eventos } = montar(MidiaPicker, { modelValue: null });
    await espera();
    (raiz.querySelector('[data-abrir]') as HTMLButtonElement).click();
    await espera();

    (raiz.querySelector('[data-midia="2"] .miniatura') as HTMLButtonElement).click();
    await espera();

    expect(eventos.modelValue).toEqual([2]);
  });
});
