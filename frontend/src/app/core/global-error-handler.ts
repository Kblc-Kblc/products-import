import { HttpErrorResponse } from '@angular/common/http';
import { ErrorHandler, Injectable, inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';

/** Unexpected runtime errors: log them and tell the user instead of failing silently. */
@Injectable()
export class GlobalErrorHandler implements ErrorHandler {
  private readonly snackBar = inject(MatSnackBar);

  handleError(error: unknown): void {
    console.error(error);
    // HTTP errors are already reported by the interceptor
    if (!(error instanceof HttpErrorResponse)) {
      this.snackBar.open('Что-то пошло не так. Обновите страницу.', 'OK', { panelClass: 'toast-error' });
    }
  }
}
