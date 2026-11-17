/**
 * Tipos de domínio compartilhados pelos componentes.
 *
 * Os campos foram levantados a partir do uso real nos componentes. O backend
 * (PHP) ora devolve `id`, ora `id_<entidade>`, e IDs/flags podem chegar como
 * número ou string — por isso vários campos são opcionais ou `Id`.
 */

/** ID vindo do backend: número ou string numérica. */
export type Id = number | string

export interface Etiqueta {
  id?: Id
  id_etiqueta?: Id
  nome: string
  /** Cor em hexadecimal, sem `#`. */
  cor: string
}

export interface Anotacao {
  id?: Id
  id_anotacao?: Id
  titulo: string
  anotacao?: string
  conteudo?: string
  privacidade?: Id
  nivel_privacidade?: Id
}

export interface ChecklistItem {
  id?: Id
  id_item?: Id
  titulo: string
  descricao?: string
  observacao?: string
  cumprido?: Id
  concluido?: Id
}

export interface Checklist {
  id: Id
  titulo: string
  status?: string
  itens: ChecklistItem[]
  itens_cumpridos?: number
  total_itens?: number
}

export interface HistoricoCaso {
  acao: string
  descricao?: string
  created_at: string
}

export interface Movimentacao {
  id?: Id
  nome: string
  dataHora: string
  observacao?: string
  sentiment?: string
  sentiment_rationale?: string
}

export interface Intimacao {
  numero_processo: string
  tipoComunicacao?: string
  data_disponibilizacao: string
}

export interface Parte {
  id?: Id
  id_parte?: Id
  nome: string
  /** 'A' | 'P' | 'Ativo' | 'Passivo' */
  polo: string
  is_represented?: Id
}

export interface VinculoProcesso {
  id_vinculo: Id
  id_processo_vinculado: Id
  numeroprocessocommascara?: string
  titulo_processo?: string
  tipo_vinculo?: string
  descricao_vinculo?: string
  is_arquivado?: Id
}

export interface Tarefa {
  id?: Id
  id_tarefa?: Id
  tarefa: string
  prazo?: string
  status?: Id
}

export interface Usuario {
  id: Id
  nome?: string
  username?: string
}

/** Item das listas de "últimos movimentados" do dashboard. */
export interface ProcessoResumo {
  id?: Id
  id_processo?: Id
  numero_processo?: string
  nome?: string
  titulo_processo?: string
}

/** Mensagem exibida pelo ToastContainer. */
export interface Toast {
  id: Id
  type: 'success' | 'danger' | 'warning' | 'info' | string
  msg: string
}

/** Migration do banco, como retornada por GET configuracoes/migracoes. */
export interface Migracao {
  versao: string;
  nome: string;
  classe: string;
  namespace: string;
  status: 'aplicada' | 'pendente';
  lote: number | null;
  aplicada_em: string | null;
}

export interface MigracoesStatus {
  pendentes: number;
  aplicadas: number;
  ultimo_lote: number;
  migracoes: Migracao[];
}

/** Resultado de POST configuracoes/migracoes/executar. */
export interface MigracoesResultado {
  executadas: string[];
  mensagens: string[];
}

/** Contagem de clientes ou processos por situação de pasta no Drive. */
export interface DriveSyncEntidadeResumo {
  total: number;
  com_pasta: number;
  sem_pasta: number;
  /** Sem pasta e sem job pendente/em processamento. */
  sem_job: number;
}

/** Resposta de GET drive/resumo. */
export interface DriveSyncResumo {
  configurado: boolean;
  fila: { pending: number; processing: number; completed: number; failed: number; travados: number };
  clientes: DriveSyncEntidadeResumo;
  processos: DriveSyncEntidadeResumo;
}

/** Resultado de POST drive/processar. */
export interface DriveSyncLote {
  processados: number;
  sucesso: number;
  falhas: number;
  restantes: number;
  itens: { id: number; status: 'completed' | 'failed'; pasta?: string; erro?: string }[];
}

/** Privacidade granular de processos, clientes e contratos. */
export type Visibilidade = 'publico' | 'restrito'

export type TipoEntidadeAcesso = 'processo' | 'cliente' | 'contrato'

/** Advogado com acesso (leitura e edição) a um registro restrito. */
export interface AcessoUsuario {
  user_id: Id
  username: string
  concedido_por?: Id | null
  concedido_por_nome?: string | null
  created_at?: string | null
}

/** Resposta de GET acessos/{tipo}/{id}. */
export interface AcessosEntidade {
  visibilidade: Visibilidade
  responsavel: { user_id: number; username: string } | null
  usuarios: AcessoUsuario[]
  pode_gerenciar: boolean
}

/** Comprovante de pagamento guardado no Google Drive (pasta do financeiro, subpasta AAAA-MM). */
export interface Comprovante {
  id_comprovante: Id
  tipo: 'despesa' | 'receita'
  pagamento_id: Id
  nome: string
  mime_type?: string | null
  tamanho?: number | null
  drive_id: string
  drive_link: string
  /** Mês da pasta no Drive (AAAA-MM). */
  pasta_mes: string
  created_at?: string | null
}

/** Arquivo de contrato guardado no Google Drive (subpasta Contratos da pasta do cliente). */
export interface ContratoArquivo {
  id_contrato_arquivo: Id
  contrato_id: Id
  categoria: 'Contrato original' | 'Aditivo' | 'Anexo' | 'Outros'
  nome: string
  mime_type?: string | null
  tamanho?: number | null
  drive_id: string
  drive_link: string
  created_at?: string | null
}
