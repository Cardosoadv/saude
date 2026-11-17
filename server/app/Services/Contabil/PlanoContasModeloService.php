<?php

declare(strict_types=1);

namespace App\Services\Contabil;

use CognitioTechny\Contabil\Application\Chart\ChartFormat;
use CognitioTechny\Contabil\Infrastructure\CodeIgniter\Commands\SeedChartCommand;
use InvalidArgumentException;

/**
 * Importa os planos de contas modelo: o plano padrão do sistema (ITG 1000,
 * em `app/Database/PlanosModelo`) e os que acompanham o pacote
 * cognitiotechny/contabil.
 *
 * Os modelos são ponto de partida: precisam ser revisados pela contabilidade
 * antes de serem tratados como plano oficial.
 *
 * @version 1.1.0
 */
class PlanoContasModeloService
{
    /**
     * Plano padrão do sistema: estrutura do Anexo 7 da ITG 1000 (pequena
     * empresa) com as contas específicas de um escritório de advocacia.
     */
    public const MODELO_PADRAO = 'itg-1000-advocacia';

    /**
     * Modelos disponíveis, com o rótulo exibido na tela. O padrão vem primeiro.
     */
    private const MODELOS = [
        self::MODELO_PADRAO     => 'Padrão ITG 1000 - Escritório de advocacia (valores de clientes segregados)',
        'professional-services' => 'Escritório / prestação de serviços profissionais (valores de clientes segregados)',
        'generic'               => 'Genérico (cinco classes)',
    ];

    /**
     * @return list<array{id: string, rotulo: string}>
     */
    public function listarModelos(): array
    {
        $modelos = [];

        foreach (self::MODELOS as $id => $rotulo) {
            if (is_file($this->caminho($id))) {
                $modelos[] = ['id' => $id, 'rotulo' => $rotulo];
            }
        }

        return $modelos;
    }

    /**
     * Cria a entidade (se ainda não existir) e importa o modelo. Contas cujo
     * código já existe são mantidas, então repetir a operação não duplica nada.
     *
     * @return array{created: int, skipped: int}
     *
     * @throws InvalidArgumentException Modelo desconhecido.
     */
    public function importar(string $modelo, int $entityId = 1): array
    {
        if (! isset(self::MODELOS[$modelo]) || ! is_file($this->caminho($modelo))) {
            throw new InvalidArgumentException('Modelo de plano de contas desconhecido.');
        }

        $this->garantirEntidade($entityId);

        $resultado = service('contabil')->importChart()->execute(
            $entityId,
            ChartFormat::parse((string) file_get_contents($this->caminho($modelo)), ChartFormat::JSON),
            true,
        );

        return $resultado->toArray();
    }

    private function garantirEntidade(int $entityId): void
    {
        $db = db_connect();

        if ($db->table('acc_entities')->where('id', $entityId)->countAllResults() > 0) {
            return;
        }

        $agora = date('Y-m-d H:i:s');

        $db->table('acc_entities')->insert([
            'id'            => $entityId,
            'name'          => 'Cardoso & Bruno Advogados',
            'currency'      => 'BRL',
            'default_basis' => 'ACCRUAL',
            'created_at'    => $agora,
            'updated_at'    => $agora,
        ]);
    }

    private function caminho(string $modelo): string
    {
        if ($modelo === self::MODELO_PADRAO) {
            return APPPATH . 'Database/PlanosModelo/' . $modelo . '.json';
        }

        return SeedChartCommand::MODELS_DIRECTORY . '/' . $modelo . '.json';
    }
}
