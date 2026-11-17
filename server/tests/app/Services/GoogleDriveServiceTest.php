<?php

namespace Tests\App\Services;

use App\Services\ConfiguracoesService;
use App\Services\GoogleDriveService;
use CodeIgniter\Test\CIUnitTestCase;
use Google\Client;

/**
 * Testes do escopo de leitura do GoogleDriveService.
 */
class GoogleDriveServiceTest extends CIUnitTestCase
{
    private function criarServico(array $token): GoogleDriveService
    {
        $config = $this->createMock(ConfiguracoesService::class);
        $config->method('getValor')->willReturn('valor');

        $client = $this->createMock(Client::class);
        $client->method('refreshToken')->willReturn($token);

        return new GoogleDriveService($config, $client);
    }

    public function testTokenSoComDriveFileTemLeituraLimitada(): void
    {
        $servico = $this->criarServico(['scope' => 'https://www.googleapis.com/auth/drive.file']);

        $this->assertFalse($servico->temLeituraCompleta());
    }

    public function testTokenComDriveReadonlyTemLeituraCompleta(): void
    {
        $servico = $this->criarServico([
            'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive.readonly',
        ]);

        $this->assertTrue($servico->temLeituraCompleta());
    }

    public function testTokenComDriveTotalTemLeituraCompleta(): void
    {
        $servico = $this->criarServico(['scope' => 'https://www.googleapis.com/auth/drive']);

        $this->assertTrue($servico->temLeituraCompleta());
    }
}
