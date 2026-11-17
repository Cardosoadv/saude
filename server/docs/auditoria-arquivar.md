# Arquivamento mensal da auditoria (`auditoria:arquivar`)

A tabela `auditoria` cresce a cada alteração feita no sistema. O comando `php spark auditoria:arquivar` salva os
registros em um backup compactado, confere o arquivo, apaga da tabela o que foi salvo e roda `OPTIMIZE TABLE`
para devolver o espaço.

## Uso

```bash
php spark auditoria:arquivar                    # arquiva tudo
php spark auditoria:arquivar --manter-dias 30   # mantém na tabela os últimos 30 dias
php spark auditoria:arquivar --simular          # só conta; não grava nem apaga
php spark auditoria:arquivar --dir /outra/pasta # pasta do backup (padrão: writable/backups/auditoria)
php spark auditoria:arquivar --sem-otimizar     # pula o OPTIMIZE TABLE
```

## Crontab (um por tenant)

```cron
# dia 1º de cada mês, às 03:00
0 3 1 * * cd /caminho/do/tenant && php spark auditoria:arquivar >> writable/logs/auditoria-arquivar.log 2>&1
```

## Como funciona e garantias

1. Exporta os registros (em lotes de 1.000) para `auditoria_AAAAMMDD_HHMMSS.jsonl.gz`: uma linha JSON por registro,
   com todas as colunas.
2. Relê o arquivo e confere a contagem de linhas e o último `id`. O arquivo só ganha o nome final depois disso e
   ganha um `.sha256` ao lado.
3. **Só então** apaga os registros exportados (até o maior `id` exportado). O que for gravado durante a execução
   fica na tabela.
4. Roda `OPTIMIZE TABLE auditoria` (só em MySQL/MariaDB; em outros bancos é ignorado).

Se qualquer etapa antes do passo 3 falhar, nada é apagado e o comando sai com código 1. Uma trava
(`writable/cache/auditoria_arquivar.lock`) impede duas execuções simultâneas.

## Atenção

- O backup fica no mesmo servidor, em `writable/backups/auditoria` (ignorado pelo Git). Copie esses arquivos para
  fora do servidor (Drive, rsync etc.) se precisar de cópia externa. O arquivo contém dados antigos/novos dos
  registros, portanto trate-o como dado sensível.
- Os backups antigos não são apagados pelo comando.
- Outras tabelas (`auditoria_processo`, `auditoria_cliente`, `auditoria_intimacao`) não são afetadas.
- Para consultar um backup: `zcat auditoria_20261201_030000.jsonl.gz | head`.
