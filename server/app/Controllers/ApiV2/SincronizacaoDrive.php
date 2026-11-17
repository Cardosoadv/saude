<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2;

use App\Controllers\BaseController;
use App\Services\DriveSyncService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Controller API V2 da sincronização de pastas do Google Drive (clientes): resumo, fila de jobs, entidades, enfileiramento e processamento.
 * Restrito a administradores.
 *
 * @version 2.0.0
 * @since 2026-10-17
 */
class SincronizacaoDrive extends BaseController
{
    use ResponseTrait;

    protected DriveSyncService $service;

    public function __construct(?DriveSyncService $service = null)
    {
        $this->service = $service ?? new DriveSyncService();
    }

    /** GET drive/resumo */
    public function resumo(): ResponseInterface
    {
        return $this->executar('consultar a sincronização do Drive', fn () => [
            'data' => $this->service->resumo(),
        ]);
    }

    /** GET drive/jobs?status=&tipo=&busca=&pagina=&por_pagina= */
    public function jobs(): ResponseInterface
    {
        return $this->executar('listar os jobs do Drive', fn () => [
            'data' => $this->service->listarJobs(
                [
                    'status' => (string) $this->request->getGet('status'),
                    'tipo'   => (string) $this->request->getGet('tipo'),
                    'busca'  => trim((string) $this->request->getGet('busca')),
                ],
                (int) ($this->request->getGet('pagina') ?: 1),
                (int) ($this->request->getGet('por_pagina') ?: 25)
            ),
        ]);
    }

    /** GET drive/entidades?tipo=cliente&situacao=&busca=&pagina=&por_pagina= */
    public function entidades(): ResponseInterface
    {
        return $this->executar('listar as entidades do Drive', fn () => [
            'data' => $this->service->listarEntidades(
                (string) ($this->request->getGet('tipo') ?: 'cliente'),
                [
                    'situacao' => (string) $this->request->getGet('situacao'),
                    'busca'    => trim((string) $this->request->getGet('busca')),
                ],
                (int) ($this->request->getGet('pagina') ?: 1),
                (int) ($this->request->getGet('por_pagina') ?: 25)
            ),
        ]);
    }

    /** POST drive/sincronizar-retroativo (e GET, por compatibilidade) */
    public function retroativo(): ResponseInterface
    {
        return $this->executar('enfileirar a sincronização retroativa', function () {
            $r = $this->service->enfileirarPendentes();
            log_message('warning', 'Sincronização retroativa do Drive agendada pelo usuário #' . auth()->id() . '.');

            return [
                'message'                => 'Sincronização retroativa agendada.',
                'jobs_criados_clientes'  => $r['clientes'],
                'data'                   => $r,
            ];
        });
    }

    /** POST drive/enfileirar  {tipo, ids[]} */
    public function enfileirar(): ResponseInterface
    {
        return $this->executar('enfileirar entidades no Drive', function () {
            $body  = $this->corpo();
            $criados = $this->service->enfileirarEntidades((string) ($body['tipo'] ?? ''), (array) ($body['ids'] ?? []));

            return ['message' => "{$criados} job(s) enfileirado(s).", 'data' => ['criados' => $criados]];
        });
    }

    /** POST drive/processar  {limite} */
    public function processar(): ResponseInterface
    {
        return $this->executar('processar a fila do Drive', function () {
            // Cada pasta é uma chamada à API do Google; evita estourar o limite do PHP.
            @set_time_limit(300);
            $limite = (int) ($this->corpo()['limite'] ?? 20);
            $r = $this->service->processar($limite);

            return [
                'message' => $r['processados'] === 0
                    ? 'Nenhum job pendente.'
                    : "{$r['processados']} job(s) processado(s): {$r['sucesso']} com sucesso, {$r['falhas']} com falha.",
                'data' => $r,
            ];
        });
    }

    /** POST drive/jobs/reprocessar  {ids[]} ou {status} */
    public function reprocessar(): ResponseInterface
    {
        return $this->executar('reprocessar jobs do Drive', function () {
            $body = $this->corpo();
            $n = $this->service->reprocessar((array) ($body['ids'] ?? []), $body['status'] ?? null);

            return ['message' => "{$n} job(s) devolvido(s) à fila.", 'data' => ['afetados' => $n]];
        });
    }

    /** POST drive/jobs/excluir  {ids[]} ou {status} */
    public function excluir(): ResponseInterface
    {
        return $this->executar('excluir jobs do Drive', function () {
            $body = $this->corpo();
            $n = $this->service->excluir((array) ($body['ids'] ?? []), $body['status'] ?? null);

            return ['message' => "{$n} job(s) removido(s).", 'data' => ['afetados' => $n]];
        });
    }

    /**
     * Valida a permissão, executa a ação e padroniza a resposta/erros.
     *
     * @param callable():array $acao Retorna campos extras da resposta.
     */
    private function executar(string $descricao, callable $acao): ResponseInterface
    {
        if (!auth()->user()->can('admin.access')) {
            return $this->respond(['success' => false, 'message' => "Você não tem permissão para {$descricao}."], 403);
        }

        try {
            return $this->respond(['success' => true] + $acao());
        } catch (Throwable $e) {
            log_message('error', "Erro ao {$descricao} (Drive): " . $e->getMessage());

            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Corpo JSON ou form da requisição. */
    private function corpo(): array
    {
        $json = $this->request->getJSON(true);

        return is_array($json) ? $json : (array) $this->request->getPost();
    }
}
