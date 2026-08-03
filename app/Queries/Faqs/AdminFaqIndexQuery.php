<?php

declare(strict_types=1);

namespace App\Queries\Faqs;

use App\Models\Faq;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdminFaqIndexQuery
{
    /**
     * @param  array{page:int,perPage:int,isActive:int|null,search:?string}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Faq::query())
            ->allowedFilters(
                AllowedFilter::exact('isActive', 'is_active'),
                AllowedFilter::callback('search', static function ($query, $value): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }

                    $search = mb_strtolower(trim($value));

                    $query->where(static function ($builder) use ($search): void {
                        $builder
                            ->whereRaw('LOWER(question_ar) LIKE ?', ['%'.$search.'%'])
                            ->orWhereRaw('LOWER(question_en) LIKE ?', ['%'.$search.'%']);
                    });
                }),
            )
            ->orderBy('position')
            ->orderBy('id');

        return $query->paginate(
            $filters['perPage'],
            ['*'],
            'page',
            $filters['page'],
        )->withQueryString();
    }
}
