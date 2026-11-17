<?php

declare(strict_types=1);

namespace App\Repositories\Site;

use App\Models\Site\SiteSecaoModel;
use App\Repositories\BaseRepository;

class SiteSecaoRepository extends BaseRepository
{
    public function __construct(?SiteSecaoModel $model = null)
    {
        parent::__construct();
        $this->model    = $model ?? new SiteSecaoModel();
        $this->cacheKey = 'site_secoes';
    }

    /**
     * Seções de uma página em ordem; `dados` já decodificado.
     */
    public function porPagina(int $paginaId, bool $apenasVisiveis = false): array
    {
        $chave = "pagina_{$paginaId}_" . ($apenasVisiveis ? 'v' : 't');

        return $this->remember($chave, function () use ($paginaId, $apenasVisiveis) {
            $q = $this->model->where('pagina_id', $paginaId);
            if ($apenasVisiveis) {
                $q->where('visivel', 1);
            }
            $secoes = $q->orderBy('ordem', 'ASC')->orderBy('id', 'ASC')->findAll();

            foreach ($secoes as &$secao) {
                $secao['dados'] = $secao['dados'] ? (json_decode($secao['dados'], true) ?? []) : [];
            }
            return $secoes;
        });
    }

    /**
     * Data da última alteração de seção de cada página publicada, para o
     * `lastmod` do sitemap.
     *
     * @return array<int, string> [pagina_id => 'Y-m-d H:i:s']
     */
    public function ultimaAtualizacaoPorPagina(): array
    {
        $linhas = $this->db->table('site_secoes')
            ->select('pagina_id, MAX(updated_at) AS ultima')
            ->where('deleted_at', null)
            ->groupBy('pagina_id')
            ->get()->getResultArray();

        return array_column($linhas, 'ultima', 'pagina_id');
    }

    /**
     * Define a ordem das seções de uma página a partir da lista de ids.
     * Ids que não pertencem à página são ignorados.
     */
    public function reordenar(int $paginaId, array $ids): void
    {
        $this->db->transStart();
        foreach (array_values($ids) as $posicao => $id) {
            $this->db->table('site_secoes')
                ->where('pagina_id', $paginaId)
                ->where('id', (int) $id)
                ->update(['ordem' => $posicao + 1]);
        }
        $this->db->transComplete();
        $this->clearCache();
    }
}
