import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Product, ProductPage, ProductQuery } from '../models';

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly http = inject(HttpClient);

  list(query: ProductQuery): Observable<ProductPage> {
    let params = new HttpParams().set('page', query.page).set('limit', query.limit);
    if (query.name) params = params.set('name', query.name);
    if (query.priceMin != null) params = params.set('price_min', query.priceMin);
    if (query.priceMax != null) params = params.set('price_max', query.priceMax);

    return this.http.get<ProductPage>('/api/products', { params });
  }

  get(id: number): Observable<Product> {
    return this.http.get<Product>(`/api/products/${id}`);
  }
}
