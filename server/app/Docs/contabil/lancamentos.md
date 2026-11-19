---
titulo: Lançamentos contábeis e Livro Diário
resumo: Registro de partidas dobradas, consulta cronológica do Livro Diário e regras de estorno com justificativa.
ordem: 20
telas: [contabil-lancamentos]
palavras_chave: [lançamentos, partidas dobradas, livro diário, débito, crédito, estorno, histórico]
---

## Livro Diário

A aba **Lançamentos** (`#/contabil-lancamentos`) exibe a escrituração cronológica oficial
da clínica, correspondente ao Livro Diário.

Cada lançamento reúne:
- **Número do Lançamento** e data da escrituração;
- **Histórico**: descrição detalhada da operação ou documento contábil;
- **Status**: postado (ativo) ou estornado;
- **Partidas Dobradas**: linhas detalhando conta de débito, conta de crédito e valor.

## Consulta e filtros

Para localizar lançamentos específicos:
- Use a **busca por texto** para pesquisar termos do histórico ou número;
- Filtre por **período** (data inicial e final);
- Filtre por **conta contábil** específica para verificar a movimentação analítica de uma conta;
- Filtre por **tipo** (Normal, Estorno, Abertura ou Encerramento).

## Novo lançamento avulso

Além dos lançamentos automáticos vindos da [Importação financeira](importacao.md), é possível
registrar lançamentos manuais (como provisões, depreciações ou ajustes contábeis):

1. Clique no botão **Novo Lançamento**;
2. Preencha a **Data**, o **Histórico** e a **Origem**;
3. Adicione as pernas de **Débito** e **Crédito**, selecionando as contas analíticas e os valores.
   O lançamento aceita **quantas linhas forem necessárias** (por exemplo, um pagamento com
   vários rateios); **Adicionar linha** já sugere o lado e o valor que faltam para fechar, e cada
   linha pode ter um histórico próprio;
4. Verifique o indicador de balanceamento: o botão de gravação só é liberado quando a soma
   dos débitos for exatamente igual à soma dos créditos;
5. Clique em **Confirmar Lançamento**.

Para lançar os saldos iniciais de um novo exercício, use **Abertura contábil**
(veja [Abertura contábil](abertura.md)).

## Regras de estorno

Em obediência às normas contábeis brasileiras (NBC TG) e ao princípio da imutabilidade:

- **Não é permitido editar ou apagar** um lançamento contábil já registrado no Livro Diário;
- Caso um lançamento precise de correção, utilize o botão **Estornar** na linha correspondente;
- Informe a **Justificativa** detalhada do estorno (obrigatória para fins de auditoria);
- O sistema gerará automaticamente um novo lançamento com as posições invertidas de débito
  e crédito, vinculando o estorno ao lançamento original.

> [!DICA]
> Para consultar a movimentação agrupada e o saldo acumulado de uma conta contábil ao longo
> do tempo, utilize o Livro Razão em [Demonstrações e relatórios](relatorios.md).
