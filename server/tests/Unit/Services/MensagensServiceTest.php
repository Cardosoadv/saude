<?php

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MensagensService;
use App\Services\ProcessosService;
use App\Repositories\MensagensRepository;
use App\Interfaces\AIServiceInterface;
use Exception;

class MensagensServiceTest extends CIUnitTestCase
{
    private $service;
    private $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }
        $this->mockRepository = $this->createMock(MensagensRepository::class);
        $this->service = new MensagensService($this->mockRepository);
    }

    public function testLerMensagemNaoEncontrada()
    {
        $this->mockRepository->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Mensagem não encontrada.");

        $this->service->ler(1, 123);
    }

    public function testLerMensagemAcessoNegado()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'data_leitura' => null
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Acesso negado à mensagem.");

        // Usuário 30 não é remetente nem destinatário
        $this->service->ler(1, 30);
    }

    public function testLerMensagemPeloRemetente()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'data_leitura' => null
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);

        // Não deve marcar como lida se for o remetente
        $this->mockRepository->expects($this->never())->method('update');

        $result = $this->service->ler(1, 10);

        $this->assertEquals($mensagem, $result);
        $this->assertNull($result['data_leitura']);
    }

    public function testLerMensagemPeloDestinatarioMarcaComoLida()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'data_leitura' => null
        ];

        // findById é chamado em ler() e depois em marcarLida()
        $this->mockRepository->expects($this->exactly(2))
            ->method('findById')
            ->willReturn($mensagem);

        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function($data) {
                return isset($data['data_leitura']);
            }))
            ->willReturn(true);

        $result = $this->service->ler(1, 20);

        $this->assertNotNull($result['data_leitura']);
    }

    public function testLerMensagemJaLida()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'data_leitura' => '2023-01-01 10:00:00'
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);

        // Não deve atualizar se já estiver lida
        $this->mockRepository->expects($this->never())->method('update');

        $result = $this->service->ler(1, 20);

        $this->assertEquals('2023-01-01 10:00:00', $result['data_leitura']);
    }

    public function testExcluirMensagemNaoEncontrada()
    {
        $this->mockRepository->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Mensagem não encontrada.");

        $this->service->excluir(1, 123);
    }

    public function testExcluirMensagemAcessoNegado()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Sem permissão para excluir esta mensagem.");

        $this->service->excluir(1, 30);
    }

    public function testExcluirMensagemSucessoPeloRemetente()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);
        $this->mockRepository->expects($this->once())->method('delete')->with(1)->willReturn(true);

        $this->service->excluir(1, 10);
        $this->assertTrue(true); // Se não lançou exceção, passou
    }

    public function testExcluirMensagemSucessoPeloDestinatario()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);
        $this->mockRepository->expects($this->once())->method('delete')->with(1)->willReturn(true);

        $this->service->excluir(1, 20);
        $this->assertTrue(true);
    }

    public function testClassificarUrgenciaMensagemNaoEncontrada()
    {
        $this->mockRepository->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Mensagem não encontrada.");

        $this->service->classificarUrgencia(1, 123);
    }

    public function testClassificarUrgenciaAcessoNegado()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'conteudo' => 'Olá',
            'assunto' => 'Assunto',
            'processo_id' => null,
        ];

        $this->mockRepository->method('findById')->willReturn($mensagem);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Acesso negado à mensagem.");

        $this->service->classificarUrgencia(1, 30);
    }

    public function testClassificarUrgenciaChamaAIServiceComConteudoEAssunto()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'conteudo' => 'Preciso de ajuda urgente com meu processo',
            'assunto' => 'Socorro',
            'processo_id' => null,
        ];

        $mockRepository = $this->createMock(MensagensRepository::class);
        $mockRepository->method('findById')->willReturn($mensagem);

        $mockAIService = $this->createMock(AIServiceInterface::class);
        $mockAIService->expects($this->once())
            ->method('classifyMessageUrgency')
            ->with('Preciso de ajuda urgente com meu processo', 'Socorro', [])
            ->willReturn([
                'urgencia' => 'Urgente',
                'sentimento' => 'Negativo',
                'justificativa' => 'Cliente relata urgência.',
                'score_confianca' => 0.9,
            ]);

        $mockProcessosService = $this->createMock(ProcessosService::class);

        $service = new MensagensService($mockRepository, null, $mockAIService, $mockProcessosService);

        $result = $service->classificarUrgencia(1, 20);

        $this->assertEquals('Urgente', $result['urgencia']);
    }

    public function testSugerirRespostaMensagemNaoEncontrada()
    {
        $this->mockRepository->method('findById')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Mensagem não encontrada.");

        $this->service->sugerirResposta(1, 123);
    }

    public function testSugerirRespostaComProcessoVinculadoIncluiContextoNaChamadaDeIA()
    {
        $mensagem = [
            'id' => 1,
            'remetente_id' => 10,
            'destinatario_id' => 20,
            'conteudo' => 'Qual o andamento do meu caso?',
            'assunto' => 'Dúvida',
            'processo_id' => 55,
        ];

        $mockRepository = $this->createMock(MensagensRepository::class);
        $mockRepository->method('findById')->willReturn($mensagem);

        $mockProcessosService = $this->createMock(ProcessosService::class);
        $mockProcessosService->method('buscarDetalhesProcesso')
            ->with(55)
            ->willReturn(['processo' => ['titulo_processo' => 'Caso X']]);

        $mockAIService = $this->createMock(AIServiceInterface::class);
        $mockAIService->expects($this->once())
            ->method('draftMessageReply')
            ->with('Qual o andamento do meu caso?', 'Dúvida', ['processo' => ['titulo_processo' => 'Caso X']])
            ->willReturn('Rascunho de resposta gerado pela IA.');

        $service = new MensagensService($mockRepository, null, $mockAIService, $mockProcessosService);

        $result = $service->sugerirResposta(1, 20);

        $this->assertEquals('Rascunho de resposta gerado pela IA.', $result);
    }
}
