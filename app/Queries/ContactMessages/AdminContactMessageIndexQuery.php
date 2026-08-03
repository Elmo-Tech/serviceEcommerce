<?php

declare(strict_types=1);

namespace App\Queries\ContactMessages;

use App\Enums\ContactMessages\ContactMessageStatus;
use App\Models\ContactMessage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminContactMessageIndexQuery
{
    /**
     * @param  array{status:?string,search:?string,dateFrom:?string,dateTo:?string,page:int,perPage:int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ContactMessage::query()->latestFirst();

        if (is_string($filters['status']) && $filters['status'] !== '') {
            $status = ContactMessageStatus::fromKey($filters['status']);

            if ($status instanceof ContactMessageStatus) {
                $query->where('status', $status->value);
            }
        }

        if (is_string($filters['search']) && $filters['search'] !== '') {
            $search = trim($filters['search']);

            $query->where(function ($nestedQuery) use ($search): void {
                $nestedQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%');
            });
        }

        if (is_string($filters['dateFrom']) && $filters['dateFrom'] !== '') {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $filters['dateFrom'], 'UTC')->startOfDay();
            $query->where('created_at', '>=', $from);
        }

        if (is_string($filters['dateTo']) && $filters['dateTo'] !== '') {
            $to = CarbonImmutable::createFromFormat('!Y-m-d', $filters['dateTo'], 'UTC')->endOfDay();
            $query->where('created_at', '<=', $to);
        }

        return $query->paginate(
            $filters['perPage'],
            ['*'],
            'page',
            $filters['page'],
        )->withQueryString();
    }
}
