# Inventário de textos fixos do site público

Levantamento da issue #642 (épico #641). Lista o que hoje está escrito no código e para onde vai
na migração para o banco de dados. A classificação segue as quatro categorias do épico:

- **CFG**: configuração do site (`site_configuracoes`, #643)
- **SEC**: conteúdo de página ou seção (`site_paginas` / `site_secoes`, #643)
- **MENU**: item de menu (`site_menus` / `site_menu_itens`, #643)
- **UI**: texto de interface (`textos.*` declarados pelo template, #652)

Este arquivo é a base do seed do template "Clássico" (#646).

## 1. `app/Views/site/layout/main.php` (layout comum)

| Texto / valor | Cat. | Destino proposto |
|---|---|---|
| Título padrão "Cardoso & Bruno Sociedade de Advogados" (`<title>`, `og:title`) | CFG | `seo.titulo_padrao` |
| Descrição padrão "Assessoria de alto padrão especializada em Direito Civil, Administrativo e Contratos Complexos." (`description`, `og:description`) | CFG | `seo.descricao_padrao` |
| Imagem padrão `public/assets/img/logo.png` (`og:image`, logo do menu, logo do rodapé) | CFG | `identidade.logo`, `identidade.logo_rodape`, `seo.og_image` |
| `alt` "Logo Cardoso & Bruno" | CFG | derivado de `identidade.nome` |
| Favicon `public/favicon.ico` | CFG | `identidade.favicon` |
| Fontes Google (Cormorant Garamond, Outfit) e ícones Bootstrap Icons | CFG | `tema.fontes.*` (#651) |
| ID do Google Analytics `G-X56XSQ5ZB6` (script e `gtag('config')`) | CFG | `integracoes.ga_id` |
| Menu do cabeçalho: Áreas de Atuação (`#expertise`), Direito Civil, Direito Administrativo, Notícias, Contato (`#contato`) | MENU | menu `cabecalho` |
| Botão de destaque "Consulta Inicial" (`#contato`) | MENU | item `destaque` do menu `cabecalho` |
| Rodapé, "sobre": "Compromisso com a integridade, excelência técnica e resolução estratégica de litígios complexos. Advocacia de alto padrão corporativo e civil." | CFG | `identidade.sobre_rodape` |
| Rodapé, "Links Úteis" (título) | UI | `textos.rodape.links` |
| Rodapé, links: Início, Áreas de Atuação, Notícias, Contato | MENU | menu `rodape` |
| Rodapé, "Unidades" (título) | UI | `textos.rodape.unidades` |
| Unidade: Belo Horizonte / MG, Rua Roberto Lúcio Aroeira, 417, Sala 01, Bairro Itapoã, CEP 31710-055, Tel (31) 9.9224-6996 | CFG | `contato.enderecos`, `contato.telefone` |
| Copyright "© {ano} Cardoso & Bruno Sociedade de Advogados. Todos os direitos reservados." | CFG + UI | `identidade.nome` + `textos.rodape.copyright` |
| Links "Área do Cliente" e "Painel Administrativo" (rodapé) | UI | `textos.rodape.area_cliente`, `textos.rodape.painel` |
| `aria-label` "Alternar navegação" e demais `title`/`aria-label` | UI | `textos.acessibilidade.*` |

## 2. `app/Views/site/home.php`

| Seção | Texto | Cat. |
|---|---|---|
| Hero | Sobretítulo "Cardoso & Bruno Sociedade de Advogados" | SEC (`hero`) |
| Hero | Título "Defesa Estratégica e *Excelência* Jurídica" (com `<em>`) | SEC (`hero`, `texto_inline`) |
| Hero | Subtítulo "Assessoria de alto padrão especializada em Direito Civil, Administrativo e Contratos Complexos. Soluções jurídicas sob medida para empresas e indivíduos." | SEC (`hero`) |
| Hero | Botões "Iniciar Consulta" (`#contato`) e "Conhecer Áreas" (`#expertise`) | SEC (`hero.botoes`) |
| Pilares | 3 cards (ícone, título, texto): Rigor Técnico, Ética e Integridade, Foco em Resultados | SEC (`pilares`) |
| Áreas | Sobretítulo "Áreas de Prática", título "Especialidades de Atuação" | SEC (`cards_areas`) |
| Áreas | 4 cards (título, texto, link): Direito Civil, Direito Administrativo, Contratos, Advocacia Colaborativa, e o rótulo do link ("Conheça nossa atuação em {área}") | SEC (`cards_areas.itens`) + UI |
| Notícias | "Conteúdo Jurídico", "Artigos Recentes", "Ver todos os artigos jurídicos", "Nenhum artigo publicado no momento.", `aria` "Anterior"/"Próximo" | SEC (`ultimas_noticias`) + UI |
| Contato | Sobretítulo "Contato", título "Inicie sua Consulta Conosco", descrição "Entre em contato para apresentar sua demanda jurídica. Nossa equipe retornará com presteza e o devido sigilo corporativo." | SEC (`contato`) |
| Contato | Rótulos "Nosso Escritório", "WhatsApp / Telefone", "E-mail de Contato" | UI |
| Contato | Endereço, telefone `(31) 9.9224-6996` e e-mail `contato@cardosoebruno.adv.br` repetidos no corpo da página | CFG (`contato.*`, não duplicar) |
| Formulário | Rótulos e placeholders: "Nome completo *", "Seu nome", "Telefone", "(31) 9 0000-0000", "E-mail *", "seu@email.com", "Área de interesse", "Selecione uma especialidade", "Descreva brevemente sua situação *", "Como podemos ajudá-lo?" | UI |
| Formulário | Botão "Enviar Consulta" / "Enviando...", "Enviado com Sucesso!", "Enviar Outra Mensagem", "Mensagem enviada com sucesso!", "Erro ao enviar. Tente novamente." | UI |
| Formulário | Opções de "Área de interesse" | já vêm do banco (`juris_ramos_direito`) |

## 3. Páginas de área

`pagina_civil.php`, `pagina_administrativo.php`, `pagina_contratos.php`, `pagina_colaborativa.php`
têm a mesma estrutura e viram **uma página cada** (`tipo=area`), com as seções abaixo.

| Parte | Cat. | Seção |
|---|---|---|
| Cabeçalho: "Áreas de Prática" + título com `<em>` (Direito *Civil*, Direito *Administrativo*, Contratos & *Negócios*, Advocacia *Colaborativa*) | SEC | `hero_area` |
| Parágrafo de abertura (`lead`) e parágrafo seguinte | SEC | `rich_text` |
| "Nossos serviços em ... incluem:" + lista de 5 a 6 itens (negrito + descrição) | SEC | `lista_destaque` |
| "Nossos diferenciais:" + lista de 5 itens | SEC | `lista_destaque` (marcador dourado) |
| "Áreas de atuação em ...:" + lista (1 ou 2 colunas) | SEC | `lista_duas_colunas` |
| Caixa final com frase + botão "Agende uma Consulta" (`#contato`) | SEC | `cta_box` |
| Barra lateral "Outras Áreas" + 3 links para as demais áreas | MENU | menu `lateral_areas` |
| Metadados `<title>` e `description` de cada área (hoje só no dead code removido nesta issue; ver seção 4) | SEC | campos `meta_title`, `meta_description` da página |

Observações para o seed:

- **`pagina_contratos.php` reaproveita o texto de Direito Civil** (contencioso cível, "litígio cível ou comercial"). Não é conteúdo de contratos. Migrar o texto literal e **marcar para revisão editorial** do escritório.
- Os 4 textos de diferenciais são quase idênticos entre as páginas; no banco continuam como seções independentes por página.

## 4. `app/Controllers/Site.php`

| Valor | Cat. | Destino |
|---|---|---|
| `index()`: title "Cardoso & Bruno Sociedade de Advogados \| Excelência Jurídica", repetido também em `description` e `keywords` (**bug**: a descrição e as palavras-chave são cópia do título) | SEC | página `home`: `meta_title`, `meta_description`, palavras-chave. Corrigir na #647 |
| `pagina()`: mapa `slug → view` (`direito-civil`, `contratos-negocios`, `direito-administrativo`, `advocacia-colaborativa`) | SEC | linhas de `site_paginas` |
| `noticias()`: "Artigos e Notícias \| Cardoso & Bruno" | CFG + SEC | `seo.sufixo_titulo` + página `noticias` (`tipo=sistema`) |
| `noticiaDetail()`: sufixo " \| Cardoso & Bruno" | CFG | `seo.sufixo_titulo` |
| Paginação (9 por página), 3 notícias relacionadas | UI/CFG | parâmetros da seção `ultimas_noticias` |

SEO que existia nos controladores removidos (`Home.php`, `Pagina.php`) e que **não é usado hoje**, mas serve de ponto de partida para o seed:

- Home: "Cardoso & Bruno Sociedade de Advogados: Especialistas em Direito Civil, Administrativo e Contratos. Atendimento estratégico e Advocacia Colaborativa em Belo Horizonte e Juatuba." e palavras-chave (advogado belo horizonte, inventários, divórcios, advocacia juatuba, direito administrativo mg, consultoria jurídica civil, elaboração de contratos, advocacia colaborativa, Cardoso e Bruno Sociedade de Advogados, OAB MG).
- Direito Civil: "Assessoria completa em Direito Civil: obrigações, contratos, responsabilidade civil, família e sucessões com foco em soluções eficientes."
- Direito Administrativo: "Especialistas em Direito Administrativo, licitações, contratos públicos e defesa de interesses perante a administração pública."
- Contratos e Negócios: "Elaboração, análise e revisão de contratos empresariais e civis, garantindo segurança jurídica para seus negócios e parcerias."
- Advocacia Colaborativa: "Resolução de conflitos de forma consensual e humanizada. Advocacia colaborativa para divórcios, inventários e questões empresariais."
- Notícias: "Artigos & Atualizações Jurídicas \| Cardoso & Bruno Sociedade de Advogados".

Recuperáveis pelo histórico do git (`git show <commit anterior a esta issue>:app/Controllers/ApiV2/Site/Home.php`).

## 5. `app/Services/SitemapService.php::generateSitemap()`

URLs fixas: `/`, `/#expertise`, `/pagina/direito-civil`, `/pagina/direito-administrativo`, `/#contato`, `/pagina/contratos-negocios`, `/pagina/advocacia-colaborativa`, mais as notícias publicadas.
Destino: `site_paginas` com `exibir_sitemap=1`, prioridade e `changefreq` por página (#647). As âncoras (`#expertise`, `#contato`) saem do sitemap.

## 6. Telas especiais (UI, #652)

| Arquivo | Textos fixos |
|---|---|
| `noticias_list.php` | "Conteúdo Jurídico", "Artigos & Atualizações", subtítulo "Análise técnica sobre as principais mudanças legislativas e jurisprudenciais do Direito Brasileiro.", botões "Todos / Direito Civil / Administrativo / Contratos", fixos no HTML e que na verdade só preenchem a busca por texto (`search=`); não usam `site_news_categories`, "Nenhum artigo encontrado", "Tente alterar os termos de busca ou filtros.", "Ver Todos os Artigos", "Ler Artigo" |
| `noticias_detail.php` | "Voltar para a listagem", "Publicado em:", "Compartilhar:" (LinkedIn, WhatsApp), "Leia também", "Ler Artigo" |
| `area_cliente.php` | "Área do Cliente", "Acesse seus processos e documentos de forma segura.", "Acesso Seguro", texto do link mágico, "E-mail Cadastrado", "Receber Link Mágico", "Link Enviado!", "Verifique sua caixa de entrada e sua pasta de spam.", "Tentar outro e-mail" |
| `cliente_panel.php` | "Painel do Cliente", "Carregando...", "Meus Processos", "Linha do Tempo", "Mensagens", "Assunto", "Sua mensagem", "Enviar Mensagem", mensagens de lista vazia, "Relatório Final de Encerramento", "Ramo do Direito", "Advogado Responsável", mensagens de erro em JavaScript |
| `app/Views/emails/cliente_magic_link.php` | `alt` "Logo do Escritório"; logo vem de `env('logoEmpresa')` (fora do site, ver #656) |

## 7. Fora do site público (opcional, #656)

`documentos/timbrado.php`, `subestabelecimento.php`, `subestabelecimento_lote.php` (nome do
escritório, telefone, advogado outorgante "Rodrigo Cesar Dias Bruno") e `template/header.php`
(`meta author`/`keywords`).

**Resolvido na #656**: as views leem nome, telefones, site, cidade, logo e cor de `DadosEscritorio`
(`app/Libraries/Site/DadosEscritorio.php`), que usa as configurações do site (`identidade.nome`,
`identidade.nome_curto`, `identidade.logo`, `identidade.cor_documentos`, `contato.telefone`, `contato.telefone2`,
`contato.site`, `contato.enderecos[0].cidade`). O advogado que assina o substabelecimento vem da variável
`$outorgante` (`nome`, `oab`) ou, sem ela, do usuário logado (`username`, `oab_uf`, `oab_numero`). Sem logo configurado,
o timbrado imprime o nome do escritório em texto. Fica de fora o remetente de e-mail (`Config\Email`, `.env`) e o
`noreply@…` de `NotificacaoService`.
