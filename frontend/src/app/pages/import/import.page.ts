import { DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { ImportJob } from '../../models';
import { ImportTrackerService } from '../../services/import-tracker.service';

const MAX_BYTES = 10 * 1024 * 1024;

@Component({
  selector: 'app-import-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, DecimalPipe, RouterLink, MatButtonModule, MatIconModule, MatProgressBarModule, MatTableModule],
  templateUrl: './import.page.html',
  styleUrl: './import.page.scss',
})
export class ImportPage {
  private readonly tracker = inject(ImportTrackerService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly statusLabels: Record<ImportJob['status'], string> = {
    queued: 'в очереди',
    processing: 'выполняется',
    done: 'завершён',
    failed: 'ошибка',
  };

  protected readonly statusBadge: Record<ImportJob['status'], string> = {
    queued: 'badge',
    processing: 'badge amber',
    done: 'badge green',
    failed: 'badge red',
  };

  protected readonly file = signal<File | null>(null);
  protected readonly dragging = signal(false);
  protected readonly uploading = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly job = this.tracker.job;
  protected readonly percent = this.tracker.percent;
  protected readonly running = computed(() => this.uploading() || this.tracker.running());

  onDragOver(event: DragEvent): void {
    if (this.running()) return;
    event.preventDefault();
    this.dragging.set(true);
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragging.set(false);
    if (!this.running()) this.onFileSelected(event.dataTransfer?.files ?? null);
  }

  onFileSelected(files: FileList | null): void {
    const file = files?.[0] ?? null;
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
    this.tracker.upload(file).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.uploading.set(false);
        this.file.set(null);
      },
      error: (e: unknown) => {
        this.uploading.set(false);
        // Network/5xx/429 are already reported by the HTTP interceptor toast
        if (e instanceof HttpErrorResponse && typeof e.error?.error === 'string') {
          this.error.set(e.error.error);
        } else if (!(e instanceof HttpErrorResponse)) {
          this.error.set('Не удалось загрузить файл');
        }
      },
    });
  }
}
