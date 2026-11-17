<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Traits\GeminiTrait;
use Config\Mcp as McpConfig;
use Exception;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Result\CallToolResult;

/**
 * Camada agente que dá ao Gemini acesso de leitura/escrita ao OrdoJuris via
 * MCP, em vez de exigir que cada feature (Davinci, checklist, triagem de
 * intimações etc.) monte manualmente o contexto que o modelo pode precisar.
 *
 * Fluxo: lista as tools do próprio servidor MCP (/mcp) → converte para o
 * formato de function-calling do Gemini → deixa o modelo decidir quais
 * chamar → executa via MCP → devolve o resultado ao modelo → repete até
 * uma resposta final em texto.
 *
 * Todas as tools de escrita expostas hoje (criar_tarefa,
 * criar_anotacao_processo, adicionar_processo, adicionar_cliente,
 * adicionar_contrato) exigem `usuarioId`. Este serviço sempre
 * sobrescreve esse argumento com o $usuarioId real recebido em `perguntar()`
 * antes de despachar a chamada — o modelo nunca decide em nome de quem agir.
 */
final class GeminiMcpAgentService
{
    use GeminiTrait;

    private const MAX_RODADAS_DE_FERRAMENTAS = 6;

    private McpConfig $config;

    public function __construct()
    {
        $this->bootGeminiTrait();
        $this->config = config(McpConfig::class);
    }

    /**
     * @param array<int, array{role: string, parts: array}> $historico Turnos anteriores da conversa (opcional).
     * @return array{resposta: string, ferramentas_usadas: list<array{nome: string, argumentos: array}>}
     */
    public function perguntar(string $pergunta, int $usuarioId, array $historico = []): array
    {
        $client = Client::builder()
            ->setClientInfo('OrdoJuris GeminiAgent', '1.0.0')
            ->build();

        $client->connect(new HttpTransport(
            endpoint: $this->resolverEndpoint(),
            headers: [
                'Authorization' => 'Bearer ' . $this->config->internalToken,
                // Quem pergunta: as Tools de leitura seguem a privacidade granular desse usuário.
                'X-Mcp-Usuario' => (string) $usuarioId,
            ],
        ));

        try {
            $tools = $client->listTools()->tools;

            $schemasPorNome = [];
            $functionDeclarations = [];
            foreach ($tools as $tool) {
                $schemasPorNome[$tool->name] = $tool->inputSchema;
                $functionDeclarations[] = [
                    'name'        => $tool->name,
                    'description' => $tool->description ?? '',
                    'parameters'  => $this->sanitizarSchemaParaGemini($tool->inputSchema),
                ];
            }

            $contents = $historico;
            $contents[] = ['role' => 'user', 'parts' => [['text' => $pergunta]]];

            $ferramentasUsadas = [];

            for ($rodada = 0; $rodada < self::MAX_RODADAS_DE_FERRAMENTAS; $rodada++) {
                $response = $this->callGeminiApi([
                    'contents' => $contents,
                    'tools'    => [['functionDeclarations' => $functionDeclarations]],
                    'generationConfig' => ['temperature' => 0.2],
                ]);

                $parts = $response->candidates[0]->content->parts ?? null;

                if ($parts === null) {
                    throw new Exception('Resposta vazia ou inválida do Gemini.');
                }

                $chamadas = array_filter($parts, static fn ($p) => isset($p->functionCall));

                if (empty($chamadas)) {
                    $texto = implode('', array_map(static fn ($p) => $p->text ?? '', $parts));
                    return ['resposta' => trim($texto), 'ferramentas_usadas' => $ferramentasUsadas];
                }

                $contents[] = ['role' => 'model', 'parts' => $parts];

                $respostasFuncao = [];
                foreach ($chamadas as $chamada) {
                    $nome = $chamada->functionCall->name;
                    $argumentos = (array) ($chamada->functionCall->args ?? []);

                    $propriedades = $schemasPorNome[$nome]['properties'] ?? [];
                    if (is_array($propriedades) && isset($propriedades['usuarioId'])) {
                        $argumentos['usuarioId'] = $usuarioId;
                    }

                    $resultado = $client->callTool($nome, $argumentos);
                    $ferramentasUsadas[] = ['nome' => $nome, 'argumentos' => $argumentos];

                    $respostasFuncao[] = [
                        'functionResponse' => [
                            'name'     => $nome,
                            'response' => ['content' => $this->extrairConteudoTexto($resultado)],
                        ],
                    ];
                }

                $contents[] = ['role' => 'function', 'parts' => $respostasFuncao];
            }

            throw new Exception('Número máximo de chamadas a ferramentas MCP excedido sem resposta final.');
        } finally {
            $client->disconnect();
        }
    }

    private function resolverEndpoint(): string
    {
        $base = $this->config->baseUrl !== '' ? $this->config->baseUrl : rtrim(site_url(), '/');

        return $base . '/mcp';
    }

    private function extrairConteudoTexto(CallToolResult $resultado): string
    {
        $texto = $resultado->content[0]->text ?? json_encode($resultado->content);

        return $resultado->isError ? ('Erro na ferramenta: ' . $texto) : $texto;
    }

    /**
     * O SDK MCP produz JSON Schema puro; o Gemini só aceita um subconjunto
     * (tipo OpenAPI). Mantém apenas as chaves compatíveis, recursivamente.
     */
    private function sanitizarSchemaParaGemini(array|\stdClass $schema): array
    {
        $schema = (array) $schema;
        $permitidas = ['type', 'description', 'properties', 'required', 'items', 'enum', 'format'];
        $limpo = array_intersect_key($schema, array_flip($permitidas));

        // Parâmetros opcionais (?int, ?string) viram union type `["null","X"]` no
        // JSON Schema gerado pelo SDK — o Gemini só aceita um `type` escalar.
        if (is_array($limpo['type'] ?? null)) {
            $semNulo = array_values(array_diff($limpo['type'], ['null']));
            $limpo['type'] = $semNulo[0] ?? 'string';
            $limpo['nullable'] = true;
        }

        if (isset($limpo['properties'])) {
            foreach ((array) $limpo['properties'] as $nome => $subSchema) {
                $limpo['properties'][$nome] = $this->sanitizarSchemaParaGemini($subSchema);
            }
        }

        if (isset($limpo['items'])) {
            $limpo['items'] = $this->sanitizarSchemaParaGemini($limpo['items']) ?: ['type' => 'string'];
        }

        return $limpo;
    }
}
