---
titulo: Importar processos em lote
resumo: Trazer vários processos para a carteira a partir de um arquivo ou de uma pesquisa no DJEN pelo nome da parte, conferindo os dados antes de gravar.
ordem: 45
telas: [importacao-processos]
palavras_chave: [importar, lote, CSV, JSON, planilha, DJEN, DataJud, conferência, divergência, carteira, nome da parte, pesquisar parte, contrato, etiquetas]
---

Em **Processos › Importar em Lote** você cadastra de uma vez até **50 processos**, de duas formas:

- **Enviar arquivo**: um **CSV** ou **JSON** com os números dos processos;
- **Pesquisar no DJEN pelo nome da parte**: o sistema procura as publicações em que a parte aparece e você
  escolhe quais processos trazer.

Nos dois casos, antes de gravar, o sistema consulta cada número no **DJEN** e no **DataJud** e mostra uma
tela de conferência; ao clicar em **Importar selecionados**, uma última tela resume o que será gravado e só
então você confirma.

## Pesquisar no DJEN pelo nome da parte

Informe o **nome da parte** (pessoa ou empresa, pelo menos 4 letras), o **período das publicações** (padrão:
último ano) e, se quiser, a **sigla do tribunal** (ex.: TJSP, TRT2). A lista mostra um processo por linha, com
tribunal, órgão, classe, a parte encontrada e o polo dela, as demais partes, o número de publicações e se o
processo é **Novo**, **Já cadastrado** ou **Excluído da carteira**.

- O DJEN também encontra nomes que apenas **contêm** o termo (ex.: "Maria Juraci da Silva" ao pesquisar
  "Juraci da Silva"). Esses aparecem com o selo **Nome parecido**, depois dos idênticos. Marque **Só nome
  idêntico** para escondê-los, e **Ocultar já cadastrados** para ver só os novos.
- Só aparecem processos que tiveram publicação no período. Para nomes muito comuns, o DJEN pode trazer mais
  publicações do que é possível ler de uma vez (até 500 por pesquisa); o aviso pede para reduzir o período ou
  informar o tribunal.
- Marque os processos (ou use **Selecionar os exibidos**) e clique em **Conferir selecionados**. Dá para
  levar até 50 por vez; depois de gravar, **Nova importação** volta à lista para escolher outros.
- A pesquisa não grava nada nem importa intimações.

## O arquivo

Só a coluna `numero_processo` é obrigatória. As demais são opcionais e completam o que as consultas não trazem:

| Coluna | Observação |
|---|---|
| `numero_processo` | número CNJ, com ou sem máscara |
| `titulo_processo` | se ficar vazio, o título é "Cliente - Classe da ação"; sem cliente ou classe, "Processo sem título" |
| `cliente_documento`, `cliente_nome` | o cliente é localizado pelo CPF/CNPJ (prioridade) ou pelo nome; nenhum cliente novo é criado |
| `tribunal`, `orgao`, `classe`, `codigo_classe`, `data_distribuicao` | data em dd/mm/aaaa |
| `valor_causa`, `risco`, `pasta`, `comentario` | risco: Provável, Possível ou Remoto |
| `etiquetas` | várias separadas por `\|`; etiquetas que não existem são criadas |
| `responsavel`, `visibilidade`, `ramo_direito` | responsável pelo nome de usuário; visibilidade `publico` ou `restrito` |
| `polo_ativo`, `polo_passivo` | várias partes separadas por `\|` |

O botão **Baixar modelo CSV** traz todas as colunas. Linhas com número inválido ou repetido aparecem no topo e
não são importadas. Valores que não puderam ser lidos (uma data inválida, por exemplo) são ignorados com aviso.

## A conferência

Cada processo aparece em um cartão. A tabela mostra, campo a campo, o valor do **Arquivo**, do **DJEN**, do
**DataJud** e, quando o processo já existe, da **Carteira**. A coluna **Valor a gravar** traz uma sugestão
que você pode trocar escolhendo uma das fontes ou digitando outro valor.

- **Divergências** (fontes com valores diferentes) ficam destacadas em amarelo. A importação só é liberada
  depois que cada uma é resolvida: escolha uma das fontes (inclusive a que já está marcada), digite outro
  valor ou clique em **Confirmar este valor** para manter a sugestão. **Aceitar sugestões**, no cartão,
  confirma as divergências daquele processo; **Aceitar todas as sugestões**, acima da lista, confirma as de
  todo o lote.
- **Partes** vêm do arquivo e do DJEN (o DataJud não informa partes). Desmarque as que não quiser incluir.
- **Cliente**: o sistema compara as partes com os clientes cadastrados e sugere os parecidos, com o grau de
  confiança. A sugestão de confiança alta já vem escolhida; as demais você escolhe se quiser. Também dá para
  buscar outro cliente: digite parte do nome ou do documento e escolha na lista (setas e Enter funcionam).
  O cliente não é obrigatório.
- **Contrato**: busque o contrato pelo número ou pelo cliente. Se o contrato for de outro cliente, o cartão
  avisa (mas não impede a gravação). O contrato não é obrigatório.
- **Aplicar a todos os processos**: no quadro acima da lista, escolha o **cliente**, o **contrato** e as
  **etiquetas** (digite e tecle Enter; as que não existem são criadas) e clique em **Aplicar ao lote**. Cliente
  e contrato valem para todos os processos ou só para os que estão sem eles; as etiquetas são sempre
  acrescentadas, sem remover as atuais. Processos ainda em consulta recebem os valores quando a consulta
  termina, e cada cartão continua podendo trocar os seus.
- **Processos com sigilo** costumam trazer só as iniciais das partes. Nesse caso as sugestões de cliente
  comparam as iniciais e têm confiança baixa: confira antes de escolher.
- **Processos já cadastrados** também passam pela conferência. Só os campos que você mudar são gravados,
  as partes novas são acrescentadas e nenhuma parte é removida. Responsável e visibilidade não mudam pela
  importação: altere-os na tela do processo.
- Se o DJEN ou o DataJud não responderem, o cartão mostra a falha e o botão de recarregar consulta de novo.

As intimações encontradas no DJEN **não** são importadas: a sincronização normal traz as próximas.

## Situações

| Situação | Significado |
|---|---|
| **Novo** | será criado na carteira |
| **Já cadastrado** | será atualizado só nos campos alterados |
| **Excluído da carteira** | restaure o processo antes de importar |
| **Sem permissão** | o processo existe, mas você não pode alterá-lo |

## Confirmação

**Importar selecionados** abre a tela de confirmação, com cada processo que será **criado** ou **atualizado**,
o título, o cliente, o contrato, as etiquetas e quantas partes novas entram, além de um aviso de quantos
ficarão sem cliente ou sem contrato. **Voltar e revisar** fecha a tela sem gravar; **Confirmar e gravar**
grava.

Ao final aparece o resumo com os processos criados, atualizados e com falha, e o link para cada um.

## Pela IA (MCP)

A ferramenta **adicionar_processo** do MCP faz a mesma pesquisa para um processo só: informe o número CNJ (e, se quiser, cliente, título, pasta, risco, etiquetas e partes). Ela aceita as sugestões da conferência — dados oficiais do DataJud e do DJEN, cliente sugerido com confiança alta e título "Cliente - Classe da ação" — e grava. Em processo já cadastrado, só completa o que está vazio e acrescenta partes e etiquetas novas. Com `simular` ela mostra o que seria gravado sem gravar.
