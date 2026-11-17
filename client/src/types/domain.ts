/**
 * Tipos de domínio compartilhados pelos componentes.
 *
 * Os campos foram levantados a partir do uso real nos componentes. O backend
 * (PHP) ora devolve `id`, ora `id_<entidade>`, e IDs/flags podem chegar como
 * número ou string — por isso vários campos são opcionais ou `Id`.
 */

/** ID vindo do backend: número ou string numérica. */
export type Id = number | string

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

/** Contagem de clientes por situação de pasta no Drive. */
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
}

/** Resultado de POST drive/processar. */
export interface DriveSyncLote {
  processados: number;
  sucesso: number;
  falhas: number;
  restantes: number;
  itens: { id: number; status: 'completed' | 'failed'; pasta?: string; erro?: string }[];
}

/** Privacidade granular de clientes e contratos. */
export type Visibilidade = 'publico' | 'restrito'

export type TipoEntidadeAcesso = 'cliente' | 'contrato'

/** Profissional com acesso (leitura e edição) a um registro restrito. */
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
