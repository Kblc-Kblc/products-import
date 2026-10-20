import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatToolbarModule, MatButtonModule, MatIconModule],
  template: `
    @if (auth.email(); as email) {
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
    }
    <main>
      <router-outlet />
    </main>
  `,
  styles: `
    mat-toolbar { gap: 8px; position: sticky; top: 0; z-index: 1; }
    nav { display: flex; gap: 4px; }
    .active { background: var(--mat-sys-secondary-container); }
    .spacer { flex: 1; }
    .email { font: var(--mat-sys-body-medium); color: var(--mat-sys-on-surface-variant); }
    main { max-width: 1100px; margin: 0 auto; padding: 24px 16px; }
    @media (max-width: 600px) { .email { display: none; } }
  `,
})
export class App {
  protected readonly auth = inject(AuthService);
}
