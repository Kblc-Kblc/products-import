import { createAction, props } from '@ngrx/store';
import { ProductPage, ProductQuery } from '../../models';

export const loadProducts = createAction('[Products] Load Products', props<{ query: ProductQuery }>());
export const loadProductsSuccess = createAction('[Products] Load Products Success', props<{ page: ProductPage }>());
export const loadProductsFailure = createAction('[Products] Load Products Failure', props<{ error: string }>());
