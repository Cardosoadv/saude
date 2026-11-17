import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

vi.mock('../src/services/api', () => ({ apiFetch: vi.fn() }));
vi.mock('../src/services/auth', () => ({ getCurrentUser: vi.fn() }));

import { aplicarAcesso, limparAcesso } from '../src/composables/useAcesso';
import ObjetosVinculadosCard from '../src/components/widgets/Processos/ObjetosVinculadosCard.vue';

const objetos = [{ id_objeto: 7, cidade: 'Recife', cod_interno: 'A1' }];

async function renderCard(): Promise<string> {
  const app = createSSRApp({ render: () => h(ObjetosVinculadosCard, { processId: 1, objetos }) });
  return renderToString(app);
}

describe('ObjetosVinculadosCard', () => {
  beforeEach(() => {
    limparAcesso();
  });

  it('com objetos.edit mostra vincular e remover', async () => {
    aplicarAcesso({ permissoes: ['objetos.access', 'objetos.edit'], modulos_ativos: ['objetos'] });

    const html = await renderCard();

    expect(html).toContain('Vincular');
    expect(html).toContain('Remover Vínculo');
  });

  it('só com objetos.access lista mas não altera vínculos', async () => {
    aplicarAcesso({ permissoes: ['objetos.access'], modulos_ativos: ['objetos'] });

    const html = await renderCard();

    expect(html).toContain('Recife');
    expect(html).not.toContain('Vincular');
    expect(html).not.toContain('Remover Vínculo');
  });
});

describe('ProcessDetail', () => {
  // Renderizar a view exigiria simular o detalhe do processo inteiro; aqui
  // garante-se que o card e o modal de objetos continuam protegidos.
  const fonte = readFileSync(resolve(__dirname, '../src/views/Processos/ProcessDetail.vue'), 'utf8');

  it('só exibe o card de objetos com módulo e permissão', () => {
    expect(fonte).toMatch(/<ObjetosVinculadosCard v-if="liberado\('objetos', 'objetos\.access'\)"/);
  });

  it('não abre modal de vínculo sem objetos.edit', () => {
    expect(fonte).toContain(`v-if="activeModal && modalLiberado(activeModal)"`);
    expect(fonte).toContain(`objetoVinculo: ['objetos', 'objetos.edit']`);
  });
});
