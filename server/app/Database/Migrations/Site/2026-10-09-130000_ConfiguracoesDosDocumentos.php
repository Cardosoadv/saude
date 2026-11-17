<?php

namespace App\Database\Migrations;

use App\Libraries\Site\Template\SeedImporter;
use App\Services\Site\SiteConfiguracaoService;
use App\Services\Site\SiteMidiaService;
use CodeIgniter\Database\Migration;

/**
 * Dados do escritório usados nos documentos gerados (issue #656): segundo telefone e endereço do site.
 * Instalações que já tinham o seed do Clássico recebem os valores que estavam fixos nas views (e o logo
 * horizontal passa para a biblioteca de mídias); o
 * importador nunca sobrescreve uma configuração que já exista.
 */
class ConfiguracoesDosDocumentos extends Migration
{
    private const CHAVES = ['contato.telefone2', 'contato.site'];

    public function up()
    {
        $diretorio = APPPATH . 'Templates/classico/';
        $seed      = json_decode((string) file_get_contents($diretorio . 'seed/content.json'), true);
        $manifesto = json_decode((string) file_get_contents($diretorio . 'template.json'), true);

        // Só instalações do escritório do seed (as demais preenchem pelo painel).
        $nome = $this->db->table('site_configuracoes')->where('chave', 'identidade.nome')->get()->getRowArray();
        if ($nome === null || ($nome['valor'] ?? '') !== ($seed['configuracoes']['identidade.nome']['valor'] ?? null)) {
            return;
        }

        $this->registraLogoDosDocumentos();

        (new SeedImporter())->importar(
            ['configuracoes' => array_intersect_key($seed['configuracoes'] ?? [], array_flip(self::CHAVES))],
            $manifesto
        );
    }

    /**
     * Os documentos usavam `public/assets/img/logo-horizontal.png`. Agora o logo vem da biblioteca de
     * mídias (`identidade.logo`): o arquivo antigo é enviado para ela, se o escritório ainda não escolheu um.
     */
    private function registraLogoDosDocumentos(): void
    {
        $arquivo = FCPATH . 'assets/img/logo-horizontal.png';
        $tem = $this->db->table('site_configuracoes')->where('chave', 'identidade.logo')->countAllResults() > 0;
        if ($tem || !is_file($arquivo)) {
            return;
        }

        $reduzido = null;
        try {
            // O arquivo antigo tem 10745 px de largura, acima do limite da biblioteca de mídias: vai reduzido.
            $reduzido = $this->reduzido($arquivo, 2400);
            $midia = (new SiteMidiaService())->enviar($reduzido ?? $arquivo, 'logo-horizontal.png', 'Logo do escritório');
            (new SiteConfiguracaoService())->atualizar(['identidade.logo' => ['valor' => $midia['id'], 'tipo' => 'imagem']]);
        } catch (\Throwable $e) {
            log_message('error', 'Não foi possível registrar o logo dos documentos na biblioteca de mídias. ' . $e->getMessage());
        } finally {
            if ($reduzido !== null) {
                @unlink($reduzido);
            }
        }
    }

    /** PNG com no máximo `$largura` px de largura, em arquivo temporário; null se não precisa ou não é possível reduzir. */
    private function reduzido(string $arquivo, int $largura): ?string
    {
        $info = @getimagesize($arquivo);
        if ($info === false || $info[0] <= $largura || !function_exists('imagecreatefrompng')) {
            return null;
        }

        @ini_set('memory_limit', '512M'); // 10745x3137 px ocupam ~135 MB descompactados
        $origem = @imagecreatefrompng($arquivo);
        if ($origem === false) {
            return null;
        }
        $menor = imagescale($origem, $largura);
        if ($menor === false) {
            return null;
        }
        imagesavealpha($menor, true);

        $destino = tempnam(sys_get_temp_dir(), 'logo_') . '.png';
        $ok = imagepng($menor, $destino);

        return $ok ? $destino : null;
    }

    public function down()
    {
        // Intencionalmente vazio: remover apagaria configuração do escritório.
    }
}
