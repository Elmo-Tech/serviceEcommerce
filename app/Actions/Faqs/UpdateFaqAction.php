<?php

declare(strict_types=1);

namespace App\Actions\Faqs;

use App\Models\Faq;
use App\Services\Faqs\FaqOrderingService;
use Illuminate\Database\Eloquent\Collection;

class UpdateFaqAction
{
    public function __construct(
        private readonly FaqOrderingService $orderingService,
    ) {}

    /**
     * @param  array{questionAr?:string,questionEn?:string,answerAr?:string,answerEn?:string,isActive?:int,position?:int}  $payload
     */
    public function execute(int $faqId, array $payload): Faq
    {
        /** @var Faq $faq */
        $faq = $this->orderingService->mutate(function (Collection $faqs) use ($faqId, $payload): Faq {
            $faq = $this->orderingService->findOrFail($faqs, $faqId);

            $questionAr = $payload['questionAr'] ?? $faq->question_ar;
            $questionEn = $payload['questionEn'] ?? $faq->question_en;

            $this->orderingService->ensureUniqueQuestions(
                $faqs,
                $questionAr,
                $questionEn,
                $faqId,
            );

            $updates = [];

            if (array_key_exists('questionAr', $payload)) {
                $updates['question_ar'] = $payload['questionAr'];
            }

            if (array_key_exists('questionEn', $payload)) {
                $updates['question_en'] = $payload['questionEn'];
            }

            if (array_key_exists('answerAr', $payload)) {
                $updates['answer_ar'] = $payload['answerAr'];
            }

            if (array_key_exists('answerEn', $payload)) {
                $updates['answer_en'] = $payload['answerEn'];
            }

            if (array_key_exists('isActive', $payload)) {
                $updates['is_active'] = (bool) $payload['isActive'];
            }

            if ($updates !== []) {
                $faq->fill($updates)->save();
            }

            if (! array_key_exists('position', $payload)) {
                /** @var Faq $freshFaq */
                $freshFaq = $faq->fresh();

                return $freshFaq;
            }

            $targetPosition = $this->orderingService->ensureUpdatePosition(
                $payload['position'],
                $faqs->count(),
            );

            if ($targetPosition === (int) $faq->position) {
                /** @var Faq $freshFaq */
                $freshFaq = $faq->fresh();

                return $freshFaq;
            }

            $orderedIds = $faqs->pluck('id')->values()->all();
            $currentIndex = array_search($faqId, $orderedIds, true);

            if ($currentIndex !== false) {
                array_splice($orderedIds, $currentIndex, 1);
            }

            array_splice($orderedIds, $targetPosition - 1, 0, [$faqId]);

            $this->orderingService->park($faqs);
            $this->orderingService->assign($orderedIds);

            /** @var Faq $freshFaq */
            $freshFaq = $faq->fresh();

            return $freshFaq;
        });

        return $faq;
    }
}
