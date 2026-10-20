import { createFeatureSelector, createSelector } from '@ngrx/store';
import { PRODUCTS_FEATURE_KEY, ProductsState, initialState } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>(PRODUCTS_FEATURE_KEY);

export const selectAllProducts = createSelector(selectProductsState, (state) => state.items);
export const selectProductsLoading = createSelector(selectProductsState, (state) => state.loading);
export const selectTotalPages = createSelector(selectProductsState, (state) => state.totalPages);
export const selectProductsTotal = createSelector(selectProductsState, (state) => state.total);
export const selectProductsQuery = createSelector(selectProductsState, (state) => state.query);
export const selectProductsError = createSelector(selectProductsState, (state) => state.error);

/** The current list query as URL params (defaults omitted), e.g. for a "back to list" link */
export const selectProductsQueryParams = createSelector(selectProductsQuery, (query) => {
  const params: Record<string, string | number> = {};
  if (query.page > 1) params['page'] = query.page;
  if (query.limit !== initialState.query.limit) params['limit'] = query.limit;
  if (query.name) params['name'] = query.name;
  if (query.priceMin != null) params['price_min'] = query.priceMin;
  if (query.priceMax != null) params['price_max'] = query.priceMax;
  return params;
});
