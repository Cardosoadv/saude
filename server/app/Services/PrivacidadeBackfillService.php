<?php

namespace App\Services;

use App\Models\AuditoriaModel;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/**
 * Fase 0 da privacidade granular: prepara os dados existentes para que, ao
 * ligar `privacidade_granular_ativa`, ninguém perca acesso ao que usa hoje.
 *
 * Idempotente: só preenche responsáveis vazios e não duplica acessos.
 *
 *  1. contratos.responsavel_id  ← `responsavel_interno` (username ou e-mail do usuário)
 *  2. clientes.responsavel_id   ← advogado que mais aparece como responsável nos
 *     processos ativos do cliente (empate: processo mais recente); sem processos
 *     ativos, o responsável do contrato mais recente; por fim, qualquer processo.
 *  3. acessos_entidades         ← demais responsáveis de processos/contratos do
 *     cliente recebem acesso ao cliente.
 *  4. Opcional: o responsável padrão assume tudo o que ficou sem responsável.
 *  5. Relatório dos registros sem responsável ou atribuídos ao padrão (revisão manual).
 */
class PrivacidadeBackfillService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * @param int|null $responsavelPadrao Advogado atribuído como responsável de tudo o que
     *                                    continuar sem responsável após as heurísticas.
     *
     * @return array{
     *   contratos_preenchidos: int,
     *   clientes_preenchidos: int,
     *   acessos_concedidos: int,
     *   atribuidos_padrao: int,
     *   pendencias: list<array{tipo: string, id: int, descricao: string, motivo: string}>
     * }
     */
    public function executar(bool $simular = false, ?int $responsavelPadrao = null): array
    {
        if ($responsavelPadrao !== null && !(new AcessoEntidadeService($this->db))->ehAdvogadoAtivo($responsavelPadrao)) {
            throw new InvalidArgumentException("O responsável padrão (#{$responsavelPadrao}) precisa ser um advogado ativo.");
        }

        $resultado = [
            'contratos_preenchidos' => 0,
            'clientes_preenchidos'  => 0,
            'acessos_concedidos'    => 0,
            'atribuidos_padrao'     => 0,
            'pendencias'            => [],
        ];

        if (!$simular) {
            $this->db->transStart();
        }

        $contratos = $this->preencherResponsaveisContratos($simular, $resultado['pendencias']);
        $clientes  = $this->preencherResponsaveisClientes($simular, $contratos);

        $resultado['contratos_preenchidos'] = count($contratos);
        $resultado['clientes_preenchidos']  = count($clientes);
        $resultado['acessos_concedidos']    = $this->concederAcessosAosClientes($simular, $clientes, $contratos);
        $resultado['pendencias']            = array_merge($resultado['pendencias'], $this->pendencias($clientes));

        if ($responsavelPadrao !== null) {
            $resultado['atribuidos_padrao'] = $this->atribuirResponsavelPadrao($simular, $responsavelPadrao, $resultado['pendencias']);
        }

        if (!$simular) {
            $this->db->transComplete();
            $this->auditar($resultado + ['responsavel_padrao' => $responsavelPadrao]);
        }

        return $resultado;
    }

    /**
     * Tudo o que ficou sem responsável recebe o advogado padrão. As pendências
     * continuam no relatório (motivo atualizado) para redistribuição posterior.
     *
     * @param list<array> $pendencias
     */
    protected function atribuirResponsavelPadrao(bool $simular, int $userId, array &$pendencias): int
    {
        $idsPorTipo = [];
        foreach ($pendencias as &$pendencia) {
            $idsPorTipo[$pendencia['tipo']][] = $pendencia['id'];
            $pendencia['motivo'] = "atribuído ao responsável padrão #{$userId} — revisar ({$pendencia['motivo']})";
        }
        unset($pendencia);

        if (!$simular) {
            foreach ($idsPorTipo as $tipo => $ids) {
                $meta = AcessoEntidadeService::TIPOS[$tipo];
                foreach (array_chunk($ids, 500) as $lote) {
                    $this->db->table($meta['tabela'])
                        ->whereIn($meta['pk'], $lote)
                        ->where('responsavel_id', null)
                        ->update(['responsavel_id' => $userId]);
                }
            }
        }

        return count($pendencias);
    }

    /**
     * @param list<array> $pendencias Recebe os contratos cujo texto não bateu com nenhum usuário.
     *
     * @return array<int, int> id_contrato => responsavel_id preenchido nesta execução
     */
    protected function preencherResponsaveisContratos(bool $simular, array &$pendencias): array
    {
        $usuarios = $this->mapaUsuarios();
        $preenchidos = [];

        $contratos = $this->db->table('contratos')
            ->select('id_contrato, numero_contrato, responsavel_interno')
            ->where('responsavel_id', null)
            ->where('deleted_at', null)
            ->where('responsavel_interno IS NOT NULL', null, false)
            ->where("TRIM(responsavel_interno) != ''", null, false)
            ->get()
            ->getResultArray();

        foreach ($contratos as $contrato) {
            $chave  = mb_strtolower(trim($contrato['responsavel_interno']));
            $userId = $usuarios[$chave] ?? null;

            if ($userId === null) {
                $pendencias[] = [
                    'tipo'      => 'contrato',
                    'id'        => (int) $contrato['id_contrato'],
                    'descricao' => (string) $contrato['numero_contrato'],
                    'motivo'    => "responsavel_interno \"{$contrato['responsavel_interno']}\" não corresponde a nenhum usuário",
                ];
                continue;
            }

            $preenchidos[(int) $contrato['id_contrato']] = $userId;

            if (!$simular) {
                $this->db->table('contratos')
                    ->where('id_contrato', $contrato['id_contrato'])
                    ->update(['responsavel_id' => $userId]);
            }
        }

        return $preenchidos;
    }

    /**
     * @param array<int, int> $contratosPreenchidos Responsáveis de contrato definidos nesta execução
     *                                              (necessário no modo simulação, em que nada foi gravado).
     *
     * @return array<int, int> id_cliente => responsavel_id preenchido nesta execução
     */
    protected function preencherResponsaveisClientes(bool $simular, array $contratosPreenchidos): array
    {
        $semResponsavel = array_map('intval', array_column(
            $this->db->table('clientes')
                ->select('id_cliente')
                ->where('responsavel_id', null)
                ->where('deleted_at', null)
                ->get()
                ->getResultArray(),
            'id_cliente'
        ));

        if ($semResponsavel === []) {
            return [];
        }

        $porProcessosAtivos = $this->maisFrequentePorCliente(true);
        $porContratos       = $this->responsavelContratoMaisRecente($contratosPreenchidos);
        $porProcessos       = $this->maisFrequentePorCliente(false);

        $preenchidos = [];
        foreach ($semResponsavel as $clienteId) {
            $userId = $porProcessosAtivos[$clienteId] ?? $porContratos[$clienteId] ?? $porProcessos[$clienteId] ?? null;
            if ($userId === null) {
                continue;
            }

            $preenchidos[$clienteId] = $userId;

            if (!$simular) {
                $this->db->table('clientes')->where('id_cliente', $clienteId)->update(['responsavel_id' => $userId]);
            }
        }

        return $preenchidos;
    }

    /**
     * Responsáveis de processos e contratos do cliente (exceto o próprio
     * responsável do cliente) recebem acesso explícito ao cliente.
     */
    protected function concederAcessosAosClientes(bool $simular, array $clientesPreenchidos, array $contratosPreenchidos): int
    {
        $responsavelCliente = [];
        foreach ($this->db->table('clientes')->select('id_cliente, responsavel_id')->where('deleted_at', null)->get()->getResultArray() as $c) {
            $responsavelCliente[(int) $c['id_cliente']] = $c['responsavel_id'] === null ? null : (int) $c['responsavel_id'];
        }
        foreach ($clientesPreenchidos as $clienteId => $userId) {
            $responsavelCliente[$clienteId] = $userId;
        }

        $pares = [];
        $processos = $this->db->table('processos')
            ->select('cliente_id, responsavel_id')
            ->where('cliente_id IS NOT NULL', null, false)
            ->where('responsavel_id IS NOT NULL', null, false)
            ->where('deleted_at', null)
            ->groupBy(['cliente_id', 'responsavel_id'])
            ->get()
            ->getResultArray();
        foreach ($processos as $p) {
            $pares[(int) $p['cliente_id'] . ':' . (int) $p['responsavel_id']] = [(int) $p['cliente_id'], (int) $p['responsavel_id']];
        }
        foreach ($this->contratosComResponsavel($contratosPreenchidos) as $c) {
            $pares[$c['cliente_id'] . ':' . $c['responsavel_id']] = [$c['cliente_id'], $c['responsavel_id']];
        }

        $existentes = [];
        foreach ($this->db->table('acessos_entidades')->select('entidade_id, user_id')->where('entidade_tipo', 'cliente')->get()->getResultArray() as $a) {
            $existentes[(int) $a['entidade_id'] . ':' . (int) $a['user_id']] = true;
        }

        $novos = [];
        foreach ($pares as $chave => [$clienteId, $userId]) {
            if (!array_key_exists($clienteId, $responsavelCliente)) {
                continue; // cliente excluído ou inexistente
            }
            if ($responsavelCliente[$clienteId] === $userId || isset($existentes[$chave])) {
                continue;
            }
            $novos[] = [
                'entidade_tipo' => 'cliente',
                'entidade_id'   => $clienteId,
                'user_id'       => $userId,
                'concedido_por' => null,
                'created_at'    => date('Y-m-d H:i:s'),
            ];
        }

        if (!$simular && $novos !== []) {
            foreach (array_chunk($novos, 500) as $lote) {
                $this->db->table('acessos_entidades')->insertBatch($lote);
            }
        }

        return count($novos);
    }

    /**
     * Registros que ficarão visíveis só para admins quando a privacidade for ativada.
     *
     * @param array<int, int> $clientesPreenchidos
     */
    protected function pendencias(array $clientesPreenchidos): array
    {
        $pendencias = [];

        $processos = $this->db->table('processos')
            ->select('id_processo, numero_processo, titulo_processo')
            ->where('responsavel_id', null)
            ->where('deleted_at', null)
            ->where('visibilidade', 'restrito')
            ->get()
            ->getResultArray();
        foreach ($processos as $p) {
            $pendencias[] = [
                'tipo'      => 'processo',
                'id'        => (int) $p['id_processo'],
                'descricao' => trim(($p['numero_processo'] ?? '') . ' ' . ($p['titulo_processo'] ?? '')),
                'motivo'    => 'sem responsável',
            ];
        }

        $clientes = $this->db->table('clientes')
            ->select('id_cliente, nome, status_crm')
            ->where('responsavel_id', null)
            ->where('deleted_at', null)
            ->where('visibilidade', 'restrito')
            ->get()
            ->getResultArray();
        foreach ($clientes as $c) {
            if (isset($clientesPreenchidos[(int) $c['id_cliente']])) {
                continue; // simulação: seria preenchido
            }
            $pendencias[] = [
                'tipo'      => 'cliente',
                'id'        => (int) $c['id_cliente'],
                'descricao' => (string) $c['nome'],
                'motivo'    => 'sem responsável (status CRM: ' . ($c['status_crm'] ?? '-') . ')',
            ];
        }

        $contratos = $this->db->table('contratos')
            ->select('id_contrato, numero_contrato')
            ->where('responsavel_id', null)
            ->where('deleted_at', null)
            ->where('visibilidade', 'restrito')
            ->groupStart()
                ->where('responsavel_interno', null)
                ->orWhere("TRIM(responsavel_interno) = ''", null, false)
            ->groupEnd()
            ->get()
            ->getResultArray();
        foreach ($contratos as $c) {
            $pendencias[] = [
                'tipo'      => 'contrato',
                'id'        => (int) $c['id_contrato'],
                'descricao' => (string) $c['numero_contrato'],
                'motivo'    => 'sem responsável',
            ];
        }

        return $pendencias;
    }

    /**
     * Mapa (username|e-mail em minúsculas) => users.id, apenas usuários ativos.
     *
     * @return array<string, int>
     */
    protected function mapaUsuarios(): array
    {
        $mapa = [];

        $usuarios = $this->db->table('users u')
            ->select('u.id, u.username, ai.secret AS email')
            ->join('auth_identities ai', "ai.user_id = u.id AND ai.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($usuarios as $u) {
            foreach ([$u['username'], $u['email']] as $chave) {
                if ($chave !== null && trim($chave) !== '') {
                    $mapa[mb_strtolower(trim($chave))] ??= (int) $u['id'];
                }
            }
        }

        return $mapa;
    }

    /**
     * Advogado que mais aparece como responsável nos processos de cada cliente.
     * Empate: responsável do processo mais recente.
     *
     * @return array<int, int> cliente_id => responsavel_id
     */
    protected function maisFrequentePorCliente(bool $somenteAtivos): array
    {
        $builder = $this->db->table('processos')
            ->select('cliente_id, responsavel_id, COUNT(*) AS total, MAX(id_processo) AS ultimo')
            ->where('cliente_id IS NOT NULL', null, false)
            ->where('responsavel_id IS NOT NULL', null, false)
            ->where('deleted_at', null);

        if ($somenteAtivos) {
            $builder->where('encerrado', 0);
        }

        $melhor = [];
        foreach ($builder->groupBy(['cliente_id', 'responsavel_id'])->get()->getResultArray() as $linha) {
            $clienteId = (int) $linha['cliente_id'];
            $atual     = $melhor[$clienteId] ?? null;
            $candidato = ['user' => (int) $linha['responsavel_id'], 'total' => (int) $linha['total'], 'ultimo' => (int) $linha['ultimo']];

            if ($atual === null
                || $candidato['total'] > $atual['total']
                || ($candidato['total'] === $atual['total'] && $candidato['ultimo'] > $atual['ultimo'])) {
                $melhor[$clienteId] = $candidato;
            }
        }

        return array_map(static fn (array $m) => $m['user'], $melhor);
    }

    /**
     * @return array<int, int> cliente_id => responsavel_id do contrato mais recente
     */
    protected function responsavelContratoMaisRecente(array $contratosPreenchidos): array
    {
        $resultado = [];
        $ultimo    = [];

        foreach ($this->contratosComResponsavel($contratosPreenchidos) as $c) {
            if (!isset($ultimo[$c['cliente_id']]) || $c['id_contrato'] > $ultimo[$c['cliente_id']]) {
                $ultimo[$c['cliente_id']]    = $c['id_contrato'];
                $resultado[$c['cliente_id']] = $c['responsavel_id'];
            }
        }

        return $resultado;
    }

    /**
     * Contratos com responsável, considerando os preenchidos nesta execução.
     *
     * @return list<array{id_contrato: int, cliente_id: int, responsavel_id: int}>
     */
    protected function contratosComResponsavel(array $contratosPreenchidos): array
    {
        $linhas = $this->db->table('contratos')
            ->select('id_contrato, cliente_id, responsavel_id')
            ->where('cliente_id IS NOT NULL', null, false)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $resultado = [];
        foreach ($linhas as $l) {
            $id     = (int) $l['id_contrato'];
            $userId = $contratosPreenchidos[$id] ?? ($l['responsavel_id'] === null ? null : (int) $l['responsavel_id']);
            if ($userId !== null) {
                $resultado[] = ['id_contrato' => $id, 'cliente_id' => (int) $l['cliente_id'], 'responsavel_id' => $userId];
            }
        }

        return $resultado;
    }

    protected function auditar(array $resultado): void
    {
        (new AuditoriaModel())->insert([
            'user_id'     => null,
            'table_name'  => 'acessos_entidades',
            'action_type' => 'UPDATE',
            'dados_novos' => json_encode([
                'operacao'              => 'privacidade:backfill',
                'contratos_preenchidos' => $resultado['contratos_preenchidos'],
                'clientes_preenchidos'  => $resultado['clientes_preenchidos'],
                'acessos_concedidos'    => $resultado['acessos_concedidos'],
                'atribuidos_padrao'     => $resultado['atribuidos_padrao'],
                'responsavel_padrao'    => $resultado['responsavel_padrao'],
            ]),
            'ip_address' => null,
        ]);
    }
}
