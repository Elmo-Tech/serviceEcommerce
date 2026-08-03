<?php

declare(strict_types=1);

namespace App\Actions\Faqs;

use App\Models\Faq;
use App\Services\Faqs\FaqOrderingService;
use Illuminate\Database\Eloquent\Collection;

class CreateFaqAction
{
    public function __construct(
        private readonly FaqOrderingService $orderingService,
    ) {}

    /**
     * @param  array{questionAr:string,questionEn:string,answerAr:string,answerEn:string,isActive:int,position?:int}  $payload
     */
    public function execute(array $payload): Faq
    {
        /** @var Faq $faq */
        $faq = $this->orderingService->mutate(function (Collection $faqs) use ($payload): Faq {
            $this->orderingService->ensureUniqueQuestions(
                $faqs,
                $payload['questionAr'],
                $payload['questionEn'],
            );

            $position = $this->orderingService->ensureCreatePosition(
                $payload['position'] ?? null,
                $faqs->count(),
            );

            $faq = Faq::query()->create([
                'question_ar' => $payload['questionAr'],
                'question_en' => $payload['questionEn'],
                'answer_ar' => $payload['answerAr'],
                'answer_en' => $payload['answerEn'],
                'is_active' => (bool) $payload['isActive'],
                'position' => 2100000000 + $faqs->count(),
            ]);

            $orderedIds = $faqs->pluck('id')->all();
            array_splice($orderedIds, $position - 1, 0, [$faq->id]);

            $this->orderingService->park($faqs->push($faq));
            $this->orderingService->assign($orderedIds);

            /** @var Faq $freshFaq */
            $freshFaq = $faq->fresh();

            return $freshFaq;
        });

        return $faq;
    }
}
