---
titulo: Usuários e grupos
resumo: Cadastrar usuários, definir grupos de acesso, permissões específicas, registro profissional e rateio.
ordem: 10
palavras_chave: [usuário, senha, esqueci minha senha, grupo, permissão, perfil, registro profissional, rateio, profissional, responsável, tarefas, quem somos, equipe]
---

Em **Configurações › Usuários** ficam todos os usuários, com busca por nome ou e-mail.
Só administradores criam e alteram usuários.

## Cadastrar ou editar

Clique em **Novo Usuário**, ou no lápis ao lado de um usuário. O formulário tem duas abas.

### Dados Gerais

- **Foto**: clique na câmera (JPG ou PNG, até 2 MB).
- **Usuário Ativo**: desmarque para bloquear o acesso sem apagar o cadastro.
- **Participa do Rateio**: o usuário aparece nos repasses de receitas e despesas entre sócios.
- **Responsável por Tarefas**: só quem estiver marcado aparece na busca de responsável ao criar ou editar uma tarefa. Usuários que já existiam no sistema vêm marcados; os novos, também, mas você pode desmarcar. Tarefas antigas continuam com o responsável que já tinham.
- **Exibir em Quem Somos**: o usuário passa a integrar a equipe da página **Quem Somos** do site, com a foto e os dados profissionais do perfil. Ao marcar, aparecem os campos **Cargo**, **Ordem na equipe**, **Formação**, **Especializações**, **Áreas de atuação**, **Lattes**, **LinkedIn** e **Minicurrículo**.
- **Profissional**: exige o **registro no conselho** (CRM, CRO, CRMV...) e a **UF**. Em Quem Somos o registro aparece junto do nome.
- **Nome de Usuário** e **E-mail**: usados para entrar no sistema.
- **Senha** e **Confirmar Senha**: mínimo de 8 caracteres. Ao editar, deixe em branco para manter a senha atual.

### Grupos & Permissões

- **Grupos de Acesso (Perfil)**: o usuário recebe as permissões de **todos** os grupos marcados.
- **Permissões Granulares Específicas**: permissões extras só para este usuário, além das dos grupos.

## Perfil, senha e "esqueci minha senha"

Cada usuário cuida do próprio cadastro pelo menu da foto, no canto superior direito:

- **Perfil**: altera nome de usuário, e-mail, foto e a apresentação profissional (cargo, formação, áreas, minicurrículo, Lattes e LinkedIn). Também troca a senha, informando a senha atual. Grupos, permissões e as marcações acima só o administrador altera.
- **Configurações**: atalho para a tela de configurações do sistema.
- **Sair**: encerra a sessão.

Quem esqueceu a senha clica em **Esqueci minha senha** na tela de entrada e informa o e-mail. Se ele estiver cadastrado e o usuário ativo, chega um link para criar a nova senha. O link vale por **2 horas** e só funciona uma vez. A resposta na tela é sempre a mesma, exista o e-mail ou não. Ao redefinir, os acessos com "Lembrar de mim" são encerrados.

## Quem Somos

A seção **Equipe** da página Quem Somos usa, nesta ordem: os membros cadastrados na própria seção (editor do site); se não houver, os usuários marcados com **Exibir em Quem Somos**; se também não houver, a lista de profissionais das configurações do site. Só usuários **ativos** aparecem, e só a foto deles fica pública.

## Grupos padrão

| Grupo | Para quem |
|---|---|
| Super Admin | controle total do sistema, inclusive templates do site |
| Admin | administradores do dia a dia da clínica |
| Developer | equipe técnica que mantém o sistema |
| Profissional | médicos, dentistas, veterinários e demais profissionais de saúde |
| Recepção | atendimento: clientes, tarefas e financeiro básico |
| User | acesso mínimo (somente a documentação) |
| Cliente | só a Área do Cliente, fora deste sistema |

> [!NOTA]
> Uma tela só aparece no menu quando o usuário tem a permissão **e** o módulo está ativo.
> Veja [Módulos](modulos.md).

## Excluir

Use a lixeira ao lado do usuário. Para apenas suspender o acesso, prefira desmarcar **Usuário Ativo**.
