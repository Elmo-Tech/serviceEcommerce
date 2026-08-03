<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\ContactMessages;

use App\Enums\ContactMessages\ContactMessageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexContactMessagesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $filter = $this->input('filter', []);

        if (is_array($filter)) {
            if (array_key_exists('search', $filter) && is_string($filter['search'])) {
                $filter['search'] = trim($filter['search']);

                if ($filter['search'] === '') {
                    unset($filter['search']);
                }
            }

            if (array_key_exists('date', $filter) && is_array($filter['date'])) {
                $date = $filter['date'];
                $from = array_key_exists(0, $date) ? trim((string) $date[0]) : null;
                $to = array_key_exists(1, $date) ? trim((string) $date[1]) : null;

                $filter['date'] = [
                    0 => $from === '' ? null : $from,
                    1 => $to === '' ? null : $to,
                ];
            }
        }

        $this->merge([
            'filter' => $filter,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes', 'string', Rule::in(ContactMessageStatus::keys())],
            'filter.search' => ['sometimes', 'string', 'max:255'],
            'filter.date' => ['sometimes', 'array'],
            'filter.date.0' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'filter.date.1' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unexpectedKeys = array_diff(array_keys($this->query()), ['filter', 'page', 'perPage']);

                if ($unexpectedKeys !== []) {
                    $validator->errors()->add('payload', __('validation.invalid_payload'));
                }

                $filter = $this->query('filter', []);

                if (is_array($filter)) {
                    $unexpectedFilterKeys = array_diff(array_keys($filter), ['status', 'search', 'date']);

                    if ($unexpectedFilterKeys !== []) {
                        $validator->errors()->add('payload', __('validation.invalid_payload'));
                    }

                    if (array_key_exists('date', $filter) && is_array($filter['date'])) {
                        $unexpectedDateKeys = array_diff(array_keys($filter['date']), [0, 1, '0', '1']);

                        if ($unexpectedDateKeys !== []) {
                            $validator->errors()->add('payload', __('validation.invalid_payload'));
                        }
                    }
                }

                $validatedFilter = $this->input('filter', []);

                if (is_array($validatedFilter) && array_key_exists('date', $validatedFilter)) {
                    $date = is_array($validatedFilter['date']) ? $validatedFilter['date'] : [];
                    $from = $date[0] ?? null;
                    $to = $date[1] ?? null;

                    if ($from === null && $to === null) {
                        $validator->errors()->add('filter.date', __('validation.invalid_payload'));
                    }

                    if (is_string($from) && is_string($to)) {
                        $fromDate = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
                        $toDate = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');

                        if ($fromDate->greaterThan($toDate)) {
                            $validator->errors()->add('filter.date', __('validation.invalid_payload'));
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array{status:?string,search:?string,dateFrom:?string,dateTo:?string,page:int,perPage:int}
     */
    public function filters(): array
    {
        $filter = $this->input('filter', []);
        $date = is_array($filter) && array_key_exists('date', $filter) && is_array($filter['date'])
            ? $filter['date']
            : [];

        return [
            'status' => is_array($filter) && array_key_exists('status', $filter)
                ? (string) $filter['status']
                : null,
            'search' => is_array($filter) && array_key_exists('search', $filter)
                ? trim((string) $filter['search'])
                : null,
            'dateFrom' => array_key_exists(0, $date) ? $date[0] : null,
            'dateTo' => array_key_exists(1, $date) ? $date[1] : null,
            'page' => (int) $this->input('page', 1),
            'perPage' => (int) $this->input('perPage', 15),
        ];
    }
}
