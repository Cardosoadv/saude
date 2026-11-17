import { describe, it, expect, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { alternarModulo, moduloEssencial, type Modulo } from '../src/utils/alternarModulo';

const objetos = (): Modulo => ({ id: 5, slug: 'objetos', nome: 'Objetos', ativo: true });

describe('alternarModulo', () => {
  it('desativa, envia o novo estado e usa a resposta da API', async () => {
    const m = objetos();
    const salvar = vi.fn().mockResolvedValue({
      success: true, message: 'Módulo desativado.', data: { ...m, ativo: false },
    });

    const r = await alternarModulo(m, salvar);

    expect(salvar).toHaveBeenCalledWith('objetos', false);
    expect(r).toEqual({ ok: true, mensagem: 'Módulo desativado.' });
    expect(m.ativo).toBe(false);
  });

  it('muda na tela antes da resposta', async () => {
    const m = objetos();
    let estadoDuranteChamada: boolean | undefined;
    await alternarModulo(m, async () => {
      estadoDuranteChamada = m.ativo;
      return { success: true };
    });

    expect(estadoDuranteChamada).toBe(false);
  });

  it('reverte quando a API falha', async () => {
    const m = objetos();

    const r = await alternarModulo(m, vi.fn().mockRejectedValue(new Error('HTTP 500')));

    expect(r).toEqual({ ok: false, mensagem: 'HTTP 500' });
    expect(m.ativo).toBe(true);
  });

  it('reverte e mostra a validação da API (módulo essencial)', async () => {
    const m: Modulo = { id: 1, slug: 'administrativo', nome: 'Administrativo', ativo: true };
    const erro = Object.assign(new Error('HTTP 400'), {
      data: { messages: { ativo: 'O módulo Administrativo não pode ser desativado.' } },
    });

    const r = await alternarModulo(m, vi.fn().mockRejectedValue(erro));

    expect(r.ok).toBe(false);
    expect(r.mensagem).toBe('O módulo Administrativo não pode ser desativado.');
    expect(m.ativo).toBe(true);
  });

  it('reverte quando a API responde success: false', async () => {
    const m = objetos();

    const r = await alternarModulo(m, vi.fn().mockResolvedValue({ success: false, message: 'Recusado.' }));

    expect(r).toEqual({ ok: false, mensagem: 'Recusado.' });
    expect(m.ativo).toBe(true);
  });

  it('marca administrativo como essencial', () => {
    expect(moduloEssencial({ id: 1, slug: 'administrativo', nome: 'Administrativo', ativo: true })).toBe(true);
    expect(moduloEssencial(objetos())).toBe(false);
  });
});

describe('ConfiguracoesView', () => {
  const fonte = readFileSync(resolve(__dirname, '../src/views/Configuracoes/ConfiguracoesView.vue'), 'utf8');

  it('aba e painel de módulos exigem module.edit', () => {
    expect(fonte).toContain(`const podeGerirModulos = computed(() => pode('module.edit'))`);
    expect(fonte).toContain(`v-if="podeGerirModulos"`);
    expect(fonte).toContain(`<ModulosPanel v-if="activeTab === 'modulos' && podeGerirModulos"`);
  });
});
