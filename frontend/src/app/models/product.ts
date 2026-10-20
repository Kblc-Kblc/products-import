import { ProductAttribute } from './product-attribute';
import { ProductImage } from './product-image';

export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  /** Decimal string, e.g. "1320.00" */
  price: string;
  /** Discount in %, decimal string; null if the purchase price is unknown */
  discount: string | null;
  attributes: ProductAttribute[];
  images: ProductImage[];
}

export type ProductListItem = Pick<Product, 'id' | 'externalCode' | 'name' | 'price' | 'discount'> & {
  image: string | null;
};

export interface ProductPage {
  items: ProductListItem[];
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface ProductQuery {
  page: number;
  limit: number;
  name?: string;
  priceMin?: number | null;
  priceMax?: number | null;
}
