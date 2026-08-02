<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Dashboard;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\DashboardFilterShapeGuard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShowDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.ordersPeriod' => ['sometimes', 'string', Rule::in(['today', 'this_week', 'this_month', 'custom'])],
            'filter.dateFrom' => ['sometimes', 'string', 'date_format:Y-m-d', 'required_with:filter.dateTo'],
            'filter.dateTo' => ['sometimes', 'string', 'date_format:Y-m-d', 'required_with:filter.dateFrom'],
            'filter.status' => ['sometimes', 'string', 'regex:/^[0-4]$/'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (app(DashboardFilterShapeGuard::class)->violations(
                    $this->server('QUERY_STRING'),
                ) as $attribute => $translationKey) {
                    $validator->errors()->add($attribute, __($translationKey));
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $filter = $this->input('filter', []);

                if (! is_array($filter)) {
                    return;
                }

                $ordersPeriod = $filter['ordersPeriod'] ?? 'today';
                $dateFrom = $filter['dateFrom'] ?? null;
                $dateTo = $filter['dateTo'] ?? null;

                if ($ordersPeriod === 'custom' && ($dateFrom === null || $dateTo === null)) {
                    $validator->errors()->add('filter.dateFrom', __('validation.required', ['attribute' => 'filter.dateFrom']));
                    $validator->errors()->add('filter.dateTo', __('validation.required', ['attribute' => 'filter.dateTo']));

                    return;
                }

                if (! is_string($dateFrom) || ! is_string($dateTo)) {
                    return;
                }

                $start = CarbonImmutable::createFromFormat('!Y-m-d', $dateFrom, 'UTC');
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $dateTo, 'UTC');

                if ($start === false || $end === false) {
                    return;
                }

                if ($start->greaterThan($end)) {
                    $validator->errors()->add('filter.dateTo', __('validation.after_or_equal', [
                        'attribute' => 'filter.dateTo',
                        'date' => 'filter.dateFrom',
                    ]));

                    return;
                }

                if ($start->diffInDays($end) + 1 > 366) {
                    $validator->errors()->add('filter.dateTo', __('dashboard.errors.invalid_filters'));
                }
            },
        ];
    }

    public function filters(): DashboardFilterData
    {
        /** @var array<string, mixed> $filter */
        $filter = $this->validated('filter', []);

        return new DashboardFilterData(
            ordersPeriod: (string) ($filter['ordersPeriod'] ?? 'today'),
            dateFrom: isset($filter['dateFrom']) ? (string) $filter['dateFrom'] : null,
            dateTo: isset($filter['dateTo']) ? (string) $filter['dateTo'] : null,
            status: array_key_exists('status', $filter) ? (int) $filter['status'] : null,
        );
    }
}
