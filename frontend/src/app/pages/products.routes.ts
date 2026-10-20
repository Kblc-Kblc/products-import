import { MatPaginatorIntl } from '@angular/material/paginator';
import { Routes } from '@angular/router';
import { provideEffects } from '@ngrx/effects';
import { provideState } from '@ngrx/store';
import { RuPaginatorIntl } from '../core/paginator-intl';
import { PRODUCTS_FEATURE_KEY, ProductsEffects, productsReducer } from '../store/products';

/** Lazy products section: its store, effects and paginator labels load only when it is first visited. */
export const PRODUCTS_ROUTES: Routes = [
  {
    path: '',
    providers: [
      provideState(PRODUCTS_FEATURE_KEY, productsReducer),
      provideEffects(ProductsEffects),
      { provide: MatPaginatorIntl, useClass: RuPaginatorIntl },
    ],
    children: [
      {
        path: '',
        title: 'Товары',
        loadComponent: () => import('./product-list/product-list.page').then((m) => m.ProductListPage),
      },
      {
        path: ':id',
        title: 'Карточка товара',
        loadComponent: () => import('./product-card/product-card.page').then((m) => m.ProductCardPage),
      },
    ],
  },
];
