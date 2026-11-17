// @vitest-environment happy-dom
import { describe, it, expect, vi } from 'vitest';
import { createApp, defineComponent, h, nextTick, ref } from 'vue';

vi.mock('../src/services/api', () => ({
  apiFetch: vi.fn(async () => ({ data: [{ id: 2, titulo: 'Direito Civil' }, { id: 3, titulo: 'Contato' }] })),
  ApiError: class extends Error {},
  BASE_URL: 'http://localhost/api/',
}));
vi.mock('../src/services/siteApi', () => ({
  obterMidia: vi.fn(async (id: number) => ({ data: { id, nome_original: 'foto.png', alt: 'Fachada', largura: 10, altura: 10, miniatura_url: 'http://x/t.png', url: 'http://x/f.png' } })),
  listarMidias: vi.fn(async () => ({ data: [], pager: { page: 1, per_page: 24, total: 0, last_page: 1 } })),
  listarPaginas: vi.fn(async () => ({ data: [] })),
  atualizarAltMidia: vi.fn(), enviarMidia: vi.fn(), excluirMidia: vi.fn(), usosMidia: vi.fn(),
  errosDeCampo: () => ({}),
}));
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));

import SchemaForm from '../src/components/site/SchemaForm.vue';
import { completarDados, dadosParaEnvio } from '../src/utils/schemaForm';
import type { CamposSchema } from '../src/types/site';

const CAMPOS: CamposSchema = {
  sobretitulo: { tipo: 'texto', max: 20 },
  titulo: { tipo: 'texto_inline', obrigatorio: true },
  subtitulo: { tipo: 'textarea' },
  corpo: { tipo: 'rich_text' },
  foto: { tipo: 'imagem' },
  icone: { tipo: 'icone', padrao: 'bi-award' },
  destino: { tipo: 'link' },
  alinhamento: { tipo: 'opcao', opcoes: ['esquerda', 'centro'], padrao: 'centro' },
  destaque: { tipo: 'bool' },
  menu: { tipo: 'referencia_menu' },
  noticias: { tipo: 'dinamico', fonte: 'ultimas_noticias' },
  botoes: {
    tipo: 'lista',
    itens: {
      rotulo: { tipo: 'texto', obrigatorio: true, max: 40 },
      estilo: { tipo: 'opcao', opcoes: ['primario', 'contorno'], padrao: 'primario' },
    },
  },
};

/** Monta o formulário num DOM simulado e expõe o valor atual (v-model) e o raiz. */
function montar(inicial: Record<string, any> = {}, errors: Record<string, string> = {}) {
  const valor = ref<Record<string, any>>(completarDados(CAMPOS, inicial));
  const erros = ref(errors);
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);

  const Pai = defineComponent({
    render: () => h(SchemaForm, {
      campos: CAMPOS,
      modelValue: valor.value,
      errors: erros.value,
      menus: ['cabecalho', 'rodape'],
      paginas: [{ id: 2, titulo: 'Direito Civil' }],
      'onUpdate:modelValue': (v: Record<string, any>) => { valor.value = v; },
    }),
  });
  createApp(Pai).mount(raiz);

  return { raiz, valor, erros };
}

function digitar(el: Element, texto: string) {
  (el as HTMLInputElement).value = texto;
  el.dispatchEvent(new Event('input', { bubbles: true }));
}

describe('SchemaForm', () => {
  it('renderiza todos os tipos de campo', async () => {
    const { raiz } = montar();
    await nextTick();

    const campo = (nome: string) => raiz.querySelector(`[data-campo="${nome}"]`) as HTMLElement;

    expect(campo('sobretitulo').querySelector('input[type="text"]')).not.toBeNull();
    expect(campo('titulo').querySelector('input[type="text"]')).not.toBeNull();
    expect(campo('subtitulo').querySelector('textarea')).not.toBeNull();
    expect(campo('corpo').querySelector('.doc-editor')).not.toBeNull();
    expect(campo('foto').querySelector('[data-abrir]')?.textContent).toContain('Escolher imagem');
    expect(campo('icone').querySelector('.icone-grade')).not.toBeNull();
    expect(campo('destino').querySelector('.link-seletor')).not.toBeNull();
    expect(campo('alinhamento').querySelectorAll('option')).toHaveLength(2);
    expect(campo('destaque').querySelector('input[type="checkbox"]')).not.toBeNull();
    expect(campo('menu').querySelectorAll('option')).toHaveLength(3);
    expect(campo('noticias').textContent).toContain('Conteúdo automático: últimas notícias');
    expect(campo('botoes').querySelector('button')?.textContent).toContain('Adicionar');
  });

  it('rich_text não mostra placeholders nem moldura de página', async () => {
    const { raiz } = montar();
    await nextTick();

    const editor = raiz.querySelector('[data-campo="corpo"]') as HTMLElement;
    expect(editor.querySelector('.doc-tb-btn--accent')).toBeNull();
    expect(editor.querySelector('.doc-editor-frame--flat')).not.toBeNull();
    expect(editor.querySelector('[title="Inserir tabela"]')).toBeNull();
    expect(editor.querySelector('[title="Negrito (Ctrl+B)"]')).not.toBeNull();
  });

  it('contador de caracteres quando há max', async () => {
    const { raiz } = montar({ sobretitulo: 'abc' });
    await nextTick();

    expect(raiz.querySelector('[data-campo="sobretitulo"] .schema-contador')?.textContent).toBe('3/20');
  });

  it('digitar atualiza o valor (v-model) sem perder o resto', async () => {
    const { raiz, valor } = montar({ subtitulo: 'fica' });
    await nextTick();

    digitar(raiz.querySelector('[data-campo="titulo"] input')!, 'Novo título');
    await nextTick();

    expect(valor.value.titulo).toBe('Novo título');
    expect(valor.value.subtitulo).toBe('fica');
  });

  it('select e switch fazem o round-trip', async () => {
    const { raiz, valor } = montar();
    await nextTick();

    const sel = raiz.querySelector('[data-campo="alinhamento"] select') as HTMLSelectElement;
    sel.value = 'esquerda';
    sel.dispatchEvent(new Event('change', { bubbles: true }));
    await nextTick();

    const sw = raiz.querySelector('[data-campo="destaque"] input') as HTMLInputElement;
    sw.checked = true;
    sw.dispatchEvent(new Event('change', { bubbles: true }));
    await nextTick();


    expect(valor.value.alinhamento).toBe('esquerda');
    expect(valor.value.destaque).toBe(true);
  });

  it('o ícone vem do clique na grade', async () => {
    const { raiz, valor } = montar();
    await nextTick();

    (raiz.querySelector('[data-campo="icone"] button[title="bi-bank"]') as HTMLButtonElement).click();
    await nextTick();

    expect(valor.value.icone).toBe('bi-bank');
  });

  it('link: trocar o tipo e escolher o destino', async () => {
    const { raiz, valor } = montar();
    await nextTick();

    const tipo = raiz.querySelector('[data-campo="destino"] select.link-tipo') as HTMLSelectElement;
    tipo.value = 'url';
    tipo.dispatchEvent(new Event('change', { bubbles: true }));
    await nextTick();
    digitar(raiz.querySelector('[data-campo="destino"] input')!, 'https://exemplo.com');
    await nextTick();

    expect(valor.value.destino).toEqual({ tipo: 'url', url: 'https://exemplo.com' });
  });

  it('lista: adicionar, editar, reordenar e remover itens', async () => {
    const { raiz, valor } = montar();
    await nextTick();
    const botoes = () => raiz.querySelector('[data-campo="botoes"]') as HTMLElement;
    const adicionar = () => (botoes().querySelector(':scope > .schema-lista > button') as HTMLButtonElement).click();

    adicionar();
    await nextTick();
    adicionar();
    await nextTick();
    expect(valor.value.botoes).toHaveLength(2);
    expect(valor.value.botoes[0]).toEqual({ rotulo: '', estilo: 'primario' });

    digitar(raiz.querySelector('[data-campo="botoes.0.rotulo"] input')!, 'Primeiro');
    await nextTick();
    digitar(raiz.querySelector('[data-campo="botoes.1.rotulo"] input')!, 'Segundo');
    await nextTick();
    expect(valor.value.botoes.map((b: any) => b.rotulo)).toEqual(['Primeiro', 'Segundo']);

    (raiz.querySelector('[data-item="botoes.0"] button[title="Mover para baixo"]') as HTMLButtonElement).click();
    await nextTick();
    expect(valor.value.botoes.map((b: any) => b.rotulo)).toEqual(['Segundo', 'Primeiro']);

    (raiz.querySelector('[data-item="botoes.0"] button[title="Remover"]') as HTMLButtonElement).click();
    await nextTick();
    expect(valor.value.botoes.map((b: any) => b.rotulo)).toEqual(['Primeiro']);
  });

  it('arrastar e soltar reordena a lista', async () => {
    const { raiz, valor } = montar({ botoes: [{ rotulo: 'A' }, { rotulo: 'B' }, { rotulo: 'C' }] });
    await nextTick();

    raiz.querySelector('[data-item="botoes.0"] .schema-alca')!.dispatchEvent(new Event('dragstart', { bubbles: true }));
    raiz.querySelector('[data-item="botoes.2"]')!.dispatchEvent(new Event('drop', { bubbles: true, cancelable: true }));
    await nextTick();

    expect(valor.value.botoes.map((b: any) => b.rotulo)).toEqual(['B', 'C', 'A']);
  });

  it('mostra o erro 422 no campo certo, inclusive dentro de listas', async () => {
    const { raiz } = montar(
      { botoes: [{ rotulo: 'ok' }, { rotulo: '' }] },
      { titulo: 'Campo obrigatório.', 'botoes.1.rotulo': 'Campo obrigatório.', 'botoes.1.estilo': 'Valor não está entre as opções.' },
    );
    await nextTick();

    const erroDe = (caminho: string) => raiz.querySelector(`[data-campo="${caminho}"] > .schema-erro`)?.textContent;

    expect(erroDe('titulo')).toBe('Campo obrigatório.');
    expect(erroDe('botoes.1.rotulo')).toBe('Campo obrigatório.');
    expect(erroDe('botoes.1.estilo')).toBe('Valor não está entre as opções.');
    // quem não tem erro não mostra nada
    expect(erroDe('botoes.0.rotulo')).toBeUndefined();
    expect(erroDe('sobretitulo')).toBeUndefined();
    // a lista fica marcada, mas a mensagem fica no item
    expect(raiz.querySelector('[data-campo="botoes"]')?.classList.contains('schema-campo--erro')).toBe(true);
    expect(raiz.querySelector('[data-campo="botoes.1.rotulo"]')?.classList.contains('schema-campo--erro')).toBe(true);
    expect(raiz.querySelector('[data-campo="botoes.0.rotulo"]')?.classList.contains('schema-campo--erro')).toBe(false);
  });

  it('o valor do formulário vira o corpo da API sem campos dinâmicos', async () => {
    const { raiz, valor } = montar({ titulo: 'T' });
    await nextTick();
    digitar(raiz.querySelector('[data-campo="sobretitulo"] input')!, 'Sobre');
    await nextTick();

    const envio = dadosParaEnvio(CAMPOS, valor.value);

    expect(envio.sobretitulo).toBe('Sobre');
    expect('noticias' in envio).toBe(false);
  });
});
