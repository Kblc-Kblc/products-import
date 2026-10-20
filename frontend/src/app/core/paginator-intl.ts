import { Injectable } from '@angular/core';
import { MatPaginatorIntl } from '@angular/material/paginator';

@Injectable()
export class RuPaginatorIntl extends MatPaginatorIntl {
  override itemsPerPageLabel = 'На странице:';
  override nextPageLabel = 'Следующая страница';
  override previousPageLabel = 'Предыдущая страница';
  override firstPageLabel = 'Первая страница';
  override lastPageLabel = 'Последняя страница';

  override getRangeLabel = (page: number, pageSize: number, length: number): string => {
    if (length === 0) return '0 из 0';
    const start = page * pageSize;
    return `${start + 1} – ${Math.min(start + pageSize, length)} из ${length}`;
  };
}
