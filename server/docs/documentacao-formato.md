# Documentação interna: formato das páginas

Formato das páginas do manual do Ordo Juris, lido dentro do próprio sistema (épico #699, issue #700).
O backend converte o Markdown em HTML e a SPA só exibe o resultado.

## Onde ficam

```
app/Docs/
  primeiros-passos/        # uma pasta por seção
    index.md               # capa da seção (opcional)
    navegacao.md           # página
    img/                   # imagens da seção
      menu-lateral.webp
  processos/
    index.md
    cadastro.md
```

- Só existem **dois níveis**: seção (pasta) e página (arquivo `.md`). Subpastas, além de `img/`, não são lidas.
- Nomes de pasta e de arquivo: minúsculas, números e hífen (`busca-automatica.md`).
- O **slug** vem do caminho, sem `.md`: `processos/cadastro.md` vira `processos/cadastro`.
  A capa `processos/index.md` vira `processos`.
- Seção sem `index.md` aparece no índice com o nome da pasta, e as páginas dela continuam acessíveis.

**Páginas criadas pela interface**: quem tem a permissão `documentacao.editar` também cria e edita páginas pela
tela de edição da documentação. Elas ficam no banco, seguem este mesmo formato e, depois de publicadas,
substituem o arquivo de mesmo slug. O arquivo continua sendo a versão oficial versionada: para incorporar uma
mudança feita pela interface, copie o texto para o `.md` e exclua a página do banco.

## Cabeçalho (frontmatter)

Toda página começa com um cabeçalho entre linhas `---`:

```markdown
---
titulo: Cadastro de processos
resumo: Como cadastrar um processo, preencher as partes e vincular o cliente.
ordem: 10
modulo: processos
permissao: processos.access
telas: [list, process-form]
palavras_chave:
  - novo processo
  - CNJ
---

Texto da página em Markdown...
```

| Campo | Obrigatório | Regra |
|---|---|---|
| `titulo` | sim | até 120 caracteres |
| `resumo` | não | até 300 caracteres; aparece no índice, na busca e na ajuda contextual |
| `ordem` | não | inteiro de 0 a 9999, padrão 100; menor aparece primeiro (empate: título) |
| `modulo` | não | slug do catálogo `ModulosSeeder::CATALOGO`; com o módulo desativado a página some |
| `permissao` | não | permissão do Shield (`escopo.acao`); sem ela o usuário não vê a página |
| `telas` | não | lista de `currentView` da SPA (`list`, `cliente-detail`...) que abrem esta página na ajuda contextual |
| `palavras_chave` | não | lista de termos extras para a busca, até 60 caracteres cada |

Campo fora da tabela é erro. A validação oficial é `App\Libraries\Documentacao\Frontmatter`;
[`documentacao-pagina.schema.json`](documentacao-pagina.schema.json) descreve o mesmo formato para editores e IDEs.

**Herança da capa**: uma página sem `modulo` ou sem `permissao` usa o valor da capa (`index.md`) da sua seção.
Assim, basta declarar `modulo: financeiro` na capa de `financeiro/`.

**Sintaxe aceita** (subconjunto de YAML, lido sem biblioteca):

- `chave: valor`: texto com ou sem aspas (`"..."` ou `'...'`), ou inteiro
- lista em linha: `telas: [list, detail]`
- lista em bloco: `chave:` seguido de linhas `  - item`
- linha iniciada por `#` é comentário

## Corpo em Markdown

CommonMark com as extensões do GitHub: tabelas, listas de tarefas, tachado e links automáticos.

- **Títulos**: o título da página vem do cabeçalho. No corpo, use `##` e `###`; eles formam o sumário lateral.
  Evite `#` no corpo.
- **HTML cru não é aceito**: aparece como texto. O HTML gerado passa por uma lista branca de tags.
- **Links para outra página**: use o caminho relativo do arquivo `.md`, com âncora opcional:
  `[cadastro](../processos/cadastro.md#partes)`. O backend reescreve para `#/documentacao/processos/cadastro#partes`.
- **Links para telas do sistema**: use o hash da SPA, como `[abrir processos](#/processos)`.
- **Links externos**: só `https://` e `mailto:`.
- **Imagens**: guarde em `img/` da seção e use caminho relativo: `![Menu lateral aberto](img/menu-lateral.webp)`.
  O texto alternativo é obrigatório. Prefira WebP, com no máximo 1600 px de largura; o backend põe `width` e `height`
  lidos do arquivo. Como produzir as capturas: [`documentacao-capturas.md`](documentacao-capturas.md).
- **Avisos**: um bloco de citação cuja primeira linha é o marcador.

```markdown
> [!NOTA]
> Informação complementar.

> [!DICA]
> Atalho ou boa prática.

> [!ATENCAO]
> Algo que pode causar perda de dados ou prazo.
```

## Verificação

`php spark docs:verificar` confere todas as páginas e sai com erro se encontrar problema. Ele também roda no CI
(workflow `tests-documentacao.yml`). O comando acusa:

- cabeçalho inválido, nome de pasta ou arquivo fora do padrão, `modulo` fora do catálogo;
- `.md` fora de `<secao>/<pagina>.md` (seria ignorado sem aviso);
- link para página ou âncora inexistente, link em formato não aceito;
- imagem fora de `<secao>/img/`, inexistente, sem texto alternativo ou com mais de 1600 px de largura;
- a mesma tela em `telas` de mais de uma página (a ajuda contextual ficaria ambígua).

Use `--raiz <pasta>` para conferir outra pasta.

## Estilo de escrita

- Escreva para o usuário do escritório, não para o desenvolvedor: diga onde clicar e o que acontece.
- Frases curtas, na segunda pessoa ("clique em **Salvar**"). Nomes de botões e menus em **negrito**.
- Uma página por tarefa. Se passar de umas três telas de texto, divida.
- Capturas de tela só com dados fictícios. **Nunca** use dados reais de clientes ou processos.
