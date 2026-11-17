<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DocumentoModeloRepository;
use App\Repositories\RamosDireitoRepository;
use Exception;

/**
 * Serviço para gerenciamento de Modelos de Documentos (Templates).
 */
class DocumentoModeloService extends BaseService
{
    protected RamosDireitoRepository $ramosRepository;

    public function __construct(
        ?DocumentoModeloRepository $repository = null,
        ?RamosDireitoRepository $ramosRepository = null
    ) {
        $this->repository = $repository ?? new DocumentoModeloRepository();
        $this->ramosRepository = $ramosRepository ?? new RamosDireitoRepository();
    }

    /**
     * Lista modelos combinando filtros opcionais (ramo_id, status, tipo_alvo, ativo,
     * etiqueta_id, q), já enriquecidos com nome do ramo e etiquetas vinculadas.
     */
    public function listarComFiltros(array $filtros, int $perPage = 20): array
    {
        $modelos = $this->repository->listarComFiltros($filtros, $perPage);

        $ramos = $this->ramosRepository->findAll();
        $ramosMap = array_column($ramos, 'nome', 'id');

        foreach ($modelos as &$modelo) {
            $modelo['ramo_nome'] = $ramosMap[$modelo['ramo_id']] ?? 'N/A';
            $modelo['etiquetas'] = $this->repository->joinEtiquetas((int) $modelo['id']);
        }

        return $modelos;
    }

    /**
     * Salva um modelo de documento (Criação ou Atualização).
     */
    public function salvar(array $data): int|bool
    {
        $id = isset($data['id']) ? (int) $data['id'] : null;

        // Identifica placeholders automaticamente (sintaxe namespaced {{origem.campo}})
        $placeholders = $this->extrairPlaceholders($data['conteudo']);

        // Formata os dados
        $saveData = [
            'titulo'            => $data['titulo'],
            'ramo_id'           => !empty($data['ramo_id']) ? (int) $data['ramo_id'] : null,
            'tipo_alvo'         => $data['tipo_alvo'] ?? 'ambos',
            'conteudo'          => $data['conteudo'],
            'status'            => $data['status'] ?? 'Rascunho',
            'versao'            => $data['versao'] ?? '1.0.0',
            'ativo'             => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            'placeholders'      => json_encode($placeholders),
            'alerta_vencimento' => !empty($data['alerta_vencimento']) ? (int) $data['alerta_vencimento'] : 365,
            'ultima_revisao_em' => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            $resultado = $this->update($id, $saveData);
        } else {
            $id = $this->create($saveData);
            $resultado = $id;
        }

        if ($id && array_key_exists('etiqueta_ids', $data)) {
            $this->repository->sincronizarEtiquetas($id, array_map('intval', $data['etiqueta_ids']));
        }

        return $resultado;
    }

    /**
     * Extrai placeholders no formato {{origem.campo}} do texto, sinalizando quais
     * não existem no catálogo (`DocumentoPlaceholders`) sem bloquear o salvamento.
     */
    protected function extrairPlaceholders(string $texto): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\.([a-z0-9_]+)\s*\}\}/i', $texto, $matches, PREG_SET_ORDER);
        $catalogo = $this->getCatalogoPlaceholders();

        $placeholders = [];
        foreach ($matches as $match) {
            $origem = strtolower($match[1]);
            $campo  = $match[2];
            $chave  = "{$origem}.{$campo}";

            if (isset($placeholders[$chave])) {
                continue;
            }

            $placeholders[$chave] = [
                'chave'        => $chave,
                'origem'       => $origem,
                'campo'        => $campo,
                'desconhecido' => !isset($catalogo[$origem][$campo]),
            ];
        }

        return array_values($placeholders);
    }

    /**
     * Preenche os placeholders {{origem.campo}} de um conteúdo com dados reais.
     *
     * @param string $conteudo O texto do modelo.
     * @param array $dados Mapa achatado "origem.campo" => valor.
     * @return string Texto processado.
     */
    public function processarPlaceholders(string $conteudo, array $dados): string
    {
        foreach ($dados as $chave => $valor) {
            $padrao = '/\{\{\s*' . preg_quote($chave, '/') . '\s*\}\}/i';
            $conteudo = preg_replace($padrao, (string) $valor, $conteudo);
        }
        return $conteudo;
    }

    /**
     * Retorna o catálogo de placeholders disponíveis (fonte única backend/frontend).
     */
    public function getCatalogoPlaceholders(): array
    {
        return config('DocumentoPlaceholders')->catalogo;
    }

    /**
     * Retorna as etiquetas vinculadas a um modelo.
     */
    public function getEtiquetas(int $modeloId): array
    {
        return $this->repository->joinEtiquetas($modeloId);
    }

    /**
     * Retorna a lista de ramos para preencher selects.
     */
    public function getRamosOptions(): array
    {
        return $this->ramosRepository->findAll();
    }
}
