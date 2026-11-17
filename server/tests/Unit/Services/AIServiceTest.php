<?php

namespace Tests\Unit\Services;

use App\Services\AIManagerService;
use App\Services\GeminiService;
use CodeIgniter\Test\CIUnitTestCase;

class AIServiceTest extends CIUnitTestCase
{
    private AIManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (isset($this->db)) { $this->db->query('PRAGMA foreign_keys = OFF;'); }
        $this->service = new AIManagerService();
    }

    public function testPredictProcessOutcomeReturnsDefaultsOnEmptyData()
    {
        $result = $this->service->predictProcessOutcome([]);
        $this->assertIsArray($result);
        $this->assertEquals('Possível', $result['risco_predito']);
    }

    public function testPredictProcessOutcomeReturnsDefaultsOnMissingProcessKey()
    {
        $result = $this->service->predictProcessOutcome(['movimentacoes' => []]);
        $this->assertIsArray($result);
        $this->assertEquals('Possível', $result['risco_predito']);
    }

    public function testAnalyzeMovementSentimentReturnsDefaultsOnEmptyText()
    {
        $result = $this->service->analyzeMovementSentiment('');
        $this->assertIsArray($result);
        $this->assertEquals('Neutro', $result['sentimento']);
        $this->assertStringContainsString('Não foi possível', $result['justificativa']);
    }

    public function testClassifyMessageUrgencyReturnsDefaultsOnEmptyContent()
    {
        $result = $this->service->classifyMessageUrgency('', 'Assunto qualquer');
        $this->assertIsArray($result);
        $this->assertEquals('Normal', $result['urgencia']);
        $this->assertEquals('Neutro', $result['sentimento']);
    }

    public function testDraftMessageReplyReturnsMessageOnEmptyContent()
    {
        $result = $this->service->draftMessageReply('', 'Assunto qualquer');
        $this->assertEquals('Conteúdo da mensagem não fornecido.', $result);
    }
}
