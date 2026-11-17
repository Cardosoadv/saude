# Notificações push (PWA)

O painel (`sistema`) avisa no celular ou no computador quando chega uma **intimação nova** e quando o
**prazo de uma tarefa** do usuário está vencendo, mesmo com o app fechado. É Web Push padrão
(RFC 8030/8291/8292), implementado em PHP puro em `app/Libraries/WebPush` (só `ext-openssl` e
`ext-curl`, sem dependência nova no Composer).

## Ativação por tenant

1. Rode a migration: `php spark migrate --all` (cria `push_inscricoes` e `push_envios`).
2. Gere as chaves VAPID **uma vez** e copie a saída para o `.env` do tenant:
   ```bash
   php spark push:vapid
   ```
   ```ini
   push.vapidPublicKey = "B..."
   push.vapidPrivateKey = "..."      # segredo: nunca versionar
   push.contato = "mailto:ti@cardosoebruno.adv.br"
   ```
   Trocar as chaves invalida todos os aparelhos inscritos (cada usuário precisa ativar de novo).
3. Agende o envio no cron, de preferência logo depois da captura de intimações (`sync:djen`, ver `docs/sync-djen-cron.md`):
   ```cron
   0,15,30,45 * * * * cd /caminho/do/tenant && php spark push:notificar
   ```

Sem as chaves, `GET api/v2/push/config` responde `habilitado: false` e o painel mostra
"ainda não configuradas no servidor".

## Quem recebe o quê

| Aviso | Destinatários | Quando |
|---|---|---|
| Intimação nova | Usuários com `intimacoes.access`, módulo `intimacoes` ativo e "Intimações" ligado no aparelho | Intimação **não tratada** que chegou depois da inscrição do aparelho e nas últimas `janelaIntimacoesHoras` (48 h) |
| Prazo de tarefa | Responsável pela tarefa, com `tarefas.access`, módulo `tarefas` ativo e "Prazos" ligado | Tarefa em aberto (status 1, 2 ou 3) com prazo hoje ou amanhã (`diasAntecedenciaPrazo`) |

- Várias intimações ou prazos na mesma execução viram **um aviso só** por usuário.
- Cada intimação/prazo é avisado **uma única vez** por usuário (`push_envios`). Se o prazo da tarefa
  mudar, o novo prazo é avisado de novo. O histórico com mais de 90 dias é apagado.
- Fora do horário (`horaInicio`–`horaFim`, padrão 7h–22h) nada é enviado; o pendente sai na primeira
  execução dentro do horário.
- O conteúdo vai cifrado de ponta a ponta (só o navegador decifra) e traz apenas o número do processo,
  o tipo de comunicação ou o título da tarefa. **O texto da intimação nunca é enviado.**
- Resposta `404/410` do serviço de push apaga a inscrição; outras falhas contam até `maximoFalhas` (5).
  Se nenhum aparelho do usuário recebeu, o aviso fica pendente para a próxima execução.

Os parâmetros ficam em `app/Config/Push.php` e podem ser sobrescritos no `.env` (`push.horaInicio = 8`).

## API (`api/v2/push`, filtro `auth`, sempre sobre o usuário logado)

| Método e rota | Corpo / parâmetros | Resposta |
|---|---|---|
| `GET config` | `?endpoint=` (opcional, do aparelho atual) | `{ habilitado, chavePublica, inscricao: {intimacoes, prazos} \| null }` |
| `POST inscricao` | `{ subscription: PushSubscriptionJSON, preferencias?: {intimacoes, prazos} }` | preferências gravadas. `503` sem chaves; `400` inscrição inválida |
| `POST inscricao/remover` | `{ endpoint }` | `{ success }` (só remove inscrição do próprio usuário) |
| `POST teste` | — | envia "Notificações ativadas" a todos os aparelhos do usuário; `422` se nenhum recebeu |

O endpoint da inscrição precisa ser `https` e não pode apontar para `localhost`, `.local` ou IP privado
(o servidor faz POST para ele). Um mesmo navegador pertence a um usuário por vez: se outra pessoa
entrar nele e ativar, a inscrição passa para ela. O painel também desvincula o aparelho ao clicar em **Sair**.

## Mensagem (JSON cifrado)

```json
{ "title": "Nova intimação", "body": "Processo 0001234-56.2026.8.26.0100 — Intimação",
  "url": "#/intimacoes/detalhes/10", "tag": "intimacoes", "timestamp": 1793700000000 }
```

`url` é sempre uma rota do painel (`#/...`); o service worker ignora qualquer outro destino.

## Limitações conhecidas

- iPhone/iPad: só com o app instalado na Tela de Início (iOS 16.4+).
- Firefox e Chrome no desktop só exibem o aviso com o navegador aberto (pode estar minimizado).
