<?php

declare(strict_types=1);

namespace App\Queries\HeroSlides;

use App\Models\HeroSlide;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdminHeroSlideIndexQuery
{
    /**
     * @param  array{page:int,perPage:int,isActive:int|null}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = QueryBuilder::for(HeroSlide::query())
            ->allowedFilters(AllowedFilter::exact('isActive', 'is_active'))
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
