---
titulo: Documentos e relatórios
resumo: Anexar sentença, cálculos e outros arquivos no Google Drive e gerar o PDF da memória de cálculo ou a planilha.
ordem: 40
palavras_chave: [documento, anexo, sentença, cálculos, comprovante, google drive, PDF, planilha, XLSX, CSV, Excel, exportar, memória de cálculo]
---

## Documentos

O quadro **Documentos**, no [detalhe da liquidação](detalhe.md), guarda os arquivos que fundamentam o cálculo.

1. Escolha o **Tipo**: **Sentença**, **Cálculos** ou **Outros**.
2. Clique em **Enviar arquivos** e selecione os arquivos.

São aceitos PDF, Word, JPG, PNG e WEBP, com os mesmos limites de tamanho e de quantidade por envio dos arquivos de
contratos; a tela mostra os limites ao lado do botão. Os arquivos vão para a pasta do cliente no Google Drive,
na subpasta **Liquidações**.

Cada documento aparece com o nome (clique para abrir), o tipo e a data do envio. Os comprovantes anexados na
[baixa de parcelas](parcelas.md#baixar-uma-parcela) também aparecem aqui, com o tipo **Comprovante**.

A lixeira exclui o documento depois de confirmar, e o arquivo vai para a lixeira do Google Drive. Enviar e
excluir exigem a permissão `liquidacoes.edit`.

> [!NOTA]
> O processo precisa ter um cliente. Se o cliente ainda não tem pasta no Google Drive, ela é criada no primeiro
> envio, desde que a integração com o Google esteja configurada. Veja
> [Parâmetros e manutenção](../administracao/configuracoes.md#integracao-google).

## Memória de cálculo em PDF

No topo do detalhe, **Gerar PDF** abre a memória de cálculo pronta para juntar ao processo, em A4 paisagem. Ela
traz o processo, as partes, a data-base, os critérios usados e, para cada item e competência, o valor original, o
fator, o valor corrigido, os juros e o subtotal, além dos abatimentos e do total.

## Planilha

**Planilha (XLSX)** e **CSV** exportam as mesmas linhas do PDF para conferir ou ajustar no Excel. O CSV usa
ponto e vírgula e vírgula decimal e abre direto no Excel em português.

> [!DICA]
> Recalcule antes de gerar o PDF ou a planilha. Os relatórios mostram o que foi gravado no último cálculo, e os
> itens manuais saem como "valor informado manualmente".

## Outros lugares onde o valor aparece

- **Detalhe do processo**: o cartão **Liquidação** mostra a situação, o valor e o saldo de cada liquidação.
- **Estratégia Legal & BI**: quando a liquidação mais recente está **Apresentada**, **Impugnada**,
  **Homologada** ou **Em pagamento**, o valor atualizado substitui o valor da causa na provisão e na exposição;
  **Quitada** conta zero. Veja [Estratégia Legal & BI](../gestao/legal-ops.md).
