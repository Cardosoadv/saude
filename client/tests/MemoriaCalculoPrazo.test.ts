// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const api = vi.hoisted(() => ({ memoriaDaTarefa: vi.fn(), memoriaDaIntimacao: vi.fn() }));
vi.mock('../src/services/calendarioForenseApi', () => api);

import MemoriaCalculoPrazo from '../src/components/widgets/MemoriaCalculoPrazo.vue';
import { prazoAConferir, intimacaoComPrazoAConferir } from '../src/utils/prazos';

const espera = async () => { for (let i = 0; i < 6; i++) { await nextTick(); await Promise.resolve(); } };

const memoriaUteis = {
  metodo: 'dias_uteis', vencimento: '2026-09-08', avisos: [], dias_uteis: 15, tipo_ato: 'sentença',
  entrada: { data_disponibilizacao: '2026-08-14', dias: 15, tribunal: 'TJSP' },
  data_publicacao: '2026-08-17', inicio_contagem: '2026-08-18',
  descontados: [
    { data: '2026-08-15', motivo: 'fim_de_semana', descricao: 'Sábado' },
    { data: '2026-09-07', motivo: 'feriado', descricao: 'Independência do Brasil' },
  ],
};

function montar(props: Record<string, any>) {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const app = createApp(MemoriaCalculoPrazo, props);
  app.mount(raiz);
  return { raiz, desmontar: () => { app.unmount(); raiz.remove(); } };
}

describe('MemoriaCalculoPrazo', () => {
  let montado: ReturnType<typeof montar> | null = null;
  beforeEach(() => { Object.values(api).forEach((f) => f.mockReset()); });
  afterEach(() => { montado?.desmontar(); montado = null; });

  it('mostra os passos da contagem e os dias descontados com o motivo', async () => {
    api.memoriaDaTarefa.mockResolvedValue({ disponivel: true, memoria: memoriaUteis });
    montado = montar({ tipo: 'tarefa', id: 7 });
    await espera();

    expect(api.memoriaDaTarefa).toHaveBeenCalledWith(7);
    const texto = montado.raiz.textContent ?? '';
    expect(texto).toContain('15 dias úteis');
    expect(texto).toContain('sentença');
    expect(texto).toContain('Feriado');
    expect(texto).toContain('Independência do Brasil');
    expect(texto).toContain('Fim de semana');
    expect(montado.raiz.querySelector('.memoria-vencimento strong')?.textContent).toMatch(/08\/09\/2026/);
  });

  it('consulta a intimação e explica quando o número de dias não foi identificado', async () => {
    api.memoriaDaIntimacao.mockResolvedValue({
      disponivel: false, a_conferir: true, data_estimada_ia: '2026-12-31',
      motivo: 'O número de dias do prazo não foi identificado nesta intimação.',
    });
    montado = montar({ tipo: 'intimacao', id: 3 });
    await espera();

    expect(api.memoriaDaIntimacao).toHaveBeenCalledWith(3);
    const texto = montado.raiz.textContent ?? '';
    expect(texto).toContain('prazo a conferir');
    expect(texto).toContain('não foi identificado');
    expect(texto).toContain('31/12/2026');
  });

  it('mostra o erro quando a API falha', async () => {
    api.memoriaDaTarefa.mockRejectedValue(new Error('Sem permissão'));
    montado = montar({ tipo: 'tarefa', id: 1 });
    await espera();
    expect(montado.raiz.textContent).toContain('Sem permissão');
  });
});

describe('selo "prazo a conferir"', () => {
  it('tarefa: usa o campo prazo_a_conferir do servidor', () => {
    expect(prazoAConferir({ prazo_a_conferir: 1 })).toBe(true);
    expect(prazoAConferir({ prazo_a_conferir: '1' })).toBe(true);
    expect(prazoAConferir({ prazo_a_conferir: 0 })).toBe(false);
    expect(prazoAConferir(null)).toBe(false);
  });

  it('intimação: só quando a IA estimou data sem informar o número de dias', () => {
    expect(intimacaoComPrazoAConferir({ ai_data_critica: '2026-12-31', ai_categoria: 'prazo', ai_detalhes_extracao: null })).toBe(true);
    expect(intimacaoComPrazoAConferir({ ai_data_critica: '2026-12-31', ai_categoria: 'prazo', ai_detalhes_extracao: '{"prazo_dias":15}' })).toBe(false);
    expect(intimacaoComPrazoAConferir({ ai_data_critica: '2026-10-20', ai_categoria: 'audiencia' })).toBe(false);
    expect(intimacaoComPrazoAConferir({ ai_data_critica: null })).toBe(false);
  });
});
