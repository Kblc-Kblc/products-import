import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, MatCardModule, MatFormFieldModule, MatInputModule, MatButtonModule, MatProgressBarModule],
  template: `
    <mat-card class="login" appearance="outlined">
      @if (loading()) {
        <mat-progress-bar mode="indeterminate" />
      }
      <mat-card-header>
        <mat-card-title><h1>Вход</h1></mat-card-title>
      </mat-card-header>
      <form [formGroup]="form" (ngSubmit)="submit()">
        <mat-card-content>
          <mat-form-field>
            <mat-label>Email</mat-label>
            <input matInput type="email" formControlName="email" autocomplete="username" required />
            @if (form.controls.email.hasError('email')) {
              <mat-error>Некорректный email</mat-error>
            }
          </mat-form-field>
          <mat-form-field>
            <mat-label>Пароль</mat-label>
            <input matInput type="password" formControlName="password" autocomplete="current-password" required />
          </mat-form-field>
          @if (error()) {
            <p class="error" role="alert">{{ error() }}</p>
          }
        </mat-card-content>
        <mat-card-actions>
          <button mat-flat-button type="submit" [disabled]="loading()">Войти</button>
        </mat-card-actions>
      </form>
    </mat-card>
  `,
  styles: `
    .login { max-width: 400px; margin: 64px auto; }
    h1 { margin: 0; font: var(--mat-sys-headline-small); }
    mat-form-field { display: block; }
    mat-card-content { padding-top: 16px; }
    .error { color: var(--mat-sys-error); margin: 0; }
  `,
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Bound from the ?returnUrl= query param */
  readonly returnUrl = input<string>();

  protected readonly loading = signal(false);
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
