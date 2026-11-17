// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({
  listarTemplates: vi.fn(), instalarTemplateComProgresso: vi.fn(), ativarTemplate: vi.fn(),
  paginasExemploPrevia: vi.fn(), paginasExemploTemplate: vi.fn(), removerTemplate: vi.fn(),
}));
const zip = vi.hoisted(() => ({ lerManifestoDoZip: vi.fn() }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return { ...real, apiFetch: vi.fn() };
});
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));
vi.mock('../src/services/siteApi', async () => {
  const real = await vi.importActual<typeof import('../src/services/siteApi')>('../src/services/siteApi');
  return { ...real, ...api };
});
vi.mock('../src/utils/zipManifesto', () => zip);

import TemplatesSiteView from '../src/views/Site/TemplatesSiteView.vue';

const tpl = (extra: Record<string, unknown> = {}) => ({
  slug: 'classico', nome: 'Clássico', versao: '1.2.0', autor: 'Cardoso & Bruno', descricao: 'Visual clássico.', origem: 'core',
  ativo: true, motor: 'php', removivel: false, preview_url: 'http://x/classico/preview.png', ...extra,
});
const LISTA = () => [
  tpl(),
  tpl({ slug: 'moderno', nome: 'Moderno', versao: '1.0.0', autor: null, descricao: null, origem: 'upload', ativo: false, motor: 'twig', removivel: true, preview_url: null }),
];
const espera = async () => { for (let i = 0; i < 10; i++) { await nextTick(); await Promise.resolve(); } };

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const paginas: number[] = [];
  createApp(TemplatesSiteView, { onToast: (t: any) => toasts.push(t), onAbrirPagina: (id: number) => paginas.push(id) }).mount(raiz);
  return { raiz, toasts, paginas };
}
const q = (sel: string, raiz: ParentNode = document.body) => raiz.querySelector(sel) as HTMLElement;
const clicar = (el: Element | null) => (el as HTMLElement).click();
function escolherZip(nome = 'moderno.zip', tamanho = 2048) {
  const input = q('[data-arquivo]') as HTMLInputElement;
  const arquivo = new File([new Uint8Array(tamanho)], nome, { type: 'application/zip' });
  Object.defineProperty(input, 'files', { value: [arquivo], configurable: true });
  input.dispatchEvent(new Event('change', { bubbles: true }));
  return arquivo;
}

describe('TemplatesSiteView', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    Object.values(api).forEach((f) => f.mockReset());
    zip.lerManifestoDoZip.mockReset().mockResolvedValue({ slug: 'novo', nome: 'Novo', versao: '1.0.0' });
    api.listarTemplates.mockResolvedValue({ data: LISTA() });
    (window as any).confirm = vi.fn(() => true);
  });

  it('galeria: prévia, nome, versão, autor, selo Ativo e origem', async () => {
    montar();
    await espera();

    const classico = q('[data-template="classico"]');
    expect(q('img', classico).getAttribute('src')).toBe('http://x/classico/preview.png');
    expect(classico.textContent).toContain('Clássico');
    expect(classico.textContent).toContain('Versão 1.2.0 · Cardoso & Bruno');
    expect(q('[data-ativo]', classico).textContent).toBe('Ativo');
    expect(q('[data-origem]', classico).textContent).toContain('Padrão do sistema');

    const moderno = q('[data-template="moderno"]');
    expect(moderno.querySelector('img')).toBeNull();
    expect(moderno.querySelector('[data-ativo]')).toBeNull();
    expect(q('[data-origem]', moderno).textContent).toContain('Enviado pelo painel');
  });

  it('remover: desabilitado para o ativo e para os padrões; habilitado para o enviado', async () => {
    montar();
    await espera();

    expect((q('[data-template="classico"] [data-remover]') as HTMLButtonElement).disabled).toBe(true);
    expect((q('[data-template="moderno"] [data-remover]') as HTMLButtonElement).disabled).toBe(false);
    expect(document.querySelector('[data-template="classico"] [data-ativar]')).toBeNull();
    expect(document.querySelector('[data-template="moderno"] [data-ativar]')).not.toBeNull();
  });

  it('remover pede confirmação, chama a API e recarrega', async () => {
    api.removerTemplate.mockResolvedValue({ slug: 'moderno' });
    const { toasts } = montar();
    await espera();

    (window.confirm as any).mockReturnValueOnce(false);
    clicar(q('[data-template="moderno"] [data-remover]'));
    await espera();
    expect(api.removerTemplate).not.toHaveBeenCalled();

    clicar(q('[data-template="moderno"] [data-remover]'));
    await espera();
    expect(api.removerTemplate).toHaveBeenCalledWith('moderno');
    expect(api.listarTemplates).toHaveBeenCalledTimes(2);
    expect(toasts.at(-1)?.type).toBe('success');
  });

  it('enviar .zip novo: resumo "instalar", progresso e sucesso com avisos', async () => {
    api.instalarTemplateComProgresso.mockImplementation(async (_f: File, progresso: (n: number) => void) => {
      progresso(0.5);
      return { data: { slug: 'novo', nome: 'Novo', versao: '1.0.0', origem: 'upload', ativo: false }, atualizado: false, avisos: ['O template não tem prévia.'] };
    });
    const { toasts } = montar();
    await espera();

    escolherZip('novo.zip');
    await espera();
    expect(q('[data-resumo]').textContent).toContain('Instalar "Novo" 1.0.0');

    clicar(q('[data-confirmar-envio]'));
    await espera();

    expect(api.instalarTemplateComProgresso).toHaveBeenCalledTimes(1);
    expect(q('[data-sucesso]').textContent).toContain('Template instalado');
    expect(q('[data-sucesso]').textContent).toContain('Ele ainda não está ativo');
    expect(q('[data-sucesso] [data-avisos]').textContent).toContain('não tem prévia');
    expect(api.listarTemplates).toHaveBeenCalledTimes(2);
    expect(toasts.at(-1)?.type).toBe('success');
  });

  it('enviar nova versão mostra "atualizar de 1.0.0 para 1.1.0"', async () => {
    zip.lerManifestoDoZip.mockResolvedValue({ slug: 'moderno', nome: 'Moderno', versao: '1.1.0' });
    api.instalarTemplateComProgresso.mockResolvedValue({ data: { slug: 'moderno', nome: 'Moderno', versao: '1.1.0' }, atualizado: true, avisos: [] });
    montar();
    await espera();

    escolherZip();
    await espera();
    expect(q('[data-resumo]').textContent).toContain('de 1.0.0 para 1.1.0');
    expect(q('[data-confirmar-envio]').textContent?.trim()).toBe('Atualizar');

    clicar(q('[data-confirmar-envio]'));
    await espera();
    expect(q('[data-sucesso]').textContent).toContain('Template atualizado');
  });

  it('pacote com slug de template do sistema: envio bloqueado', async () => {
    zip.lerManifestoDoZip.mockResolvedValue({ slug: 'classico', nome: 'Clássico', versao: '9.0.0' });
    montar();
    await espera();

    escolherZip();
    await espera();

    expect(q('[data-resumo]').textContent).toContain('não pode ser sobrescrito');
    expect((q('[data-confirmar-envio]') as HTMLButtonElement).disabled).toBe(true);
  });

  it('pacote ilegível no navegador ainda pode ser enviado (o backend valida)', async () => {
    zip.lerManifestoDoZip.mockResolvedValue(null);
    montar();
    await espera();

    escolherZip();
    await espera();

    expect(document.querySelector('[data-resumo]')).toBeNull();
    expect(q('[data-sem-resumo]').textContent).toContain('será validado no envio');
    expect((q('[data-confirmar-envio]') as HTMLButtonElement).disabled).toBe(false);
  });

  it('arquivo que não é .zip é recusado antes de abrir o envio', async () => {
    const { toasts } = montar();
    await espera();

    escolherZip('template.rar');
    await espera();

    expect(document.querySelector('[role="dialog"]')).toBeNull();
    expect(toasts.at(-1)?.type).toBe('warning');
  });

  it('relatório de validação (422): erros que bloqueiam × avisos', async () => {
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.instalarTemplateComProgresso.mockRejectedValue(new ApiError('422', 422, {
      messages: { 'layouts/main.php': 'Arquivos PHP não são permitidos em templates enviados pelo painel.', 'template.json': 'O campo "versao" é obrigatório.' },
      avisos: ['O template não traz conteúdo inicial.'],
    }));
    montar();
    await espera();

    escolherZip();
    await espera();
    clicar(q('[data-confirmar-envio]'));
    await espera();

    const rel = q('[data-relatorio]');
    expect(rel.textContent).toContain('nada foi instalado');
    expect([...rel.querySelectorAll('[data-erros] li')].map((l) => l.textContent)).toEqual([
      'layouts/main.php Arquivos PHP não são permitidos em templates enviados pelo painel.',
      'template.json O campo "versao" é obrigatório.',
    ]);
    expect(q('[data-avisos]', rel).textContent).toContain('conteúdo inicial');
    expect(document.querySelector('[data-sucesso]')).toBeNull();
  });

  it('falha sem relatório (rede) mostra a mensagem e permite tentar de novo', async () => {
    api.instalarTemplateComProgresso.mockRejectedValue(new Error('rede caiu'));
    montar();
    await espera();

    escolherZip();
    await espera();
    clicar(q('[data-confirmar-envio]'));
    await espera();

    expect(q('[role="dialog"] .erro').textContent).toContain('rede caiu');
    expect((q('[data-confirmar-envio]') as HTMLButtonElement).disabled).toBe(false);
  });

  it('ativar: confirmação explica que o conteúdo é mantido; depois mostra o relatório de compatibilidade com link para o editor', async () => {
    api.ativarTemplate.mockResolvedValue({ data: {
      template: 'moderno', ativado: true, seed: null, avisos: ['O menu "rodape" do template está vazio.'],
      secoes: [
        { id: 12, tipo: 'faq', pagina: 'Sobre', pagina_id: 4, acao: 'incompativel', erros: [] },
        { id: 15, tipo: 'hero', pagina: 'Início', pagina_id: 1, acao: 'revisar', erros: { titulo: 'Campo obrigatório.' } },
      ],
    } });
    const { paginas } = montar();
    await espera();

    clicar(q('[data-template="moderno"] [data-ativar]'));
    await espera();
    expect(q('[role="dialog"]').textContent).toContain('conteúdo é mantido');
    expect(q('[role="dialog"]').textContent).toContain('ocultas');

    clicar(q('[data-confirmar-ativacao]'));
    await espera();

    expect(api.ativarTemplate).toHaveBeenCalledWith('moderno');
    const rel = q('[data-relatorio-ativacao]');
    expect(rel.textContent).toContain('Template ativado.');
    expect(q('[data-grupo="4"]', rel).textContent).toContain('tipo inexistente neste template');
    expect(q('[data-grupo="1"]', rel).textContent).toContain('precisa de revisão');
    expect(q('[data-avisos]', rel).textContent).toContain('rodape');

    clicar(q('[data-grupo="4"] [data-abrir-editor]'));
    await espera();
    expect(paginas).toEqual([4]);
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });

  it('ativar sem pendências diz que nada ficou incompatível; site vazio importa o conteúdo de exemplo', async () => {
    api.ativarTemplate.mockResolvedValue({ data: { template: 'moderno', ativado: true, secoes: [], avisos: [], seed: { paginas: ['inicio'] } } });
    montar();
    await espera();

    clicar(q('[data-template="moderno"] [data-ativar]'));
    await espera();
    clicar(q('[data-confirmar-ativacao]'));
    await espera();

    expect(document.querySelector('[data-sem-pendencias]')).not.toBeNull();
    expect(q('[data-relatorio-ativacao]').textContent).toContain('conteúdo de exemplo do template foi importado');
  });

  it('falha ao ativar mostra o erro e permite tentar de novo', async () => {
    api.ativarTemplate.mockRejectedValue(new Error('falhou'));
    montar();
    await espera();

    clicar(q('[data-template="moderno"] [data-ativar]'));
    await espera();
    clicar(q('[data-confirmar-ativacao]'));
    await espera();

    expect(q('[role="dialog"] .erro').textContent).toContain('falhou');
    expect((q('[data-confirmar-ativacao]') as HTMLButtonElement).disabled).toBe(false);
  });

  it('páginas de exemplo: mostra o que será criado antes de confirmar e só então cria', async () => {
    api.paginasExemploPrevia.mockResolvedValue({ data: { criar: [{ slug: 'inicio-moderno', titulo: 'Início', secoes: 5 }, { slug: 'sobre-moderno', titulo: 'Sobre', secoes: 1 }], ignoradas: ['contato'] } });
    api.paginasExemploTemplate.mockResolvedValue({ data: { paginas: ['inicio-moderno', 'sobre-moderno'], paginas_ignoradas: ['contato'], erros: {} } });
    const { toasts } = montar();
    await espera();

    clicar(q('[data-template="moderno"] [data-exemplo]'));
    await espera();

    expect(api.paginasExemploTemplate).not.toHaveBeenCalled();
    const itens = [...document.querySelectorAll('[data-criar] li')].map((l) => l.textContent);
    expect(itens).toEqual(['Início /pagina/inicio-moderno · 5 seções', 'Sobre /pagina/sobre-moderno · 1 seção']);
    expect(q('[role="dialog"]').textContent).toContain('Já existem e serão mantidas: contato');

    clicar(q('[data-confirmar-exemplo]'));
    await espera();

    expect(api.paginasExemploTemplate).toHaveBeenCalledWith('moderno');
    expect(q('[data-criadas]').textContent).toContain('2 páginas criadas como rascunho');
    expect(toasts.at(-1)?.type).toBe('success');
  });

  it('páginas de exemplo: nada a criar desabilita a confirmação; template sem conteúdo mostra o erro', async () => {
    api.paginasExemploPrevia.mockResolvedValue({ data: { criar: [], ignoradas: ['inicio'] } });
    montar();
    await espera();

    clicar(q('[data-template="moderno"] [data-exemplo]'));
    await espera();
    expect(document.querySelector('[data-nada-a-criar]')).not.toBeNull();
    expect((q('[data-confirmar-exemplo]') as HTMLButtonElement).disabled).toBe(true);

    clicar(q('[data-fechar-modal]'));
    await espera();
    const { ApiError } = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
    api.paginasExemploPrevia.mockRejectedValue(new ApiError('422', 422, { messages: { template: 'Este template não traz conteúdo inicial.' } }));
    clicar(q('[data-template="moderno"] [data-exemplo]'));
    await espera();
    expect(q('[data-erro-exemplo]').textContent).toContain('não traz conteúdo inicial');
  });

  it('exportar: links de download com e sem o conteúdo atual', async () => {
    montar();
    await espera();

    clicar(q('[data-template="moderno"] [data-exportar]'));
    await espera();

    expect(q('[data-exportar-sem]').getAttribute('href')).toMatch(/\/site\/admin\/templates\/moderno\/exportar$/);
    expect(q('[data-exportar-com]').getAttribute('href')).toMatch(/\/site\/admin\/templates\/moderno\/exportar\?conteudo=1$/);
    expect(q('[data-exportar-sem]').hasAttribute('download')).toBe(true);
  });

  it('falha ao carregar mostra o erro com nova tentativa', async () => {
    api.listarTemplates.mockRejectedValueOnce(new Error('rede caiu')).mockResolvedValue({ data: LISTA() });
    montar();
    await espera();

    expect(document.body.textContent).toContain('rede caiu');
    clicar(q('[role="alert"] button'));
    await espera();
    expect(document.querySelectorAll('[data-template]')).toHaveLength(2);
  });
});
