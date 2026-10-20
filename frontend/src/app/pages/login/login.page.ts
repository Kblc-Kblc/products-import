import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, MatFormFieldModule, MatInputModule, MatButtonModule, MatIconModule],
  template: `
    <div class="auth">
      <section class="hero">
        <p class="eyebrow">Каталог товаров</p>
        <h1>Импорт ассортимента из Excel</h1>
        <p class="lead">Загрузите выгрузку в .xlsx — товары, характеристики и фотографии появятся в каталоге. Повторная загрузка обновит данные без дубликатов.</p>
      </section>

      <form class="card" [formGroup]="form" (ngSubmit)="submit()">
        <h2>Вход</h2>
        <p class="card-description">Войдите, чтобы управлять каталогом.</p>

        <div class="field">
          <label for="email">Email</label>
          <mat-form-field appearance="outline" class="field-compact">
            <mat-icon matPrefix>mail</mat-icon>
            <input id="email" matInput type="email" formControlName="email" autocomplete="username" required />
          </mat-form-field>
          @if (form.controls.email.touched && form.controls.email.hasError('email')) {
            <span class="field-error">Некорректный email</span>
          }
        </div>

        <div class="field">
          <label for="password">Пароль</label>
          <mat-form-field appearance="outline" class="field-compact">
            <mat-icon matPrefix>lock</mat-icon>
            <input id="password" matInput [type]="showPassword() ? 'text' : 'password'" formControlName="password" autocomplete="current-password" required />
            <button
              matSuffix
              mat-icon-button
              type="button"
              [attr.aria-label]="showPassword() ? 'Скрыть пароль' : 'Показать пароль'"
              (click)="showPassword.set(!showPassword())"
            >
              <mat-icon>{{ showPassword() ? 'visibility_off' : 'visibility' }}</mat-icon>
            </button>
          </mat-form-field>
        </div>

        @if (error()) {
          <p class="message error" role="alert"><mat-icon>error</mat-icon>{{ error() }}</p>
        }

        <button mat-flat-button class="submit" type="submit" [disabled]="loading()">
          {{ loading() ? 'Входим…' : 'Войти' }}
        </button>
      </form>
    </div>
  `,
  styles: `
    @use '@angular/material' as mat;

    :host { display: block; min-height: 100vh; background: #fcfaf5; }
    .auth {
      width: min(1180px, calc(100% - 48px));
      min-height: 100vh;
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(380px, 0.9fr);
      gap: 64px;
      align-items: center;
      padding: 48px 0;
    }
    .hero h1 {
      max-width: 620px;
      margin: 0 0 20px;
      font-family: var(--font-serif);
      font-size: clamp(44px, 5vw, 72px);
      font-weight: 400;
      line-height: 0.98;
      letter-spacing: -0.025em;
      color: #0d4e3d;
    }
    .lead { max-width: 540px; margin: 0; font-size: 18px; line-height: 1.45; color: #65716d; }
    .card {
      width: min(100%, 460px);
      justify-self: end;
      padding: 44px;
      border: 1px solid #e9e4db;
      border-radius: 24px;
      background: #fffefa;
      box-shadow: 0 20px 55px rgb(62 51 35 / 9%);
      display: grid;
      gap: 16px;

      @include mat.form-field-overrides((container-height: 52px, container-vertical-padding: 14px, outlined-container-shape: 12px));
    }
    h2 {
      margin: 0;
      font-family: var(--font-serif);
      font-size: 40px;
      font-weight: 400;
      line-height: 1.05;
      letter-spacing: -0.025em;
    }
    .card-description { margin: -6px 0 8px; color: #66716d; font-size: 15px; }
    .field { display: grid; gap: 7px; }
    label { font-size: 14px; font-weight: 650; color: #35403c; }
    .field-error { font-size: 12px; color: var(--red); }
    .submit {
      height: 52px;
      margin-top: 6px;
      font-size: 16px;
      @include mat.button-overrides((filled-container-shape: 12px));
    }
    @media (max-width: 860px) {
      .auth { grid-template-columns: 1fr; gap: 32px; width: calc(100% - 32px); align-content: start; padding-top: 40px; }
      .hero h1 { font-size: 40px; }
      .lead { font-size: 16px; }
      .card { justify-self: stretch; width: 100%; padding: 28px 22px; }
    }
  `,
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Bound from the ?returnUrl= query param */
  readonly returnUrl = input<string>();

  protected readonly loading = signal(false);
  protected readonly showPassword = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.loading.set(true);
    this.error.set(null);
    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password).subscribe({
      next: () => void this.router.navigateByUrl(this.safeReturnUrl()),
      error: (e: { status?: number }) => {
        this.loading.set(false);
        this.error.set(e.status === 401 ? 'Неверный email или пароль' : 'Не удалось войти, попробуйте ещё раз');
      },
    });
  }

  /** Only same-app paths, so ?returnUrl= can't send the user to another site */
  private safeReturnUrl(): string {
    const url = this.returnUrl();
    return url && url.startsWith('/') && !url.startsWith('//') ? url : '/products';
  }
}
