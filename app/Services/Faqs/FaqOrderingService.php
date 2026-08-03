<?php

declare(strict_types=1);

namespace App\Services\Faqs;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Faq;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FaqOrderingService
{
    private const TEMPORARY_POSITION_OFFSET = 2000000000;

    private const LOCK_NAME = 'service-commerce:faqs:ordering';

    private const LOCK_TIMEOUT_SECONDS = 5;

    /**
     * @template T
     *
     * @param  Closure(Collection<int, Faq>): T  $callback
     * @return T
     */
    public function mutate(Closure $callback): mixed
    {
        $connection = DB::connection();
        $lock = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
            self::LOCK_NAME,
            self::LOCK_TIMEOUT_SECONDS,
        ]);

        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new ApiBusinessException(
                'faqs.ordering_unavailable',
                'FAQ_ORDERING_UNAVAILABLE',
                HttpStatusCode::SERVICE_UNAVAILABLE,
            );
        }

        try {
            return $connection->transaction(function () use ($callback): mixed {
                /** @var Collection<int, Faq> $faqs */
                $faqs = Faq::query()
                    ->ordered()
                    ->lockForUpdate()
                    ->get();

                $this->assertContiguous($faqs);

                return $callback($faqs);
            }, 1);
        } finally {
            try {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
            } catch (Throwable) {
                // Never mask the original mutation result if releasing the lock fails.
            }
        }
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     */
    public function park(Collection $faqs): void
    {
        foreach ($faqs->values() as $index => $faq) {
            $faq->forceFill(['position' => self::TEMPORARY_POSITION_OFFSET + $index])->save();
        }
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function assign(array $orderedIds): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            throw new RuntimeException('Invalid FAQ order.');
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $updated = Faq::query()->whereKey($id)->update(['position' => $index + 1]);

            if ($updated !== 1) {
                throw new RuntimeException('FAQ order target is missing.');
            }
        }
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     */
    public function findOrFail(Collection $faqs, int $id): Faq
    {
        $faq = $faqs->firstWhere('id', $id);

        if (! $faq instanceof Faq) {
            throw new ApiBusinessException(
                'faqs.not_found',
                'FAQ_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        }

        return $faq;
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     */
    public function ensureUniqueQuestions(
        Collection $faqs,
        string $questionAr,
        string $questionEn,
        ?int $ignoreId = null,
    ): void {
        $normalizedQuestionAr = $this->normalizeQuestion($questionAr);
        $normalizedQuestionEn = $this->normalizeQuestion($questionEn);
        $errors = [];

        foreach ($faqs as $faq) {
            if ($ignoreId !== null && (int) $faq->id === $ignoreId) {
                continue;
            }

            if ($this->normalizeQuestion($faq->question_ar) === $normalizedQuestionAr) {
                $errors['questionAr'][] = __('faqs.duplicate_question_ar');
            }

            if ($this->normalizeQuestion($faq->question_en) === $normalizedQuestionEn) {
                $errors['questionEn'][] = __('faqs.duplicate_question_en');
            }
        }

        if ($errors !== []) {
            throw new ApiBusinessException(
                'validation.invalid_payload',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                $errors,
            );
        }
    }

    public function ensureCreatePosition(?int $position, int $count): int
    {
        $resolvedPosition = $position ?? ($count + 1);

        if ($resolvedPosition < 1 || $resolvedPosition > ($count + 1)) {
            throw new ApiBusinessException(
                'validation.invalid_payload',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['position' => [__('faqs.position_out_of_range_for_create')]],
            );
        }

        return $resolvedPosition;
    }

    public function ensureUpdatePosition(int $position, int $count): int
    {
        if ($position < 1 || $position > $count) {
            throw new ApiBusinessException(
                'validation.invalid_payload',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                ['position' => [__('faqs.position_out_of_range_for_update')]],
            );
        }

        return $position;
    }

    /**
     * @param  Collection<int, Faq>  $faqs
     */
    private function assertContiguous(Collection $faqs): void
    {
        $actual = $faqs->pluck('position')->map(static fn (mixed $position): int => (int) $position)->all();
        $expected = $faqs->isEmpty() ? [] : range(1, $faqs->count());

        if ($actual !== $expected) {
            throw new RuntimeException('FAQ ordering invariant is invalid.');
        }
    }

    private function normalizeQuestion(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
