import { ChangeDetectionStrategy, Component, effect, inject } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatToolbarModule } from '@angular/material/toolbar';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, fromEvent, map, merge } from 'rxjs';
import { AuthService } from './services/auth.service';
import { ImportTrackerService } from './services/import-tracker.service';

@Component({
  selector: 'app-root',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatToolbarModule, MatButtonModule, MatIconModule, MatProgressBarModule],
  template: `
    @if (auth.email(); as email) {
      <header>
        <mat-toolbar>
          <nav aria-label="Основная навигация">
            <a mat-button routerLink="/products" routerLinkActive="active" ariaCurrentWhenActive="page">
              <mat-icon>inventory_2</mat-icon> Товары
            </a>
            <a mat-button routerLink="/import" routerLinkActive="active" ariaCurrentWhenActive="page">
              <mat-icon>upload_file</mat-icon> Импорт
            </a>
          </nav>
          <span class="spacer"></span>
          <span class="email">{{ email }}</span>
          <button mat-button type="button" (click)="auth.logout()"><mat-icon>logout</mat-icon> Выйти</button>
        </mat-toolbar>

        <!-- Running import, visible on every page except the import page (which shows full details) -->
        @if (tracker.job(); as job) {
          @if (tracker.running() && !onImportPage()) {
            <div class="import-progress" role="status">
              <mat-progress-bar
                [mode]="job.status === 'queued' ? 'indeterminate' : 'determinate'"
                [value]="tracker.percent()"
                aria-label="Прогресс импорта"
              />
              <a routerLink="/import">
                Импорт «{{ job.filename }}»: {{ job.processed }} из {{ job.total || '…' }} — подробнее
              </a>
            </div>
          }
        }
      </header>
    }
    <main>
      <router-outlet />
    </main>
  `,
  styles: `
    header { position: sticky; top: 0; z-index: 1; background: var(--mat-sys-surface); }
    mat-toolbar { gap: 8px; }
    nav { display: flex; gap: 4px; }
    .active { background: var(--mat-sys-secondary-container); }
    .spacer { flex: 1; }
    .email { font: var(--mat-sys-body-medium); color: var(--mat-sys-on-surface-variant); }
    .import-progress a {
      display: block;
      padding: 4px 16px;
      font: var(--mat-sys-body-small);
      background: var(--mat-sys-surface-container);
    }
    main { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
    @media (max-width: 600px) { .email { display: none; } }
  `,
})
export class App {
  protected readonly auth = inject(AuthService);
  protected readonly tracker = inject(ImportTrackerService);
  private readonly router = inject(Router);
  private readonly snackBar = inject(MatSnackBar);

  protected readonly onImportPage = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      map((e) => e.urlAfterRedirects.startsWith('/import')),
    ),
    { initialValue: false },
  );

  constructor() {
    // Follow the last import while logged in; stop polling on logout
    effect(() => (this.auth.email() ? this.tracker.resume() : this.tracker.stop()));

    merge(
      fromEvent(window, 'offline').pipe(map(() => 'Нет подключения к сети. Данные могут не обновляться.')),
      fromEvent(window, 'online').pipe(map(() => 'Подключение восстановлено.')),
    )
      .pipe(takeUntilDestroyed())
      .subscribe((message) => this.snackBar.open(message, 'OK', { duration: 4000 }));
  }
}
