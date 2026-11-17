<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Financeiro\Extrato\ConciliacaoConflitoException;
use App\Services\Financeiro\Extrato\ExtratoInvalidoException;
use App\Services\Financeiro\Extrato\ExtratoNaoEncontradoException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Apoio dos controllers da API v2 de extratos: permissão de escrita, usuário, corpo JSON e mapeamento
 * das exceções do módulo para 404 (não encontrado), 422 (dados inválidos) e 409 (conflito).
 * Usado em controllers que também usam ResponseTrait e VerificaPermissoesTrait.
 */
trait RespostaExtratosTrait
{
    /** Escrita em lançamentos e regras: permissão `financeiro.extratos_lancar`. */
    private function podeEditar(): bool
    {
        return $this->verificaPermissao($this->permissaoNecessaria, 'extratos_lancar');
    }

    private function userId(): ?int
    {
        return auth()->id() ? (int) auth()->id() : null;
    }

    /** @return array<string,mixed> */
    private function corpo(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (\Throwable) {
            $json = null;
        }

        return is_array($json) ? $json : (array) $this->request->getPost();
    }

    private function executar(callable $acao, string $nome): ResponseInterface
    {
        try {
            return $this->respond(['success' => true] + $acao());
        } catch (ExtratoNaoEncontradoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (ExtratoInvalidoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ConciliacaoConflitoException $e) {
            return $this->respond(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            log_message('error', '[' . static::class . "::{$nome}] " . $e->getMessage());

            return $this->respond(['success' => false, 'message' => 'Erro ao processar a solicitação.'], 500);
        }
    }
}
