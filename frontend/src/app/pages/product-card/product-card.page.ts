import { AsyncPipe, CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { Product } from '../../models';
import { ProductService } from '../../services/product.service';
import { selectProductsQueryParams } from '../../store/products';

type CardState = { loading: true } | { loading: false; product: Product } | { loading: false; error: string };

@Component({
  selector: 'app-product-card-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [AsyncPipe, CurrencyPipe, RouterLink, MatCardModule, MatButtonModule, MatIconModule, MatProgressBarModule],
  templateUrl: './product-card.page.html',
  styleUrl: './product-card.page.scss',
})
export class ProductCardPage {
  private readonly productService = inject(ProductService);

  /** Back link returns to the page and filters the user came from */
  protected readonly listParams$ = inject(Store).select(selectProductsQueryParams);

  /** Bound from the :id route param */
  readonly id = input.required<string>();

  protected readonly state$ = toObservable(this.id).pipe(
    switchMap((id) =>
      this.productService.get(Number(id)).pipe(
        map((product): CardState => ({ loading: false, product })),
        catchError((e: unknown) =>
          of<CardState>({
            loading: false,
            error: e instanceof HttpErrorResponse && e.status === 404 ? 'Товар не найден' : 'Не удалось загрузить товар',
          }),
        ),
        startWith<CardState>({ loading: true }),
      ),
    ),
  );
}
