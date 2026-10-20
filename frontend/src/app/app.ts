import { ChangeDetectionStrategy, Component, computed, effect, inject } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule, MatIconRegistry } from '@angular/material/icon';
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
      <header class="shell-header">
        <div class="bar">
          <a class="brand" routerLink="/products">Каталог</a>
          <nav aria-label="Основная навигация">
            <a routerLink="/products" routerLinkActive="active" ariaCurrentWhenActive="page">
              <mat-icon>inventory_2</mat-icon> Товары
            </a>
            <a routerLink="/import" routerLinkActive="active" ariaCurrentWhenActive="page">
              <mat-icon>upload_file</mat-icon> Импорт
            </a>
          </nav>
          <div class="account">
            <span class="avatar" aria-hidden="true">{{ initials() }}</span>
            <span class="email">{{ email }}</span>
            <button mat-button type="button" (click)="auth.logout()"><mat-icon>logout</mat-icon> Выйти</button>
          </div>
        </div>

        <!-- Running import, visible on every page except the import page (which shows full details) -->
        @if (tracker.job(); as job) {
          @if (tracker.running() && !onImportPage()) {
            <a class="import-progress" routerLink="/import" role="status">
              <span class="import-progress-text">
                <span class="eyebrow">Импорт</span>
                {{ job.filename }} — {{ job.processed }} из {{ job.total || '…' }}
                <span class="more">Подробнее <mat-icon>arrow_forward</mat-icon></span>
              </span>
              <mat-progress-bar
                [mode]="job.status === 'queued' ? 'indeterminate' : 'determinate'"
                [value]="tracker.percent()"
                aria-label="Прогресс импорта"
              />
            </a>
          }
        }
      </header>
      <main class="sheet">
        <router-outlet />
      </main>
    } @else {
      <router-outlet />
    }
  `,
  styles: `
    .shell-header { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 18px 0 14px; }
    .bar { display: flex; align-items: center; gap: 28px; }
    .brand {
      font-family: var(--font-serif);
      font-size: 28px;
      line-height: 1;
      letter-spacing: -0.04em;
      color: #164c37;
      text-decoration: none;
    }
    nav { display: flex; gap: 6px; }
    nav a {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      height: 38px;
      padding: 0 14px;
      border-radius: 9px;
      color: var(--ink-soft);
      font-size: 13px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.15s, color 0.15s;

      mat-icon { font-size: 18px; width: 18px; height: 18px; }
      &:hover { background: rgb(255 255 255 / 60%); color: var(--ink); }
      &.active { background: var(--green-tint); color: var(--green-dark); box-shadow: inset 0 0 0 1px var(--green-line); }
    }
    .account { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .avatar {
      width: 32px;
      height: 32px;
      display: grid;
      place-items: center;
      border-radius: 50%;
      background: #e5eee8;
      color: var(--green-dark);
      font-size: 11px;
      font-weight: 750;
      letter-spacing: 0.02em;
    }
    .email { font-size: 13px; color: var(--muted); }
    .import-progress {
      display: block;
      margin-top: 12px;
      padding: 10px 14px 0;
      border: 1px solid var(--green-line);
      border-radius: 10px;
      background: #f4faf6;
      color: var(--green-dark);
      font-size: 13px;
      text-decoration: none;
      overflow: hidden;

      .import-progress-text { display: flex; align-items: center; gap: 10px; padding-bottom: 9px; }
      .eyebrow { margin: 0; }
      .more { margin-left: auto; display: inline-flex; align-items: center; gap: 2px; font-weight: 650; }
      .more mat-icon { font-size: 16px; width: 16px; height: 16px; }
      mat-progress-bar { margin: 0 -14px; width: calc(100% + 28px); }
    }
    .sheet {
      width: min(1180px, calc(100% - 32px));
      margin: 0 auto 32px;
      padding: 32px 36px 40px;
      background: rgb(255 253 249 / 98%);
      border-radius: var(--radius-lg);
      min-height: calc(100vh - 120px);
    }
    @media (max-width: 720px) {
      .bar { flex-wrap: wrap; gap: 12px; }
      .brand { font-size: 24px; }
      .email { display: none; }
      nav { order: 3; width: 100%; }
      nav a { flex: 1; justify-content: center; }
      .sheet { width: 100%; border-radius: 16px 16px 0 0; padding: 22px 16px 32px; }
    }
  `,
})
export class App {
  protected readonly auth = inject(AuthService);
  protected readonly tracker = inject(ImportTrackerService);
  private readonly router = inject(Router);
  private readonly snackBar = inject(MatSnackBar);

  protected readonly initials = computed(() => (this.auth.email() ?? '').slice(0, 2).toUpperCase());

  protected readonly onImportPage = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      map((e) => e.urlAfterRedirects.startsWith('/import')),
    ),
    { initialValue: false },
  );

  constructor() {
    inject(MatIconRegistry).setDefaultFontSetClass('material-symbols-outlined');

    // Follow the last import while logged in; stop polling on logout
    effect(() => (this.auth.email() ? this.tracker.resume() : this.tracker.stop()));

    merge(
      fromEvent(window, 'offline').pipe(map(() => ({ text: 'Нет подключения к сети. Данные могут не обновляться.', error: true }))),
      fromEvent(window, 'online').pipe(map(() => ({ text: 'Подключение восстановлено.', error: false }))),
    )
      .pipe(takeUntilDestroyed())
      .subscribe(({ text, error }) => this.snackBar.open(text, 'OK', { panelClass: error ? 'toast-error' : '' }));
  }
}
