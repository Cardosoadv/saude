<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\DjeService;
use App\Repositories\DjeRepository;
use App\Repositories\DjeArquivoProcessadoRepository;
use App\Services\ProcessosService;
use App\Services\TarefasService;
use App\Interfaces\AIServiceInterface;
use ReflectionClass;

class DjeCalendarSyncTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    protected $service;
    protected $djeRepository;
    protected $arquivoRepository;
    protected $processosService;
    protected $tarefasService;
    protected $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY, class TEXT, key TEXT, value TEXT, type TEXT, context TEXT)');

        $this->djeRepository = $this->createMock(DjeRepository::class);
        $this->arquivoRepository = $this->createMock(DjeArquivoProcessadoRepository::class);
        $this->processosService = $this->createMock(ProcessosService::class);
        $this->tarefasService = $this->createMock(TarefasService::class);
        $this->aiService = $this->createMock(AIServiceInterface::class);

        $this->service = new DjeService(
            $this->djeRepository,
            $this->arquivoRepository,
            $this->processosService,
            $this->tarefasService,
            $this->aiService
        );
    }

    public function testSincronizarDjeAoCalendarioCriaTarefaQuandoCategoriaEhPrazo()
    {
        $numeroProcesso = "000111222";
        $item = [
            'id' => '123',
            'numero_processo' => $numeroProcesso,
            'texto' => 'Despacho que determina manifestação no prazo de 5 dias.',
            'data_disponibilizacao' => '2026-12-01',
        ];

        $processoData = [
            'id_processo' => 10,
            'cliente_id' => 5,
            'responsavel_id' => 1,
            'numero_processo' => $numeroProcesso
        ];

        $this->processosService->expects($this->once())
            ->method('processoJaExiste')
            ->with($numeroProcesso)
            ->willReturn($processoData);

        $dadosIA = [
            'categoria' => 'prazo',
            'resumo' => 'Prazo para manifestação',
            'urgencia' => 'alta',
            'data_critica' => '2026-12-10',
            'sugestao_tarefa' => [
                'tarefa' => 'Manifestar nos Autos',
                'detalhes' => 'Manifestação sobre laudo pericial'
            ]
        ];

        $this->aiService->expects($this->once())
            ->method('analyzeNotification')
            ->willReturn($dadosIA);

        // Verifica se salvarTarefa foi chamado com os dados mapeados do DJe
        $this->tarefasService->expects($this->once())
            ->method('salvarTarefa')
            ->with($this->callback(function ($dados) {
                return $dados['tarefa'] === 'DJE: MANIFESTAR NOS AUTOS' &&
                       (int)$dados['processo_id'] === 10 &&
                       $dados['prazo'] === '2026-12-10';
            }))
            ->willReturn(['success' => true]);

        // Usa reflexão para testar o método privado sincronizarDjeAoCalendario
        $reflection = new ReflectionClass(DjeService::class);
        $method = $reflection->getMethod('sincronizarDjeAoCalendario');
        $method->setAccessible(true);
        $method->invokeArgs($this->service, [$item]);
    }

    public function testSincronizarDjeAoCalendarioNaoCriaTarefaSeNaoForPrazo()
    {
        $numeroProcesso = "333444555";
        $item = [
            'id' => '124',
            'numero_processo' => $numeroProcesso,
            'texto' => 'Apenas ciência de despacho.',
            'data_disponibilizacao' => '2026-12-01',
        ];

        $processoData = [
            'id_processo' => 20,
            'cliente_id' => null,
            'responsavel_id' => 1,
            'numero_processo' => $numeroProcesso
        ];

        $this->processosService->expects($this->once())
            ->method('processoJaExiste')
            ->with($numeroProcesso)
            ->willReturn($processoData);

        $dadosIA = [
            'categoria' => 'ciencia',
            'resumo' => 'Ciência',
            'urgencia' => 'baixa',
            'data_critica' => null,
            'sugestao_tarefa' => []
        ];

        $this->aiService->expects($this->once())
            ->method('analyzeNotification')
            ->willReturn($dadosIA);

        // tarefasService NÃO deve ser chamado
        $this->tarefasService->expects($this->never())
            ->method('salvarTarefa');

        $reflection = new ReflectionClass(DjeService::class);
        $method = $reflection->getMethod('sincronizarDjeAoCalendario');
        $method->setAccessible(true);
        $method->invokeArgs($this->service, [$item]);
    }
}
