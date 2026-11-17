# Atualização das intimações por cron (`sync:djen`)

O comando `php spark sync:djen` consulta o DJEN (CNJ) para cada advogado com OAB e UF cadastradas e grava as
intimações novas. É seguro rodar várias vezes: comunicações já gravadas são ignoradas.

## Uso

```bash
php spark sync:djen                      # últimos 2 dias, todos os advogados com OAB/UF
php spark sync:djen --days 7             # últimos 7 dias (1 a 30)
php spark sync:djen --oab 12345 --uf SP  # só uma OAB (informe --oab e --uf juntos)
```

## Crontab (um por tenant)

```cron
# captura de intimações a cada 30 minutos
0,30 * * * * cd /caminho/do/tenant && php spark sync:djen --days 2 >> writable/logs/sync-djen.log 2>&1
# avisos push logo depois (ver docs/notificacoes-push.md)
5,35 * * * * cd /caminho/do/tenant && php spark push:notificar
```

Para recuperar um período perdido (servidor parado, por exemplo), rode uma vez com `--days` maior.

## Comportamento

- **Sem sobreposição**: um arquivo de trava (`writable/cache/sync_djen.lock`) impede duas execuções ao mesmo tempo.
  Se a anterior ainda estiver rodando, a nova avisa, registra no log e sai com código 0.
- **Mesma OAB em dois usuários** é consultada uma vez só.
- **Falha em uma OAB não interrompe as outras**; ao final o comando resume o que foi encontrado, o que era novo e
  quantas OABs falharam.
- Limite de requisições da API (HTTP 429): o serviço espera cerca de 1 minuto e tenta de novo.
- O JSON bruto de cada página fica em `writable/jsons/Intimacoes/AAAA/MM/DD/`.

## Código de saída

| Código | Significado |
|---|---|
| `0` | Tudo certo (ou outra execução já estava em andamento) |
| `1` | Alguma OAB falhou, nenhum advogado com OAB/UF cadastrada, ou `--days`/`--oab`/`--uf` inválidos |

Use o código de saída para alertas (por exemplo, `|| curl -fsS https://seu-monitor/falha` no fim da linha do cron).
Os erros também vão para o log da aplicação (`writable/logs/`).
