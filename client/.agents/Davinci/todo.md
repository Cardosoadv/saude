# 🎨 Davinci - Feature Discovery Backlog

## New Feature Proposals (2026-08-25) 🆕
- **Contract Renewal Alerts Panel**: `ContractList.vue`/`ContractDetail.vue` display contract data but surface no expiration/renewal signal. Add an "Alertas de Vencimento" widget (dashboard + `ContractList.vue`) with badges for contracts nearing `data_fim`/`dia_vencimento`, and a renewal action wired to the backend's new renewal-alert endpoint.
    - *Why*: Users have no visual cue today that a contract is about to expire or auto-renew, so risk sits silently in a table column instead of surfacing where lawyers actually look.
- [x] **AI Message Triage UI**: `MensagensList.vue`/`MensagemDetail.vue` render messages with no urgency/sentiment signal or reply assistance, unlike movement/publication views which already show AI-generated summaries. Add urgency badges (Urgente/Normal/Baixa) to the list and an editable "Sugestão de Resposta IA" panel to the detail view, backed by the new `classifyMessageUrgency`/`draftMessageReply` endpoints.
    - *Why*: Lawyers currently scan the inbox manually with no way to prioritize; a visible urgency signal and a draft-to-edit reply directly cut response time on client messages.
- **Cash Flow Forecast Dashboard**: The `Financeiro` views (`BalancoView.vue`, `DreView.vue`, `ExtratoList.vue`, `FechamentoMesView.vue`, `RelatorioCategorias.vue`) cover historical statements but no forward-looking view. Add a new `PrevisaoFluxoCaixaView.vue` with a projected-vs-actual balance chart and an anomaly alerts list, consuming the new cash-flow forecast endpoint.
    - *Why*: The firm can see where its money has been but not where it is heading, and unusual expense/revenue entries pass unnoticed without a dedicated view.
