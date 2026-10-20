import { createReducer, on } from '@ngrx/store';
import { ProductListItem, ProductQuery } from '../../models';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export const PRODUCTS_FEATURE_KEY = 'products';

export interface ProductsState {
  items: ProductListItem[];
  query: ProductQuery;
  total: number;
  totalPages: number;
  loading: boolean;
  error: string | null;
}

export const initialState: ProductsState = {
  items: [],
  query: { page: 1, limit: 20 },
  total: 0,
  totalPages: 0,
  loading: false,
  error: null,
};

export const productsReducer = createReducer(
  initialState,
  on(loadProducts, (state, { query }) => ({ ...state, query, loading: true, error: null })),
  on(loadProductsSuccess, (state, { page }) => ({
    ...state,
    items: page.items,
    total: page.total,
    totalPages: page.totalPages,
    loading: false,
  })),
  on(loadProductsFailure, (state, { error }) => ({ ...state, items: [], loading: false, error })),
);
