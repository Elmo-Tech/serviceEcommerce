<?php

declare(strict_types=1);

use App\Services\Dashboard\DashboardFilterShapeGuard;

it('accepts only unique non-empty scalar members of the dashboard filter object', function (): void {
    $guard = app(DashboardFilterShapeGuard::class);

    expect($guard->violations('filter[ordersPeriod]=today&filter[status]=0'))->toBe([])
        ->and($guard->violations('status=1'))->toHaveKey('payload')
        ->and($guard->violations('filter[unknown]=1'))->toHaveKey('payload')
        ->and($guard->violations('filter[status][]=1'))->toHaveKey('payload')
        ->and($guard->violations('filter[status]=1&filter[status]=2'))->toHaveKey('filter.status')
        ->and($guard->violations('filter[dateFrom]=%20%20'))->toHaveKey('filter.dateFrom');
});
