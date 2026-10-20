import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { EMPTY, Observable, catchError, switchMap, takeWhile, throwError, timer } from 'rxjs';
import { ImportJob } from '../models';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly http = inject(HttpClient);

  upload(file: File): Observable<ImportJob> {
    const body = new FormData();
    body.append('file', file);
    return this.http.post<ImportJob>('/api/imports', body);
  }

  get(id: number): Observable<ImportJob> {
    return this.http.get<ImportJob>(`/api/imports/${id}`);
  }

  /**
   * Polls the job every second until it is done or failed (the final state is emitted too).
   * A failed poll (network blip, 5xx) is skipped, not fatal; only 404 ends the stream with an error.
   */
  watch(id: number): Observable<ImportJob> {
    return timer(0, 1000).pipe(
      switchMap(() =>
        this.get(id).pipe(
          catchError((e: unknown) => (e instanceof HttpErrorResponse && e.status === 404 ? throwError(() => e) : EMPTY)),
        ),
      ),
      takeWhile((job) => job.status === 'queued' || job.status === 'processing', true),
    );
  }
}
