<?php

namespace App\Services;

use App\Models\AuditoriaModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;

/**
 * Privacidade granular de clientes e contratos.
 *
 * Um registro `restrito` só é visível para:
 *  - o responsável (`responsavel_id` da própria entidade);
 *  - os profissionais listados em `acessos_entidades` (acesso de leitura e edição);
 *  - usuários com a permissão `privacidade.bypass` (admin/superadmin).
 * Registros `publico` continuam visíveis para todos com acesso ao módulo.
 *
 * O escopo só é aplicado quando a configuração `privacidade_granular_ativa`
 * está ligada e há um usuário autenticado. Crons, filas e comandos (sem
 * usuário) continuam enxergando tudo.
 */
class AcessoEntidadeService
{
    public const CONFIG_ATIVA = 'privacidade_granular_ativa';

    public const CONFIG_RESPONSAVEL_PADRAO = 'privacidade_responsavel_padrao';

    public const VISIBILIDADES = ['publico', 'restrito'];

    /**
     * Mapa entidade → tabela, chave primária e prefixo de permissão do módulo.
     */
    public const TIPOS = [
        'cliente'  => ['tabela' => 'clientes', 'pk' => 'id_cliente', 'permissao' => 'clientes', 'repositorio' => \App\Repositories\ClienteRepository::class],
        'contrato' => ['tabela' => 'contratos', 'pk' => 'id_contrato', 'permissao' => 'contratos', 'repositorio' => \App\Repositories\ContratosRepository::class],
    ];

    protected BaseConnection $db;
    protected ConfiguracoesService $configuracoes;

    /** @var array<int, User|null> */
    private array $usuarios = [];

    public function __construct(?BaseConnection $db = null, ?ConfiguracoesService $configuracoes = null)
    {
        $this->db            = $db ?? db_connect();
        $this->configuracoes = $configuracoes ?? new ConfiguracoesService();
    }

    public function estaAtiva(): bool
    {
        return (string) $this->configuracoes->getValor(self::CONFIG_ATIVA, '0') === '1';
    }

    /**
     * Indica se o escopo não se aplica: funcionalidade desligada, contexto de
     * sistema (sem usuário), permissão `privacidade.bypass` ou, informado o
     * tipo, usuário "exclusive" do módulo (ex.: `clientes.exclusive` sem
     * `clientes.access`), que já é restrito aos registros do próprio cliente.
     */
    public function ignoraEscopo(?int $userId = null, ?string $tipo = null): bool
    {
        if (!$this->estaAtiva()) {
            return true;
        }

        $userId ??= user_id();
        if (!$userId) {
            return true;
        }

        if ($this->usuarioPode($userId, 'privacidade.bypass')) {
            return true;
        }

        if ($tipo !== null) {
            $modulo = $this->meta($tipo)['permissao'];

            return $this->usuarioPode($userId, "{$modulo}.exclusive") && !$this->usuarioPode($userId, "{$modulo}.access");
        }

        return false;
    }

    /**
     * Restringe uma consulta aos registros visíveis para o usuário.
     *
     * @param BaseBuilder|Model $builder Consulta sobre a tabela da entidade.
     * @param string|null       $alias   Alias/tabela usado na consulta (padrão: nome da tabela).
     *
     * @return BaseBuilder|Model O mesmo objeto recebido, para encadeamento.
     */
    public function aplicarEscopo($builder, string $tipo, ?string $alias = null, ?int $userId = null)
    {
        $meta   = $this->meta($tipo);
        $userId ??= user_id();

        if ($this->ignoraEscopo($userId, $tipo)) {
            return $builder;
        }

        $qb    = $builder instanceof Model ? $builder->builder() : $builder;
        $db    = $qb->db();
        $alias ??= $meta['tabela'];

        $acessos = $db->protectIdentifiers('acessos_entidades', true);
        $pk      = $db->protectIdentifiers("{$alias}.{$meta['pk']}");
        $existe  = "EXISTS (SELECT 1 FROM {$acessos} ae WHERE ae.entidade_tipo = " . $db->escape($tipo)
            . " AND ae.entidade_id = {$pk} AND ae.user_id = " . (int) $userId . ')';

        $qb->groupStart()
            ->where("{$alias}.visibilidade", 'publico')
            ->orWhere("{$alias}.responsavel_id", (int) $userId)
            ->orWhere($existe, null, false)
            ->groupEnd();

        return $builder;
    }

    /**
     * Mesma regra de aplicarEscopo() como trecho de SQL, para consultas
     * escritas à mão (`$db->query(...)`). Null quando o escopo não se aplica.
     *
     * @param string $alias Alias da tabela da entidade na consulta (ex.: 'p').
     */
    public function condicaoSql(string $tipo, string $alias, ?int $userId = null): ?string
    {
        $meta   = $this->meta($tipo);
        $userId ??= user_id();

        if ($this->ignoraEscopo($userId, $tipo)) {
            return null;
        }

        $a       = $this->db->escapeIdentifiers($alias);
        $uid     = (int) $userId;
        $acessos = $this->db->prefixTable('acessos_entidades');

        return "({$a}.visibilidade = 'publico' OR {$a}.responsavel_id = {$uid} OR EXISTS ("
            . "SELECT 1 FROM {$acessos} ae WHERE ae.entidade_tipo = " . $this->db->escape($tipo)
            . " AND ae.entidade_id = {$a}.{$meta['pk']} AND ae.user_id = {$uid}))";
    }

    /** @var array<int, array{ids: array<int, true>, numeros: array<string, true>}> */
    private array $ocultosPorUsuario = [];

    /**
     * Verifica se o usuário pode ver/editar o registro. Registro inexistente
     * retorna false (o chamador deve responder 404 em ambos os casos, para não
     * revelar a existência de registros restritos).
     */
    public function podeAcessar(string $tipo, int $id, ?int $userId = null): bool
    {
        $meta   = $this->meta($tipo);
        $userId ??= user_id();

        if ($this->ignoraEscopo($userId, $tipo)) {
            return true;
        }

        $builder = $this->db->table($meta['tabela'])->where("{$meta['tabela']}.{$meta['pk']}", $id);
        $this->aplicarEscopo($builder, $tipo, $meta['tabela'], $userId);

        return $builder->countAllResults() > 0;
    }

    /**
     * Quem pode alterar visibilidade e lista de acessos: responsável, bypass ou
     * `privacidade.manage`. Vale mesmo com a funcionalidade desligada, para que
     * os acessos possam ser preparados antes da ativação.
     */
    public function podeGerenciar(string $tipo, int $id, ?int $userId = null): bool
    {
        $meta   = $this->meta($tipo);
        $userId ??= user_id();

        if (!$userId) {
            return false;
        }

        if ($this->usuarioPode($userId, 'privacidade.bypass') || $this->usuarioPode($userId, 'privacidade.manage')) {
            return true;
        }

        $registro = $this->db->table($meta['tabela'])
            ->select('responsavel_id')
            ->where($meta['pk'], $id)
            ->get()
            ->getRowArray();

        return $registro !== null && (int) $registro['responsavel_id'] === $userId;
    }

    /**
     * Visibilidade, responsável e profissionais com acesso a um registro.
     *
     * @return array{visibilidade: string, responsavel: array|null, usuarios: array}|null
     */
    public function listarAcessos(string $tipo, int $id): ?array
    {
        $meta = $this->meta($tipo);

        $registro = $this->db->table($meta['tabela'] . ' e')
            ->select('e.visibilidade, e.responsavel_id, u.username AS responsavel_nome')
            ->join('users u', 'u.id = e.responsavel_id', 'left')
            ->where("e.{$meta['pk']}", $id)
            ->get()
            ->getRowArray();

        if ($registro === null) {
            return null;
        }

        $usuarios = $this->db->table('acessos_entidades ae')
            ->select('ae.user_id, u.username, ae.concedido_por, c.username AS concedido_por_nome, ae.created_at')
            ->join('users u', 'u.id = ae.user_id')
            ->join('users c', 'c.id = ae.concedido_por', 'left')
            ->where('ae.entidade_tipo', $tipo)
            ->where('ae.entidade_id', $id)
            ->orderBy('u.username', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'visibilidade' => $registro['visibilidade'],
            'responsavel'  => $registro['responsavel_id'] === null ? null : [
                'user_id'  => (int) $registro['responsavel_id'],
                'username' => $registro['responsavel_nome'],
            ],
            'usuarios' => $usuarios,
        ];
    }

    public function definirVisibilidade(string $tipo, int $id, string $visibilidade): bool
    {
        $meta = $this->meta($tipo);

        if (!in_array($visibilidade, self::VISIBILIDADES, true)) {
            throw new InvalidArgumentException('Visibilidade inválida.');
        }

        $this->db->table($meta['tabela'])->where($meta['pk'], $id)->update(['visibilidade' => $visibilidade]);
        $this->auditar('UPDATE', ['entidade_tipo' => $tipo, 'entidade_id' => $id, 'visibilidade' => $visibilidade]);
        $this->limparCache($tipo);

        return true;
    }

    /**
     * Concede acesso (leitura e edição). Idempotente: retorna false se o
     * usuário já tinha acesso.
     */
    public function conceder(string $tipo, int $id, int $userId, ?int $concedidoPor = null): bool
    {
        $this->meta($tipo);

        if ($this->temAcessoExplicito($tipo, $id, $userId)) {
            return false;
        }

        $dados = [
            'entidade_tipo' => $tipo,
            'entidade_id'   => $id,
            'user_id'       => $userId,
            'concedido_por' => $concedidoPor,
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        $this->db->table('acessos_entidades')->insert($dados);
        $this->auditar('CREATE', $dados);
        $this->limparCache($tipo);

        return true;
    }

    public function revogar(string $tipo, int $id, int $userId): bool
    {
        $this->meta($tipo);

        $this->db->table('acessos_entidades')
            ->where('entidade_tipo', $tipo)
            ->where('entidade_id', $id)
            ->where('user_id', $userId)
            ->delete();

        $removido = $this->db->affectedRows() > 0;

        if ($removido) {
            $this->auditar('DELETE', ['entidade_tipo' => $tipo, 'entidade_id' => $id, 'user_id' => $userId]);
            $this->limparCache($tipo);
        }

        return $removido;
    }

    /**
     * Quem cadastra um registro restrito precisa continuar enxergando-o.
     * Ignorado em contexto de sistema e para usuários do grupo `cliente`.
     */
    public function concederAoCriador(string $tipo, int $id): void
    {
        $userId = user_id();
        if (!$userId || !$this->ehProfissionalAtivo($userId)) {
            return;
        }

        $this->conceder($tipo, $id, $userId, $userId);
    }

    /**
     * Herança opcional: copia a lista de acessos (e, se pedido, a visibilidade)
     * do cliente para seus contratos. Cópia pontual — alterações
     * posteriores no cliente não se propagam sozinhas.
     *
     * @return array{contratos: int} Quantidade de registros afetados.
     */
    public function propagarDoCliente(int $clienteId, ?int $concedidoPor = null, bool $copiarVisibilidade = false): array
    {
        $cliente = $this->listarAcessos('cliente', $clienteId);
        if ($cliente === null) {
            throw new InvalidArgumentException('Cliente não encontrado.');
        }

        $usuarios = array_map('intval', array_column($cliente['usuarios'], 'user_id'));
        if ($cliente['responsavel'] !== null) {
            $usuarios[] = $cliente['responsavel']['user_id'];
        }
        $usuarios = array_values(array_unique($usuarios));

        $afetados = ['contratos' => 0];

        foreach (['contrato' => 'contratos'] as $tipo => $chave) {
            $meta = self::TIPOS[$tipo];
            $ids  = array_column(
                $this->db->table($meta['tabela'])
                    ->select($meta['pk'])
                    ->where('cliente_id', $clienteId)
                    ->where('deleted_at', null)
                    ->get()
                    ->getResultArray(),
                $meta['pk']
            );

            foreach ($ids as $id) {
                foreach ($usuarios as $userId) {
                    $this->conceder($tipo, (int) $id, $userId, $concedidoPor);
                }
                if ($copiarVisibilidade) {
                    $this->definirVisibilidade($tipo, (int) $id, $cliente['visibilidade']);
                }
            }

            $afetados[$chave] = count($ids);
        }

        return $afetados;
    }

    /**
     * Responsável atribuído a registros criados sem usuário logado (crons,
     * filas). Null se não configurado ou se o usuário não for profissional ativo.
     */
    public function responsavelPadraoDoSistema(): ?int
    {
        $valor = trim((string) $this->configuracoes->getValor(self::CONFIG_RESPONSAVEL_PADRAO, ''));

        if (!ctype_digit($valor) || !$this->ehProfissionalAtivo((int) $valor)) {
            return null;
        }

        return (int) $valor;
    }

    /**
     * Profissional = usuário ativo que não pertence ao grupo `cliente`.
     */
    public function ehProfissionalAtivo(int $userId): bool
    {
        return $this->queryProfissionais()->where('u.id', $userId)->countAllResults() > 0;
    }

    /**
     * Lista profissionais ativos para autocomplete (responsável e acessos).
     */
    public function buscarProfissionais(string $termo = '', int $limite = 20): array
    {
        $builder = $this->queryProfissionais()->select('u.id, u.username');

        if (trim($termo) !== '') {
            $builder->like('u.username', trim($termo));
        }

        return $builder->orderBy('u.username', 'ASC')->limit(max(1, min($limite, 100)))->get()->getResultArray();
    }

    protected function queryProfissionais(): BaseBuilder
    {
        $grupos = $this->db->protectIdentifiers('auth_groups_users', true);

        return $this->db->table('users u')
            ->where('u.active', 1)
            ->where('u.deleted_at', null)
            ->where("NOT EXISTS (SELECT 1 FROM {$grupos} g WHERE g.user_id = u.id AND g.`group` = 'cliente')", null, false);
    }

    protected function temAcessoExplicito(string $tipo, int $id, int $userId): bool
    {
        return $this->db->table('acessos_entidades')
            ->where('entidade_tipo', $tipo)
            ->where('entidade_id', $id)
            ->where('user_id', $userId)
            ->countAllResults() > 0;
    }

    protected function usuarioPode(int $userId, string $permissao): bool
    {
        if (!array_key_exists($userId, $this->usuarios)) {
            $atual = auth()->user();
            $this->usuarios[$userId] = ($atual !== null && (int) $atual->id === $userId)
                ? $atual
                : auth()->getProvider()->findById($userId);
        }

        $usuario = $this->usuarios[$userId];

        return $usuario !== null && $usuario->can($permissao);
    }

    /**
     * @return array{tabela: string, pk: string, permissao: string, repositorio: class-string}
     */
    protected function meta(string $tipo): array
    {
        if (!isset(self::TIPOS[$tipo])) {
            throw new InvalidArgumentException("Tipo de entidade inválido: {$tipo}");
        }

        return self::TIPOS[$tipo];
    }

    /**
     * As listagens ficam em cache (por usuário quando o escopo está ativo);
     * mudanças de visibilidade ou de acessos precisam invalidá-lo. Limpa os
     * dois porque as fichas se cruzam (ex.: a ficha do cliente lista os
     * contratos dele, em cache de clientes).
     */
    protected function limparCache(string $tipo): void
    {
        foreach (self::TIPOS as $meta) {
            (new $meta['repositorio']())->clearCache();
        }
    }

    protected function auditar(string $acao, array $dados): void
    {
        (new AuditoriaModel())->insert([
            'user_id'     => user_id(),
            'table_name'  => 'acessos_entidades',
            'action_type' => $acao,
            'dados_novos' => json_encode($dados),
            'ip_address'  => is_cli() ? null : service('request')->getIPAddress(),
        ]);
    }
}
