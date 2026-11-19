# Notificações push (PWA)

O painel (`sistema`) avisa no celular ou no computador quando o
**prazo de uma tarefa** do usuário está vencendo, mesmo com o app fechado. É Web Push padrão
(RFC 8030/8291/8292), implementado em PHP puro em `app/Libraries/WebPush` (só `ext-openssl` e
`ext-curl`, sem dependência nova no Composer).

## Ativação

1. Rode a migration: `php spark migrate` (cria `push_inscricoes` e `push_envios`).
2. Gere as chaves VAPID **uma vez** e copie a saída para o `.env`:
   ```bash
   php spark push:vapid
   ```
   ```ini
   push.vapidPublicKey = "B..."
   push.vapidPrivateKey = "..."      # segredo: nunca versionar
   push.contato = "mailto:ti@exemplo.com.br"
   ```
   Trocar as chaves invalida todos os aparelhos inscritos (cada usuário precisa ativar de novo).
3. Agende o envio no cron:
   ```cron
   0,15,30,45 * * * * cd /caminho/do/sistema && php spark push:notificar
   ```

Sem as chaves, `GET api/v2/push/config` responde `habilitado: false` e o painel mostra
"ainda não configuradas no servidor".

## Quem recebe o quê

| Aviso | Destinatários | Quando |
|---|---|---|
| Prazo de tarefa | Responsável pela tarefa, com `tarefas.access`, módulo `tarefas` ativo e "Prazos" ligado | Tarefa em aberto (status 1, 2 ou 3) com prazo hoje ou amanhã (`diasAntecedenciaPrazo`) |

- Vários prazos na mesma execução viram **um aviso só** por usuário.
- Cada prazo é avisado **uma única vez** por usuário (`push_envios`). Se o prazo da tarefa
  mudar, o novo prazo é avisado de novo. O histórico com mais de 90 dias é apagado.
- Fora do horário (`horaInicio`–`horaFim`, padrão 7h–22h) nada é enviado; o pendente sai na primeira
  execução dentro do horário.
- O conteúdo vai cifrado de ponta a ponta (só o navegador decifra) e traz apenas o tipo do aviso
  e o título da tarefa.
- Resposta `404/410` do serviço de push apaga a inscrição; outras falhas contam até `maximoFalhas` (5).
  Se nenhum aparelho do usuário recebeu, o aviso fica pendente para a próxima execução.

Os parâmetros ficam em `app/Config/Push.php` e podem ser sobrescritos no `.env` (`push.horaInicio = 8`).

## API (`api/v2/push`, filtro `auth`, sempre sobre o usuário logado)

| Método e rota | Corpo / parâmetros | Resposta |
|---|---|---|
| `GET config` | `?endpoint=` (opcional, do aparelho atual) | `{ habilitado, chavePublica, inscricao: {prazos} \| null }` |
| `POST inscricao` | `{ subscription: PushSubscriptionJSON, preferencias?: {prazos} }` | preferências gravadas. `503` sem chaves; `400` inscrição inválida |
| `POST inscricao/remover` | `{ endpoint }` | `{ success }` (só remove inscrição do próprio usuário) |
| `POST teste` | — | envia "Notificações ativadas" a todos os aparelhos do usuário; `422` se nenhum recebeu |

O endpoint da inscrição precisa ser `https` e não pode apontar para `localhost`, `.local` ou IP privado
(o servidor faz POST para ele). Um mesmo navegador pertence a um usuário por vez: se outra pessoa
entrar nele e ativar, a inscrição passa para ela. O painel também desvincula o aparelho ao clicar em **Sair**.

## Mensagem (JSON cifrado)

```json
{ "title": "Prazo hoje", "body": "Retornar ligação ao paciente",
  "url": "#/tarefas", "tag": "prazos", "timestamp": 1793700000000 }
```

`url` é sempre uma rota do painel (`#/...`); o service worker ignora qualquer outro destino.

## Limitações conhecidas

- iPhone/iPad: só com o app instalado na Tela de Início (iOS 16.4+).
- Firefox e Chrome no desktop só exibem o aviso com o navegador aberto (pode estar minimizado).
