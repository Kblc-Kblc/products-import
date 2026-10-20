import { Injectable, computed, inject, signal } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { Observable, Subscription, tap } from 'rxjs';
import { ImportJob } from '../models';
import { ImportService } from './import.service';

const LAST_JOB_KEY = 'lastImportId';

/**
 * App-wide state of the current import: the import page, the header progress bar and the
 * "import finished" toast all read it, so progress stays visible on any page.
 */
@Injectable({ providedIn: 'root' })
export class ImportTrackerService {
  private readonly importService = inject(ImportService);
  private readonly snackBar = inject(MatSnackBar);
  private readonly router = inject(Router);
  private subscription: Subscription | null = null;

  readonly job = signal<ImportJob | null>(null);
  readonly running = computed(() => {
    const status = this.job()?.status;
    return status === 'queued' || status === 'processing';
  });
  readonly percent = computed(() => {
    const job = this.job();
    return job && job.total > 0 ? Math.round((job.processed / job.total) * 100) : 0;
  });

  /** Uploads the file and starts tracking the created job. Upload errors (e.g. 422) go to the caller. */
  upload(file: File): Observable<ImportJob> {
    return this.importService.upload(file).pipe(
      tap((job) => {
        localStorage.setItem(LAST_JOB_KEY, String(job.id));
        this.job.set(job);
        this.track(job.id, true);
      }),
    );
  }

  /** Picks up the last import after a page reload (no toast for an import that already finished). */
  resume(): void {
    const id = Number(localStorage.getItem(LAST_JOB_KEY));
    if (id && !this.subscription) {
      this.track(id, false);
    }
  }

  stop(): void {
    this.subscription?.unsubscribe();
    this.subscription = null;
    this.job.set(null);
  }

  private track(id: number, notify: boolean): void {
    this.subscription?.unsubscribe();
    let wasRunning = notify;

    this.subscription = this.importService.watch(id).subscribe({
      next: (job) => {
        this.job.set(job);
        if (job.status === 'queued' || job.status === 'processing') {
          wasRunning = true;
        } else if (wasRunning) {
          wasRunning = false;
          this.notifyFinished(job);
        }
      },
      // 404: the job is gone (e.g. data was reseeded) — forget it
      error: () => {
        localStorage.removeItem(LAST_JOB_KEY);
        this.stop();
      },
    });
  }

  private notifyFinished(job: ImportJob): void {
    const message = job.status === 'failed'
      ? `Импорт «${job.filename}» прерван`
      : `Импорт завершён: создано ${job.created}, обновлено ${job.updated}, ошибок ${job.errors.length}`;
    this.snackBar
      .open(message, 'Отчёт', { duration: 8000 })
      .onAction()
      .subscribe(() => void this.router.navigate(['/import']));
  }
}
