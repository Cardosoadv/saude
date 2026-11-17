<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ObjetosRepository;

/**
 * Serviço para gerenciamento de Objetos (Bens/Direitos).
 */
class ObjetosService extends BaseService
{
    public function __construct(?ObjetosRepository $repository = null)
    {
        $this->repository = $repository ?? new ObjetosRepository();
    }

    /**
     * Lista objetos com paginação e busca.
     */
    public function listarObjetos(?string $search = null, int $perPage = 25, int $page = 1): array
    {
        return $this->repository->buscarObjetos($search, $perPage, $page);
    }

    /**
     * Lista todos os objetos ordenados.
     *
     * @return array
     */
    public function listarObjetosOrdenados(): array
    {
        return $this->repository->findAllOrdenado();
    }

    /**
     * Seleciona objetos por ID de processo.
     *
     * @param int $processoId ID do processo.
     * @return array Lista de objetos associados ao processo.
     */
    public function selecionarObjetoPorProcessoId(int $processoId): array
    {
        return $this->repository->selecionarObjetoPorProcessoId($processoId);
    }

    /**
     * Busca objetos com filtros e paginação.
     *
     * @param string|null $search Termo de busca.
     * @param int $page Página atual.
     * @param int $perPage Itens por página.
     * @return array
     */
    public function buscarObjetos(?string $search = null, int $page = 1, int $perPage = 25): array
    {
        return $this->repository->buscarObjetos($search, $page, $perPage);
    }

    /**
     * Retorna a lista de bairros distintos.
     */
    public function getBairrosDistintos(): array
    {
        return $this->repository->getBairrosDistintos();
    }

    /**
     * Retorna a lista de cidades distintas.
     */
    public function getCidadesDistintas(): array
    {
        return $this->repository->getCidadesDistintas();
    }

    /**
     * Retorna dados para exportação.
     */
    public function getDadosExportacao(): array
    {
        return $this->repository->getDadosExportacao();
    }

    /**
     * Retorna os objetos que possuem processos vinculados, agrupados por
     * objeto, cada um com a lista de processos ligados a ele.
     *
     * @return array<int, array{objeto: array, processos: array}>
     */
    public function getObjetosComProcessosVinculados(): array
    {
        $linhas = $this->repository->getObjetosComProcessosVinculados();

        $agrupado = [];
        foreach ($linhas as $linha) {
            $idObjeto = $linha['id_objeto'];

            if (!isset($agrupado[$idObjeto])) {
                $agrupado[$idObjeto] = [
                    'objeto' => [
                        'id_objeto'   => $linha['id_objeto'],
                        'cidade'      => $linha['cidade'],
                        'bairro'      => $linha['bairro'],
                        'logradouro'  => $linha['logradouro'],
                        'numero'      => $linha['numero_imovel'],
                        'complemento' => $linha['complemento'],
                        'cep'         => $linha['cep'],
                        'quadra'      => $linha['quadra'],
                        'lote'        => $linha['lote'],
                        'inscricao'   => $linha['inscricao'],
                        'cod_interno' => $linha['cod_interno'],
                        'matricula'   => $linha['matricula'],
                        'cartorio'    => $linha['cartorio'],
                    ],
                    'processos' => [],
                ];
            }

            $agrupado[$idObjeto]['processos'][] = [
                'id_processo'               => $linha['id_processo'],
                'numeroprocessocommascara'  => $linha['numeroprocessocommascara'],
                'numero_processo'           => $linha['numero_processo'],
                'titulo_processo'           => $linha['titulo_processo'],
                'dataDistribuicao'          => $linha['dataDistribuicao'],
                'valorCausa'                => $linha['valorCausa'],
                'encerrado'                 => $linha['encerrado'],
                'risco'                     => $linha['risco'],
                'cliente_nome'              => $linha['cliente_nome'],
            ];
        }

        return array_values($agrupado);
    }

    /**
     * Verifica se existe um objeto com o código interno fornecido.
     */
    public function existeObjetoCodInterno(string $codInterno): bool
    {
        return $this->repository->existeObjetoCodInterno($codInterno);
    }

    /**
     * Verifica se existe um objeto com a inscrição fornecida.
     */
    public function existeObjetoInscricao(string $inscricao): bool
    {
        return $this->repository->existeObjetoInscricao($inscricao);
    }

    /**
     * Vincula um objeto a um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $objetoId O ID do objeto.
     * @return void
     */
    public function vincularProcessoObjeto(int $processoId, int $objetoId): void
    {
        $this->repository->vincularProcessoObjeto($processoId, $objetoId);
        // Clean cache if necessary, probably in ProcessosService or here if we have access to cache service
    }

    /**
     * Desvincula um objeto de um processo.
     *
     * @param int $processoId O ID do processo.
     * @param int $objetoId O ID do objeto.
     * @return void
     */
    public function desvincularObjetoProcesso(int $processoId, int $objetoId): void
    {
        $this->repository->desvincularObjetoProcesso($processoId, $objetoId);
    }
}
