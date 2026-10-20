import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Observable, switchMap } from 'rxjs';
import { ImportJob } from '../../models';
import { ImportService } from '../../services/import.service';

const MAX_BYTES = 10 * 1024 * 1024;
const LAST_JOB_KEY = 'lastImportId';

@Component({
  selector: 'app-import-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, RouterLink, MatCardModule, MatButtonModule, MatIconModule, MatProgressBarModule, MatTableModule],
  templateUrl: './import.page.html',
  styleUrl: './import.page.scss',
})
export class ImportPage {
  private readonly importService = inject(ImportService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly statusLabels: Record<ImportJob['status'], string> = {
    queued: 'в очереди',
    processing: 'выполняется',
    done: 'завершён',
    failed: 'ошибка',
  };

  protected readonly file = signal<File | null>(null);
  protected readonly job = signal<ImportJob | null>(null);
  protected readonly uploading = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly running = computed(() => {
    const status = this.job()?.status;
    return this.uploading() || status === 'queued' || status === 'processing';
  });
  protected readonly percent = computed(() => {
    const job = this.job();
    return job && job.total > 0 ? Math.round((job.processed / job.total) * 100) : 0;
  });

  constructor() {
    // Resume showing the last import after navigating away and back
    const lastId = Number(localStorage.getItem(LAST_JOB_KEY));
    if (lastId) {
      this.follow(this.importService.watch(lastId));
    }
  }

  onFileSelected(input: HTMLInputElement): void {
    const file = input.files?.[0] ?? null;
    input.value = '';
    this.error.set(null);
    if (file && !file.name.toLowerCase().endsWith('.xlsx')) {
      this.error.set('Нужен файл с расширением .xlsx');
      return;
    }
    if (file && file.size > MAX_BYTES) {
      this.error.set('Файл больше 10 МБ');
      return;
    }
    this.file.set(file);
  }

  upload(): void {
    const file = this.file();
    if (!file) return;
    this.uploading.set(true);
    this.error.set(null);
    this.job.set(null);
    this.follow(
      this.importService.upload(file).pipe(
        switchMap((job) => {
          localStorage.setItem(LAST_JOB_KEY, String(job.id));
          this.uploading.set(false);
          this.file.set(null);
          return this.importService.watch(job.id);
        }),
      ),
    );
  }

  private follow(job$: Observable<ImportJob>): void {
    job$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (job) => this.job.set(job),
      error: (e: unknown) => {
        this.uploading.set(false);
        if (e instanceof HttpErrorResponse && e.status === 404) {
          localStorage.removeItem(LAST_JOB_KEY);
          return;
        }
        this.error.set(
          e instanceof HttpErrorResponse && typeof e.error?.error === 'string'
            ? e.error.error
            : 'Не удалось загрузить файл',
        );
      },
    });
  }
}
