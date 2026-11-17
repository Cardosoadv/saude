<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Catálogo de placeholders disponíveis para modelos de documentos.
 *
 * Fonte única de verdade tanto para a resolução de valores no backend
 * (DocumentoGeradoService::resolverDadosEntidade) quanto para o menu
 * "Inserir campo" do editor no frontend.
 */
class DocumentoPlaceholders extends BaseConfig
{
    public array $catalogo = [
        'processo' => [
            'numero_processo'  => 'Número do processo (CNJ)',
            'titulo_processo'  => 'Título do processo',
            'vara'             => 'Vara',
            'tribunal'         => 'Tribunal / Órgão',
            'status'           => 'Status do processo',
            'valorCausa'       => 'Valor da causa',
            'dataDistribuicao' => 'Data de distribuição',
        ],
        'cliente' => [
            'nome'          => 'Nome / Razão social',
            'documento'     => 'CPF/CNPJ',
            'email'         => 'E-mail',
            'telefone'      => 'Telefone',
            'endereco'      => 'Endereço completo (linha única)',
            'cidade'        => 'Cidade',
            'uf'            => 'UF',
            'nacionalidade' => 'Nacionalidade',
            'estado_civil'  => 'Estado civil',
            'profissao'     => 'Profissão',
        ],
        'caso' => [
            'titulo'          => 'Título do caso',
            'tipo'            => 'Tipo',
            'descricao'       => 'Descrição',
            'status'          => 'Status',
            'area_do_direito' => 'Área do direito',
            'valor_causa'     => 'Valor envolvido',
        ],
        'contrato' => [
            'numero_contrato'  => 'Número do contrato',
            'tipo_contrato'    => 'Tipo de contrato',
            'objeto'           => 'Objeto',
            'valor'            => 'Valor',
            'data_inicio'      => 'Data de início',
            'data_fim'         => 'Data de término',
            'forma_pagamento'  => 'Forma de pagamento',
        ],
        'hoje' => [
            'data'         => 'Data de hoje (dd/mm/aaaa)',
            'data_extenso' => 'Data de hoje por extenso',
        ],
    ];
}
