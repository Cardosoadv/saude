<?php

declare(strict_types=1);

namespace App\Controllers\ApiV2\Site\admin;

use App\Libraries\Site\Exceptions\ErroDeValidacao;
use App\Libraries\Site\Exceptions\NaoEncontrado;

/**
 * Traduz as exceções do conteúdo do site em respostas HTTP:
 * ErroDeValidacao → 422 (erros por campo), NaoEncontrado → 404, o resto → 500
 * (a mensagem real vai para o log, não para o cliente).
 */
trait RespondeErrosDoSiteTrait
{
    protected function executar(callable $acao)
    {
        try {
            return $acao();
        } catch (ErroDeValidacao $e) {
            return $e->avisos() === []
                ? $this->fail($e->erros(), 422)
                : $this->respond(['status' => 422, 'error' => 422, 'messages' => $e->erros(), 'avisos' => $e->avisos()], 422);
        } catch (NaoEncontrado $e) {
            return $this->failNotFound($e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', static::class . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());

            return $this->failServerError('Não foi possível concluir a operação.');
        }
    }

    /** Corpo JSON da requisição (ou formulário). */
    protected function entrada(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            throw ErroDeValidacao::campo('corpo', 'JSON inválido.');
        }

        $dados = $json ?? $this->request->getPost() ?? [];

        return is_array($dados) ? $dados : [];
    }
}
