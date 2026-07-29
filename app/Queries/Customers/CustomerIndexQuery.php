<?php

declare(strict_types=1);

namespace App\Queries\Customers;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerIndexQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? 'active';

        $baseQuery = Customer::query()
            ->withCount(['activeAddresses as addresses_count']);

        if ($status === 'deleted') {
            $baseQuery->onlyTrashed();
        } elseif ($status === 'all') {
            $baseQuery->withTrashed();
        } else {
            $baseQuery->whereNull('deleted_at');
        }

        $query = $baseQuery;

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($nestedQuery) use ($search): void {
                $nestedQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $hasAddresses = $filters['hasAddresses'] ?? null;

        if ($hasAddresses === true) {
            $query->has('activeAddresses');
        } elseif ($hasAddresses === false) {
            $query->doesntHave('activeAddresses');
        }

        if (! empty($filters['createdFrom'])) {
            $query->whereDate('created_at', '>=', (string) $filters['createdFrom']);
        }

        if (! empty($filters['createdTo'])) {
            $query->whereDate('created_at', '<=', (string) $filters['createdTo']);
        }

        $sort = (string) ($filters['sort'] ?? '-createdAt');
        $sortDirection = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $sortColumn = $sortField === 'name' ? 'name' : 'created_at';

        return $query
            ->orderBy($sortColumn, $sortDirection)
            ->paginate((int) ($filters['perPage'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }
}
