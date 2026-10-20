export type ImportStatus = 'queued' | 'processing' | 'done' | 'failed';

export interface ImportError {
  /** Row number in the file; 0 means the whole import failed */
  row: number;
  message: string;
}

export interface ImportJob {
  id: number;
  status: ImportStatus;
  filename: string;
  total: number;
  processed: number;
  created: number;
  updated: number;
  errors: ImportError[];
  createdAt: string;
  finishedAt: string | null;
}
