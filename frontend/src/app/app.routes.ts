import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    title: 'Вход',
    canActivate: [guestGuard],
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
        loadChildren: () => import('./pages/products.routes').then((m) => m.PRODUCTS_ROUTES),
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
