<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ArgumentosSugeridosRepository;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Exception;

class ArgumentosSugeridosService extends BaseService
{
    public function __construct(?ArgumentosSugeridosRepository $repository = null)
    {
        $this->repository = $repository ?? new ArgumentosSugeridosRepository();
    }

    /**
     * Salva um lote de argumentos. Cria em "argumentos" e vincula em "processo_argumentos".
     */
    public function salvarLote(int $processoId, array $argumentos, ?int $casoId = null): void
    {
        $this->repository->salvarLote($processoId, $argumentos, $casoId);
    }

    /**
     * Cria um único argumento manualmente.
     */
    public function criar(?int $processoId, array $dados, ?int $casoId = null): array
    {
        // As teses podem vir como array ou string separada por vírgula no manual
        $teses = $dados['teses_relacionadas'] ?? [];
        if (is_string($teses)) {
            $teses = array_map('trim', explode('\n', str_replace(',', '\n', $teses)));
            $teses = array_filter($teses);
        }
        $tesesJson = json_encode(array_values($teses));

        $novoArgumento = [
            'titulo'                 => $dados['titulo'] ?? 'Novo Argumento',
            'ramo_id'                => !empty($dados['ramo_id']) ? (int) $dados['ramo_id'] : null,
            'estrategia_recomendada' => $dados['estrategia_recomendada'] ?? '',
            'teses_relacionadas'     => $tesesJson,
        ];

        return $this->repository->criarArgumentoCompleto($processoId, $novoArgumento, $casoId);
    }

    /**
     * Atualiza os dados de um argumento específico (tabela `argumentos`)
     */
    public function atualizarArgumento(int $idArgumento, array $dados): bool
    {
        $atualizar = [];
        if (isset($dados['titulo'])) {
            $atualizar['titulo'] = $dados['titulo'];
        }
        if (isset($dados['estrategia_recomendada'])) {
            $atualizar['estrategia_recomendada'] = $dados['estrategia_recomendada'];
        }
        if (isset($dados['teses_relacionadas'])) {
            $teses = $dados['teses_relacionadas'];
            if (is_string($teses)) {
                $teses = array_map('trim', explode('\n', str_replace(',', '\n', $teses)));
                $teses = array_filter($teses);
            }
            $atualizar['teses_relacionadas'] = json_encode(array_values($teses));
        }
        if (isset($dados['ramo_id'])) {
            $atualizar['ramo_id'] = !empty($dados['ramo_id']) ? (int) $dados['ramo_id'] : null;
        }

        if (empty($atualizar)) {
            return true;
        }

        return $this->repository->atualizarArgumento($idArgumento, $atualizar);
    }

    /**
     * Atualiza a avaliação ("like", "dislike" ou null) do relacionamento
     */
    public function avaliar(int $idProcessoArgumento, ?string $avaliacao): bool
    {
        if ($avaliacao !== null && !in_array($avaliacao, ['like', 'dislike'])) {
            throw new Exception("Avaliação inválida. Deve ser 'like', 'dislike' ou null.");
        }
        return $this->update($idProcessoArgumento, ['avaliacao' => $avaliacao]);
    }

    public function buscarPorProcesso(int $processoId): array
    {
        $argumentos = $this->repository->buscarPorProcesso($processoId);

        // Decodificar JSON
        foreach ($argumentos as &$arg) {
            if (!empty($arg['teses_relacionadas'])) {
                $arg['teses_relacionadas'] = json_decode($arg['teses_relacionadas'], true);
            } else {
                $arg['teses_relacionadas'] = [];
            }
        }

        return $argumentos;
    }

    public function buscarPorCaso(int $casoId): array
    {
        $argumentos = $this->repository->buscarPorCaso($casoId);

        // Decodificar JSON
        foreach ($argumentos as &$arg) {
            if (!empty($arg['teses_relacionadas'])) {
                $arg['teses_relacionadas'] = json_decode($arg['teses_relacionadas'], true);
            } else {
                $arg['teses_relacionadas'] = [];
            }
        }

        return $argumentos;
    }

    public function buscarTodosPaginado(string $search = '', ?int $processoId = null, int $page = 1, int $limit = 20, ?int $ramoId = null): array
    {
        $resultado = $this->repository->buscarTodosPaginado($search, $processoId, $page, $limit, $ramoId);

        $resultados = $resultado['data'];
        $pager = $resultado['pager'];

        foreach ($resultados as &$arg) {
            if (!empty($arg['teses_relacionadas'])) {
                $arg['teses_relacionadas'] = json_decode($arg['teses_relacionadas'], true);
            } else {
                $arg['teses_relacionadas'] = [];
            }
        }

        return [
            'data' => $resultados,
            'pagination' => [
                'current_page' => $pager->getCurrentPage(),
                'total_pages'  => $pager->getPageCount(),
                'total_items'  => $pager->getTotal(),
                'per_page'     => $limit,
            ],
        ];
    }
}
