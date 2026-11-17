<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\IntimacoesService;
use App\Services\ProcessosService;
use App\Interfaces\AIServiceInterface;
use App\Services\TarefasService;
use App\Repositories\IntimacoesRepository;
use ReflectionClass;

class IntimacoesTaskCreationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    protected $service;
    protected $repository;
    protected $processosService;
    protected $aiService;
    protected $tarefasService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db->query('CREATE TABLE IF NOT EXISTS settings (id INTEGER PRIMARY KEY, class TEXT, key TEXT, value TEXT, type TEXT, context TEXT)');

        $this->repository = $this->createMock(IntimacoesRepository::class);
        $this->processosService = $this->createMock(ProcessosService::class);
        $this->aiService = $this->createMock(AIServiceInterface::class);
        $this->tarefasService = $this->createMock(TarefasService::class);

        $this->service = new class(
            $this->repository,
            $this->processosService,
            null,
            $this->aiService,
            null,
            null,
            $this->tarefasService
        ) extends IntimacoesService {
            private $mT;
            private $mP;
            private $mA;
            public function __construct($r, $ps, $us, $ai, $ri, $as, $ts) {
                parent::__construct($r, $ps, $us, $ai, $ri, $as, $ts);
                $this->mT = $ts;
                $this->mP = $ps;
                $this->mA = $ai;
            }
            protected function getTarefasService(): TarefasService { return $this->mT; }
            protected function getProcessoService(): ProcessosService { return $this->mP; }
            protected function getAiService(): AIServiceInterface { return $this->mA; }
            // Mock dependency methods that are now called in the service
            protected function getArgumentosService(): \App\Services\ArgumentosSugeridosService {
                return \CodeIgniter\Config\Factories::models(\App\Services\ArgumentosSugeridosService::class);
            }
        };
    }

    public function testEnriquecerIntimacaoCriaTarefaQuandoCategoriaEhPrazo()
    {
        $idIntimacao = "123";
        $texto = "Intimação de prazo fatal.";
        $numeroProcesso = "000111222";

        $dadosIA = [
            'categoria' => 'prazo',
            'resumo' => 'Prazo para contestação',
            'urgencia' => 'alta',
            'data_critica' => '2026-12-31',
            'sugestao_tarefa' => [
                'tarefa' => 'Apresentar Contestação',
                'detalhes' => 'Prazo fatal identificado pela IA'
            ]
        ];

        $this->aiService->expects($this->once())
            ->method('analyzeNotification')
            ->willReturn($dadosIA);

        $processoData = [
            'id_processo' => 10,
            'cliente_id' => 5,
            'responsavel_id' => 1,
            'numero_processo' => $numeroProcesso
        ];

        $this->processosService->method('processoJaExiste')
            ->with($numeroProcesso)
            ->willReturn($processoData);

        // Verifica se salvarTarefa foi chamado com os dados esperados
        $this->tarefasService->expects($this->once())
            ->method('salvarTarefa')
            ->with($this->callback(function ($dados) {
                return $dados['tarefa'] === 'AI: APRESENTAR CONTESTAÇÃO' &&
                       (int)$dados['processo_id'] === 10 &&
                       $dados['prazo'] === '2026-12-31';
            }))
            ->willReturn(['success' => true]);

        // Como enriquecerIntimacaoPorIA é private, usamos reflexão
        $reflection = new ReflectionClass(IntimacoesService::class);
        $method = $reflection->getMethod('enriquecerIntimacaoPorIA');
        $method->setAccessible(true);
        $method->invokeArgs($this->service, [$idIntimacao, $texto, $numeroProcesso, $processoData]);
    }

    public function testEnriquecerIntimacaoNaoCriaTarefaQuandoCategoriaEhCiencia()
    {
        $idIntimacao = "124";
        $texto = "Apenas ciência.";
        $numeroProcesso = "333444555";

        $dadosIA = [
            'categoria' => 'ciencia',
            'resumo' => 'Ciência de despacho',
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

        $reflection = new ReflectionClass(IntimacoesService::class);
        $method = $reflection->getMethod('enriquecerIntimacaoPorIA');
        $method->setAccessible(true);
        $method->invokeArgs($this->service, [$idIntimacao, $texto, $numeroProcesso]);
    }

    public function testProcessarItemNaoChamaIASeProcessoArquivado()
    {
        $item = [
            'id' => '125',
            'numero_processo' => '999999',
            'texto' => 'Intimação em processo arquivado',
            'data_disponibilizacao' => '2026-01-01',
            'datadisponibilizacao' => '2026-01-01',
            'tipoComunicacao' => 'Intimação',
            'meio' => 'DJEN',
            'link' => 'http://link',
            'numeroComunicacao' => '1',
            'hash' => 'hash',
            'meiocompleto' => 'Meio Completo'
        ];

        $processoData = [
            'id_processo' => 50,
            'numero_processo' => '999999',
            'arquived_at' => '2025-01-01 10:00:00'
        ];

        $this->processosService->method('processoJaExiste')
            ->with('999999')
            ->willReturn($processoData);

        $this->repository->expects($this->once())
            ->method('intimacaoJaExiste')
            ->willReturn(false);

        $this->repository->expects($this->once())
            ->method('salvarIntimacao');

        // IA não deve ser chamada
        $this->aiService->expects($this->never())
            ->method('analyzeNotification');

        $this->service->processarItem($item);
    }
}
