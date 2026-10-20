import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  readonly loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      // switchMap: a newer query (page/filter change) cancels the request in flight
      switchMap(({ query }) =>
        this.productService.list(query).pipe(
          map((page) => loadProductsSuccess({ page })),
          catchError((error: unknown) =>
            of(loadProductsFailure({
              error: error instanceof HttpErrorResponse && typeof error.error?.error === 'string'
                ? error.error.error
                : 'Не удалось загрузить товары',
            })),
          ),
        ),
      ),
    ),
  );
}
