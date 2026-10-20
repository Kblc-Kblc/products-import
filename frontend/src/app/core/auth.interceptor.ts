import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

/**
 * Adds the JWT to API calls and reports errors globally: 401 → back to login,
 * network failure / 5xx / 429 → toast. Other 4xx (validation, not found) are shown by the page itself.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  const token = auth.token();
  const request = token && req.url.startsWith('/api/')
    ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : req;

  return next(request).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401 && !req.url.endsWith('/auth/login')) {
          auth.logout(router.url);
        } else if (error.status === 0) {
          snackBar.open('Нет связи с сервером. Проверьте подключение к сети.', 'OK', { duration: 5000 });
        } else if (error.status >= 500) {
          snackBar.open('Ошибка на сервере. Попробуйте позже.', 'OK', { duration: 5000 });
        } else if (error.status === 429) {
          snackBar.open('Слишком много запросов. Подождите минуту.', 'OK', { duration: 5000 });
        }
      }
      return throwError(() => error);
    }),
  );
};
