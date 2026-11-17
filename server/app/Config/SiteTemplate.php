<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Parâmetros do formato de template do site (épico #641, issue #644).
 */
class SiteTemplate extends BaseConfig
{
    /**
     * Versão do OrdoJuris usada para checar `ordojuris` do manifesto.
     * Manter alinhada à versão do README.
     */
    public string $versaoOrdojuris = '7.1.0';

    /** Motores de view aceitos. `php` só para templates de origem core/cli. */
    public array $motores = ['php', 'twig'];

    /** Tipos de campo que uma seção pode declarar. */
    public array $tiposCampo = [
        'texto', 'texto_inline', 'textarea', 'rich_text', 'imagem', 'icone',
        'link', 'opcao', 'bool', 'lista', 'referencia_menu', 'dinamico',
    ];

    /** Fontes de dados que um campo `dinamico` pode usar (registradas na #645). */
    public array $fontesDinamicas = ['ultimas_noticias', 'areas'];

    /** Máximo de itens de uma lista, por segurança contra JSON gigante. */
    public int $maxItensLista = 100;

    /**
     * Diretório de cada origem de template. Templates `core` vêm no deploy;
     * `cli` e `upload` são instalados em writable (ver #650).
     *
     * @var array<string, string>
     */
    public array $diretorios = [];

    /**
     * Onde os assets dos templates ficam publicados e o prefixo da URL pública
     * (relativo ao baseURL). Nesta hospedagem a raiz do projeto é servida, por
     * isso o prefixo `public/`, o mesmo usado em `public/assets/css/site.css`.
     */
    public string $diretorioAssets = '';
    public string $prefixoAssets   = 'public/templates';

    /** Biblioteca de mídias do site (uploads da #649), com a mesma lógica de prefixo. */
    public string $diretorioUploads = '';
    public string $prefixoUploads   = 'public/uploads/site';

    /**
     * Famílias do Google Fonts que o escritório pode escolher para as fontes do tema, com a
     * família genérica de reserva. Lista fechada: o nome vai para uma URL e para o CSS.
     *
     * @var array<string, string>
     */
    public array $fontesPermitidas = [
        'Cormorant Garamond' => 'serif', 'Playfair Display' => 'serif', 'Lora' => 'serif', 'Merriweather' => 'serif',
        'EB Garamond' => 'serif', 'Libre Baskerville' => 'serif', 'Crimson Text' => 'serif', 'DM Serif Display' => 'serif',
        'Outfit' => 'sans-serif', 'Inter' => 'sans-serif', 'Montserrat' => 'sans-serif', 'Open Sans' => 'sans-serif',
        'Roboto' => 'sans-serif', 'Lato' => 'sans-serif', 'Poppins' => 'sans-serif', 'Raleway' => 'sans-serif',
        'Nunito' => 'sans-serif', 'Work Sans' => 'sans-serif', 'DM Sans' => 'sans-serif', 'Source Sans 3' => 'sans-serif',
    ];

    /** Envio de imagens para a biblioteca de mídias. */
    public int $maxUploadBytes = 5 * 1024 * 1024;
    public int $maxLadoPx      = 8000;
    public int $larguraMiniatura = 480;

    /** Tipos aceitos, pelo tipo real da imagem (não pelo que o cliente informa). SVG fica de fora de propósito. */
    public array $tiposMidia = [
        'image/jpeg'                => 'jpg',
        'image/png'                 => 'png',
        'image/gif'                 => 'gif',
        'image/webp'                => 'webp',
        'image/x-icon'              => 'ico',
        'image/vnd.microsoft.icon'  => 'ico',
    ];

    /** Validade (segundos) do link de pré-visualização de rascunho. */
    public int $validadePreview = 3600;

    /** Validade (s) do HTML renderizado guardado em cache; 0 desliga o cache de páginas. */
    public int $ttlPagina = 3600;

    public function __construct()
    {
        parent::__construct();

        $this->diretorioAssets  = ROOTPATH . 'public/templates';
        $this->diretorioUploads = ROOTPATH . 'public/uploads/site';
        $this->diretorios = [
            'core'   => APPPATH . 'Templates',
            'cli'    => WRITEPATH . 'templates',
            'upload' => WRITEPATH . 'templates',
        ];
    }
}
