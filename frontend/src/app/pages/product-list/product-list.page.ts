import { AsyncPipe, CurrencyPipe, DecimalPipe, I18nPluralPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, effect, inject, input } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Router, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { ProductQuery } from '../../models';
import {
  loadProducts,
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsTotal,
  selectTotalPages,
} from '../../store/products';

const DEFAULT_LIMIT = 20;

@Component({
  selector: 'app-product-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    AsyncPipe, CurrencyPipe, DecimalPipe, I18nPluralPipe, RouterLink, ReactiveFormsModule,
    MatFormFieldModule, MatInputModule, MatButtonModule, MatIconModule,
    MatPaginatorModule, MatProgressBarModule,
  ],
  templateUrl: './product-list.page.html',
  styleUrl: './product-list.page.scss',
})
export class ProductListPage {
  private readonly store = inject(Store);
  private readonly router = inject(Router);

  // The URL is the source of truth for the query (shareable, back button works); bound via withComponentInputBinding
  readonly page = input<string>();
  readonly limit = input<string>();
  readonly name = input<string>();
  readonly priceMin = input<string>(undefined, { alias: 'price_min' });
  readonly priceMax = input<string>(undefined, { alias: 'price_max' });

  protected readonly query = computed<ProductQuery>(() => ({
    page: Math.max(1, Number(this.page()) || 1),
    limit: Number(this.limit()) || DEFAULT_LIMIT,
    name: this.name() || undefined,
    priceMin: toNumber(this.priceMin()),
    priceMax: toNumber(this.priceMax()),
  }));

  protected readonly productPlural = { one: 'товар', few: 'товара', many: 'товаров', other: 'товара' };

  protected readonly products$ = this.store.select(selectAllProducts);
  protected readonly loading$ = this.store.select(selectProductsLoading);
  protected readonly total$ = this.store.select(selectProductsTotal);
  protected readonly totalPages$ = this.store.select(selectTotalPages);
  protected readonly error$ = this.store.select(selectProductsError);

  protected readonly filters = inject(NonNullableFormBuilder).group({
    name: [''],
    priceMin: [null as number | null],
    priceMax: [null as number | null],
  });

  constructor() {
    effect(() => {
      const query = this.query();
      this.store.dispatch(loadProducts({ query }));
      this.filters.setValue(
        { name: query.name ?? '', priceMin: query.priceMin ?? null, priceMax: query.priceMax ?? null },
        { emitEvent: false },
      );
    });
  }

  applyFilters(): void {
    const { name, priceMin, priceMax } = this.filters.getRawValue();
    this.navigate({ name: name.trim() || null, price_min: priceMin, price_max: priceMax, page: null });
  }

  resetFilters(): void {
    this.navigate({ name: null, price_min: null, price_max: null, page: null });
  }

  onPage(event: PageEvent): void {
    this.navigate({ page: event.pageIndex + 1, limit: event.pageSize === DEFAULT_LIMIT ? null : event.pageSize });
  }

  private navigate(queryParams: Record<string, string | number | null>): void {
    void this.router.navigate([], { queryParams, queryParamsHandling: 'merge' });
  }
}

function toNumber(value: string | undefined): number | null {
  return value === undefined || value === '' || Number.isNaN(Number(value)) ? null : Number(value);
}
