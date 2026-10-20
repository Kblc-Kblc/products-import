import { Routes } from '@angular/router';
import { provideEffects } from '@ngrx/effects';
import { provideState } from '@ngrx/store';
import { authGuard } from './core/auth.guard';
import { PRODUCTS_FEATURE_KEY, ProductsEffects, productsReducer } from './store/products';

export const routes: Routes = [
  {
    path: 'login',
    title: 'Вход',
    loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: '',
    canActivate: [authGuard],
    canActivateChild: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'products' },
      {
        path: 'products',
        // Products store is registered only when this lazy section is first visited
        providers: [provideState(PRODUCTS_FEATURE_KEY, productsReducer), provideEffects(ProductsEffects)],
        children: [
          {
            path: '',
            title: 'Товары',
            loadComponent: () => import('./pages/product-list/product-list.page').then((m) => m.ProductListPage),
          },
          {
            path: ':id',
            title: 'Карточка товара',
            loadComponent: () => import('./pages/product-card/product-card.page').then((m) => m.ProductCardPage),
          },
        ],
      },
      {
        path: 'import',
        title: 'Импорт',
        loadComponent: () => import('./pages/import/import.page').then((m) => m.ImportPage),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
