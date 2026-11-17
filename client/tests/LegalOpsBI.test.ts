// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const { mockChartInstances, MockChart } = vi.hoisted(() => {
  const instances: any[] = [];
  class MockChart {
    canvas: any;
    config: any;
    destroy = vi.fn();
    constructor(canvas: any, config: any) {
      this.canvas = canvas;
      this.config = config;
      instances.push(this);
    }
  }
  return { mockChartInstances: instances, MockChart };
});

vi.mock('chart.js/auto', () => ({
  default: MockChart,
}));

vi.mock('vue', async () => {
  const actual = await vi.importActual<any>('vue');
  return {
    ...actual,
    Transition: (props: any, { slots }: any) => {
      if (props?.['onAfterEnter']) {
        nextTick(() => props['onAfterEnter']());
      }
      return slots.default ? slots.default() : null;
    },
  };
});

const api = vi.hoisted(() => ({
  apiFetch: vi.fn(),
  formatCurrency: vi.fn((v: number) => `R$ ${Number(v).toFixed(2)}`),
  riscoColor: vi.fn(() => '#d97706'),
}));

vi.mock('../src/services/api', () => ({
  apiFetch: api.apiFetch,
  formatCurrency: api.formatCurrency,
  riscoColor: api.riscoColor,
}));

import LegalOpsBI from '../src/views/LegalOps/LegalOpsBI.vue';

const espera = async () => {
  for (let i = 0; i < 10; i++) {
    await nextTick();
    await Promise.resolve();
  }
};

function montar() {
  const raiz = document.createElement('div');
  document.body.appendChild(raiz);
  const toasts: any[] = [];
  const app = createApp(LegalOpsBI, {
    onToast: (t: any) => toasts.push(t),
    onSetView: vi.fn(),
  });
  app.mount(raiz);
  return { raiz, toasts, desmontar: () => { app.unmount(); raiz.remove(); } };
}

describe('LegalOpsBI.vue', () => {
  let montado: ReturnType<typeof montar> | null = null;

  beforeEach(() => {
    mockChartInstances.length = 0;
    api.apiFetch.mockReset();
  });

  afterEach(() => {
    montado?.desmontar();
    montado = null;
  });

  it('renderiza KPIs e instancia gráficos quando há dados disponíveis', async () => {
    api.apiFetch.mockResolvedValueOnce({
      kpis: {
        provisao_total: 100000,
        exposicao_total: 200000,
        tempo_medio: 45,
        ticket_medio: 15000,
      },
      charts: {
        risco_distribuicao: [
          { labels: 'Possível', values: '50' },
          { labels: 'Provável', values: '20' },
        ],
        exposicao_orgao: [
          { label: '1ª Vara Cível', value: '150000' },
        ],
        performance_orgao: [
          { label: '1ª Vara Cível', sucessos: 8, derrotas: 2 },
        ],
        performance_resultado: [
          { label: 'Sucesso', values: 10 },
        ],
      },
    });

    montado = montar();
    await espera();

    const texto = montado.raiz.textContent ?? '';
    expect(texto).toContain('Provisão Total (CPC-25)');
    expect(texto).toContain('Cycle Time Médio');
    expect(texto).toContain('45 dias');

    // Deve ter 4 gráficos ativos (não destruídos)
    const activeCharts = mockChartInstances.filter(inst => inst.destroy.mock.calls.length === 0);
    expect(activeCharts.length).toBe(4);

    // Verifica se os campos plurais sucessos/derrotas foram mapeados corretamente no gráfico de performance_orgao
    const performanceChart = activeCharts.find(inst =>
      inst.config?.data?.datasets?.some((d: any) => d.label === 'Sucesso')
    );
    expect(performanceChart).toBeDefined();
    const datasets = performanceChart.config.data.datasets;
    expect(datasets[0].data).toEqual([8]);
    expect(datasets[1].data).toEqual([2]);
  });

  it('exibe placeholders explicativos quando não há dados de desfecho/processos finalizados', async () => {
    api.apiFetch.mockResolvedValueOnce({
      kpis: {
        provisao_total: 50000,
        exposicao_total: 50000,
        tempo_medio: 0,
        ticket_medio: 0,
      },
      charts: {
        risco_distribuicao: [{ labels: 'Possível', values: '10' }],
        exposicao_orgao: [{ label: 'Vara Única', value: '50000' }],
        performance_orgao: [],
        performance_resultado: [],
      },
    });

    montado = montar();
    await espera();

    const placeholders = montado.raiz.querySelectorAll('.empty-chart-placeholder');
    expect(placeholders.length).toBeGreaterThanOrEqual(2);

    const texto = montado.raiz.textContent ?? '';
    expect(texto).toContain('Nenhum desfecho por órgão registrado ainda');
    expect(texto).toContain('Nenhum processo finalizado registrado');

    // Somente os 2 gráficos com dados devem estar ativos
    const activeCharts = mockChartInstances.filter(inst => inst.destroy.mock.calls.length === 0);
    expect(activeCharts.length).toBe(2);
  });
});
