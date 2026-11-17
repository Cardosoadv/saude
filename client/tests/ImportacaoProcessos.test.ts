// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const chamadas = vi.hoisted(() => ({ lista: [] as { endpoint: string, body: any }[], respostas: {} as Record<string, any> }));

vi.mock('../src/services/api', async () => {
  const real = await vi.importActual<typeof import('../src/services/api')>('../src/services/api');
  return {
    ...real,
    apiFetch: vi.fn(async (endpoint: string, opts: any = {}) => {
      chamadas.lista.push({ endpoint, body: opts.body });
      const chave = Object.keys(chamadas.respostas).find((k) => endpoint.startsWith(k));
      return chave ? chamadas.respostas[chave] : { success: true, data: [] };
    }),
  };
});

import ImportacaoProcessos from '../src/views/Processos/ImportacaoProcessos.vue';

const espera = async () => { for (let i = 0; i < 12; i++) { await nextTick(); await Promise.resolve(); } };

const NUMERO = '08012340420258260100';
const MASCARA = '0801234-04.2025.8.26.0100';

function conferenciaCadastrado() {
  return {
    numero_processo: NUMERO,
    numeroprocessocommascara: MASCARA,
    situacao: 'cadastrado',
    id_processo: 99,
    importavel: true,
    fontes: { djen: { status: 'encontrado', mensagem: null }, datajud: { status: 'nao_encontrado', mensagem: null } },
    avisos: [],
    sigilo: false,
    informacoes: {},
    clientes_sugeridos: [],
    etiquetas: { arquivo: ['Urgente', 'Nova'], carteira: ['Urgente'] },
    partes: [
      { nome: 'Maria da Silva', polo: 'A', fontes: ['djen'], na_carteira: true, incluir: false },
      { nome: 'Banco Exemplo S.A.', polo: 'P', fontes: ['djen'], na_carteira: false, incluir: true },
    ],
    campos: [
      { campo: 'titulo_processo', rotulo: 'Título', tipo: 'texto', bloqueado: false, divergente: false, gerado: false,
        opcoes: [{ fonte: 'carteira', valor: 'Maria x Banco', rotulo: 'Maria x Banco' }], atual: 'Maria x Banco', sugestao: 'Maria x Banco' },
      { campo: 'cliente_id', rotulo: 'Cliente', tipo: 'cliente', bloqueado: false, divergente: false,
        opcoes: [{ fonte: 'carteira', valor: 10, rotulo: 'Maria da Silva' }], atual: 10, sugestao: 10 },
      { campo: 'contrato_id', rotulo: 'Contrato', tipo: 'contrato', bloqueado: false, divergente: false,
        opcoes: [], atual: null, sugestao: null },
      { campo: 'nomeOrgao', rotulo: 'Órgão', tipo: 'texto', bloqueado: false, divergente: true,
        opcoes: [
          { fonte: 'djen', valor: '2ª VARA', rotulo: '2ª VARA' },
          { fonte: 'carteira', valor: '1ª Vara', rotulo: '1ª Vara' },
        ], atual: '1ª Vara', sugestao: '1ª Vara' },
      { campo: 'valorCausa', rotulo: 'Valor da causa', tipo: 'valor', bloqueado: false, divergente: false,
        opcoes: [{ fonte: 'arquivo', valor: '1000.00', rotulo: '1000.00' }, { fonte: 'carteira', valor: '1000.00', rotulo: '1000.00' }], atual: '1000.00', sugestao: '1000.00' },
      { campo: 'responsavel_id', rotulo: 'Responsável', tipo: 'usuario', bloqueado: true, divergente: true,
        opcoes: [{ fonte: 'arquivo', valor: 6, rotulo: 'outro' }, { fonte: 'carteira', valor: 5, rotulo: 'fabiano' }], atual: 5, sugestao: 5 },
    ],
  };
}

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const app = createApp(ImportacaoProcessos, { onToast: (t: any) => toasts.push(t) });
  app.mount(raiz);
  return { raiz, toasts, desmontar: () => { app.unmount(); raiz.remove(); } };
}

const botao = (raiz: HTMLElement, re: RegExp) =>
  Array.from(raiz.querySelectorAll('button')).find((b) => re.test(b.textContent?.trim() ?? '')) as HTMLButtonElement;

/** "Importar selecionados" abre a confirmação; "Confirmar e gravar" envia. */
async function importarEConfirmar(raiz: HTMLElement) {
  botao(raiz, /Importar selecionados/).click();
  await espera();
  botao(raiz, /Confirmar e gravar/).click();
  await espera();
}

async function enviarArquivo(raiz: HTMLElement) {
  const campo = raiz.querySelector('input[type=file]') as HTMLInputElement;
  Object.defineProperty(campo, 'files', { value: [new File([MASCARA], 'lote.csv')], configurable: true });
  campo.dispatchEvent(new Event('change'));
  await espera();
  botao(raiz, /Ler e consultar/).click();
  await espera();
}

describe('ImportacaoProcessos', () => {
  let m: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    chamadas.lista = [];
    chamadas.respostas = {
      'processos/importacao/ler': {
        success: true,
        data: {
          formato: 'csv', total: 2,
          processos: [{ linha: 1, numero_processo: NUMERO, numeroprocessocommascara: MASCARA, arquivo: { etiquetas: ['Urgente', 'Nova'] }, avisos: [] }],
          erros: [{ linha: 2, numero_processo: '123', motivo: 'Número CNJ deve ter 20 dígitos (tem 3).' }],
        },
      },
      'processos/importacao/consultar': { success: true, data: conferenciaCadastrado() },
      'processos/importacao/confirmar': {
        success: true, message: 'Importação concluída: 1 atualizado(s).',
        data: { criados: 0, atualizados: 1, sem_alteracao: 0, falhas: 0, resultados: [{ numero_processo: NUMERO, numeroprocessocommascara: MASCARA, status: 'atualizado', id_processo: 99, mensagem: null }] },
      },
    };
  });
  afterEach(() => { m?.desmontar(); m = null; });

  it('mostra erros do arquivo e consulta cada processo', async () => {
    m = montar();
    await enviarArquivo(m.raiz);

    expect(m.raiz.textContent).toContain('20 dígitos');
    const consulta = chamadas.lista.find((c) => c.endpoint === 'processos/importacao/consultar');
    expect(consulta?.body).toEqual({ numero_processo: NUMERO, arquivo: { etiquetas: ['Urgente', 'Nova'] } });
    expect(m.raiz.textContent).toContain('Já cadastrado');
    expect(m.raiz.textContent).toContain('1 divergência(s)');
  });

  it('só libera a importação depois de resolver a divergência e envia apenas o que mudou', async () => {
    m = montar();
    await enviarArquivo(m.raiz);

    const importar = botao(m.raiz, /Importar selecionados/);
    expect(importar.disabled).toBe(true);
    expect(m.raiz.textContent).toContain('Resolva 1 divergência(s)');

    // Campo bloqueado (responsável) não conta como pendente nem aceita escolha.
    const radiosResp = m.raiz.querySelectorAll<HTMLInputElement>(`input[name="${NUMERO}-responsavel_id"]`);
    expect(Array.from(radiosResp).every((r) => r.disabled)).toBe(true);

    const radios = m.raiz.querySelectorAll<HTMLInputElement>(`input[name="${NUMERO}-nomeOrgao"]`);
    radios[0].click();
    await espera();

    expect(importar.disabled).toBe(false);
    await importarEConfirmar(m.raiz);

    const envio = chamadas.lista.find((c) => c.endpoint === 'processos/importacao/confirmar');
    expect(envio?.body).toEqual({
      processos: [{
        numero_processo: NUMERO,
        id_processo: 99,
        campos: { nomeOrgao: '2ª VARA' },
        partes: [{ nome: 'Banco Exemplo S.A.', polo: 'P' }],
        etiquetas: ['Nova'],
      }],
    });
    expect(m.raiz.textContent).toContain('Atualizado');
    expect(m.toasts.at(-1)?.type).toBe('success');
  });

  it('clicar na opção que já é a sugestão também resolve a divergência, sem gerar alteração', async () => {
    m = montar();
    await enviarArquivo(m.raiz);

    const radios = m.raiz.querySelectorAll<HTMLInputElement>(`input[name="${NUMERO}-nomeOrgao"]`);
    expect(radios[1].checked).toBe(true); // a carteira é a sugestão
    radios[1].click();
    await espera();

    expect(m.raiz.textContent).toContain('Confirmado');
    await importarEConfirmar(m.raiz);

    const envio = chamadas.lista.find((c) => c.endpoint === 'processos/importacao/confirmar');
    expect(envio?.body.processos[0].campos).toEqual({});
  });

  it('"Confirmar este valor" aceita a sugestão do campo', async () => {
    m = montar();
    await enviarArquivo(m.raiz);

    botao(m.raiz, /Confirmar este valor/).click();
    await espera();

    expect(botao(m.raiz, /Confirmar este valor/)).toBeUndefined();
    expect(botao(m.raiz, /Importar selecionados/).disabled).toBe(false);
  });

  it('"Aceitar todas as sugestões" resolve as divergências do lote', async () => {
    m = montar();
    await enviarArquivo(m.raiz);

    botao(m.raiz, /Aceitar todas as sugestões \(1\)/).click();
    await espera();

    expect(botao(m.raiz, /Importar selecionados/).disabled).toBe(false);
    expect(m.toasts.at(-1)?.msg).toContain('1 divergência(s) confirmada(s)');
  });

  it('busca o cliente com autocompletar e aplica o mesmo cliente a todo o lote', async () => {
    chamadas.respostas['processos/clientes-busca'] = { results: [{ id: 42, text: 'Empresa Lote Ltda - 11.222.333/0001-81' }] };
    m = montar();
    await enviarArquivo(m.raiz);

    const busca = m.raiz.querySelector<HTMLInputElement>('input[aria-label="Buscar cliente para todo o lote"]')!;
    busca.value = 'Empresa';
    busca.dispatchEvent(new Event('input'));
    await new Promise((r) => setTimeout(r, 350));
    await espera();

    const opcao = m.raiz.querySelector<HTMLElement>('[role="listbox"] [role="option"]')!;
    expect(opcao.textContent).toContain('Empresa Lote Ltda');
    opcao.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    await espera();

    botao(m.raiz, /Aplicar ao lote/).click();
    await espera();
    expect(m.toasts.at(-1)?.msg).toContain('aplicado a 1 processo(s)');

    botao(m.raiz, /Aceitar todas/).click();
    await espera();
    await importarEConfirmar(m.raiz);

    const envio = chamadas.lista.find((c) => c.endpoint === 'processos/importacao/confirmar');
    expect(envio?.body.processos[0].campos.cliente_id).toBe(42);
  });

  it('"só os que estão sem cliente" não troca o cliente já definido', async () => {
    chamadas.respostas['processos/clientes-busca'] = { results: [{ id: 42, text: 'Empresa Lote Ltda' }] };
    m = montar();
    await enviarArquivo(m.raiz);

    const busca = m.raiz.querySelector<HTMLInputElement>('input[aria-label="Buscar cliente para todo o lote"]')!;
    busca.value = 'Empresa';
    busca.dispatchEvent(new Event('input'));
    await new Promise((r) => setTimeout(r, 350));
    await espera();
    m.raiz.querySelector<HTMLElement>('[role="listbox"] [role="option"]')!.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    await espera();

    const soSem = m.raiz.querySelector<HTMLInputElement>('input[type=radio][value="sem_cliente"]')!;
    soSem.click();
    await espera();
    botao(m.raiz, /Aplicar ao lote/).click();
    await espera();

    expect(m.toasts.at(-1)?.type).toBe('warning'); // o processo já tem o cliente 10
  });
  it('mostra a tela de confirmação e só grava depois de confirmar', async () => {
    m = montar();
    await enviarArquivo(m.raiz);
    botao(m.raiz, /Aceitar todas as sugestões/).click();
    await espera();

    botao(m.raiz, /Importar selecionados/).click();
    await espera();

    const dialogo = m.raiz.querySelector('[role="dialog"]')!;
    expect(dialogo.textContent).toContain('Confirmar importação');
    expect(dialogo.textContent).toContain(MASCARA);
    expect(dialogo.textContent).toContain('Atualizar');
    expect(dialogo.textContent).toContain('Maria da Silva');
    expect(dialogo.textContent).toContain('1 processo(s) ficarão sem contrato');
    expect(chamadas.lista.some((c) => c.endpoint === 'processos/importacao/confirmar')).toBe(false);

    botao(m.raiz, /Voltar e revisar/).click();
    await espera();
    expect(m.raiz.querySelector('[role="dialog"]')).toBeNull();
    expect(chamadas.lista.some((c) => c.endpoint === 'processos/importacao/confirmar')).toBe(false);

    await importarEConfirmar(m.raiz);
    expect(chamadas.lista.some((c) => c.endpoint === 'processos/importacao/confirmar')).toBe(true);
    expect(m.raiz.querySelector('[role="dialog"]')).toBeNull();
  });

  it('aplica o mesmo contrato e as mesmas etiquetas a todo o lote', async () => {
    chamadas.respostas['processos/contratos-busca'] = { results: [{ id: 7, text: 'Contrato #2025/01 - Maria da Silva', nome_cliente: 'Maria da Silva' }] };
    m = montar();
    await enviarArquivo(m.raiz);

    const busca = m.raiz.querySelector<HTMLInputElement>('input[aria-label="Buscar contrato para todo o lote"]')!;
    busca.value = '2025';
    busca.dispatchEvent(new Event('input'));
    await new Promise((r) => setTimeout(r, 350));
    await espera();
    m.raiz.querySelector<HTMLElement>('[role="listbox"] [role="option"]')!.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    await espera();

    const etiqueta = m.raiz.querySelector<HTMLInputElement>('#imp-etiqueta-lote')!;
    for (const nome of ['Bancário', 'urgente']) { // "urgente" já está no processo
      etiqueta.value = nome;
      etiqueta.dispatchEvent(new Event('input'));
      etiqueta.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
      await espera();
    }

    botao(m.raiz, /Aplicar ao lote/).click();
    await espera();
    expect(m.toasts.at(-1)?.msg).toContain('aplicado a 1 processo(s)');
    expect(m.toasts.at(-1)?.msg).toContain('Contrato #2025/01');

    botao(m.raiz, /Aceitar todas/).click();
    await espera();
    await importarEConfirmar(m.raiz);

    const envio = chamadas.lista.find((c) => c.endpoint === 'processos/importacao/confirmar');
    expect(envio?.body.processos[0].campos.contrato_id).toBe(7);
    expect(envio?.body.processos[0].etiquetas).toEqual(['Nova', 'Bancário']);
  });

  it('pesquisa no DJEN pelo nome da parte e leva os escolhidos para a conferência', async () => {
    chamadas.respostas['processos/importacao/pesquisar-parte'] = {
      success: true,
      data: {
        status: 'encontrado', mensagem: null, truncado: false, total_comunicacoes: 3,
        filtros: { nome: 'Maria da Silva', data_inicio: '2025-11-07', data_fim: '2026-11-07', tribunal: null },
        processos: [
          { numero_processo: NUMERO, numeroprocessocommascara: MASCARA, siglaTribunal: 'TJSP', nomeOrgao: '1ª VARA', classe: 'PROCEDIMENTO COMUM',
            poloAtivo: ['Maria da Silva'], poloPassivo: ['Banco Exemplo S.A.'], partes_encontradas: [{ nome: 'Maria da Silva', polo: 'A' }],
            correspondencia: 'exata', total_publicacoes: 2, ultima_publicacao: '2026-10-01', situacao: 'cadastrado' },
          { numero_processo: '10045673820245020001', numeroprocessocommascara: '1004567-38.2024.5.02.0001', siglaTribunal: 'TRT2', nomeOrgao: null, classe: null,
            poloAtivo: ['Ana Maria da Silva Souza'], poloPassivo: [], partes_encontradas: [{ nome: 'Ana Maria da Silva Souza', polo: 'A' }],
            correspondencia: 'parcial', total_publicacoes: 1, ultima_publicacao: '2026-09-01', situacao: 'novo' },
        ],
      },
    };
    m = montar();

    botao(m.raiz, /Pesquisar no DJEN pelo nome da parte/).click();
    await espera();
    const nome = m.raiz.querySelector<HTMLInputElement>('#imp-parte')!;
    nome.value = 'Maria da Silva';
    nome.dispatchEvent(new Event('input'));
    const tribunal = m.raiz.querySelector<HTMLInputElement>('#imp-tribunal')!;
    tribunal.value = 'tjsp';
    tribunal.dispatchEvent(new Event('input'));
    await espera();
    botao(m.raiz, /Pesquisar no DJEN$/).click();
    await espera();

    const pesquisa = chamadas.lista.find((c) => c.endpoint === 'processos/importacao/pesquisar-parte');
    expect(pesquisa?.body).toMatchObject({ nome: 'Maria da Silva', tribunal: 'tjsp' });
    expect(m.raiz.textContent).toContain('2 processo(s) encontrado(s)');
    expect(m.raiz.textContent).toContain('Nome parecido');
    expect(m.raiz.textContent).toContain('Passivo: Banco Exemplo S.A.');

    // "Só nome idêntico" esconde o parcial.
    m.raiz.querySelector<HTMLInputElement>('.imp-djen-filtros input')!.click();
    await espera();
    expect(m.raiz.textContent).not.toContain('1004567-38.2024.5.02.0001');

    const conferir = botao(m.raiz, /Conferir selecionados/);
    expect(conferir.disabled).toBe(true);
    botao(m.raiz, /Selecionar os exibidos/).click();
    await espera();
    expect(conferir.disabled).toBe(false);
    conferir.click();
    await espera();

    const consultas = chamadas.lista.filter((c) => c.endpoint === 'processos/importacao/consultar');
    expect(consultas.map((c) => c.body)).toEqual([{ numero_processo: NUMERO, arquivo: {} }]);
    expect(m.raiz.textContent).toContain('Aplicar a todos os processos');
    // Sem arquivo, a coluna "Arquivo" não aparece na conferência.
    expect(Array.from(m.raiz.querySelectorAll('.imp-tabela th')).map((t) => t.textContent?.trim())).not.toContain('Arquivo');
  });

  it('avisa e não pesquisa com nome curto demais', async () => {
    m = montar();
    botao(m.raiz, /Pesquisar no DJEN pelo nome da parte/).click();
    await espera();
    const nome = m.raiz.querySelector<HTMLInputElement>('#imp-parte')!;
    nome.value = 'Ana';
    nome.dispatchEvent(new Event('input'));
    await espera();

    expect(botao(m.raiz, /Pesquisar no DJEN$/).disabled).toBe(true);
    expect(chamadas.lista.some((c) => c.endpoint === 'processos/importacao/pesquisar-parte')).toBe(false);
  });
});
