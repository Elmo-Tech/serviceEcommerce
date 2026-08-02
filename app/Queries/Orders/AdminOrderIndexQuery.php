<?php

declare(strict_types=1);

namespace App\Queries\Orders;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class AdminOrderIndexQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $baseQuery = Order::query()
            ->withCount('items');

        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, mixed $value): void {
                    $search = trim((string) $value);

                    if ($search === '') {
                        return;
                    }

                    $query->where(function ($nestedQuery) use ($search): void {
                        $nestedQuery
                            ->where('order_number', 'like', '%'.$search.'%')
                            ->orWhere('customer_name', 'like', '%'.$search.'%')
                            ->orWhere('customer_phone', 'like', '%'.$search.'%')
                            ->orWhere('customer_email', 'like', '%'.$search.'%');
                    });
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('paymentStatus', 'payment_status'),
                AllowedFilter::exact('orderPlace', 'order_place'),
                AllowedFilter::exact('customerId', 'customer_id'),
                AllowedFilter::callback('serviceId', function ($query, mixed $value): void {
                    $query->whereHas('items', fn ($itemsQuery) => $itemsQuery->where('service_id', (int) $value));
                }),
                AllowedFilter::callback('createdFrom', function ($query, mixed $value): void {
                    $query->where('created_at', '>=', (string) $value);
                }),
                AllowedFilter::callback('createdTo', function ($query, mixed $value): void {
                    $query->where('created_at', '<=', (string) $value);
                }),
                AllowedFilter::callback('totalFrom', function ($query, mixed $value): void {
                    $query->where('total', '>=', number_format((float) $value, 2, '.', ''));
                }),
                AllowedFilter::callback('totalTo', function ($query, mixed $value): void {
                    $query->where('total', '<=', number_format((float) $value, 2, '.', ''));
                }),
            )
            ->allowedSorts(
                AllowedSort::field('createdAt', 'created_at'),
                AllowedSort::field('total', 'total'),
            );

        return $query
            ->defaultSort('-createdAt')
            ->paginate((int) ($filters['perPage'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }
}
