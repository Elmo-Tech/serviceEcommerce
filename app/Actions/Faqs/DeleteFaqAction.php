<?php

declare(strict_types=1);

namespace App\Actions\Faqs;

use App\Services\Faqs\FaqOrderingService;
use Illuminate\Database\Eloquent\Collection;

class DeleteFaqAction
{
    public function __construct(
        private readonly FaqOrderingService $orderingService,
    ) {}

    public function execute(int $faqId): void
    {
        $this->orderingService->mutate(function (Collection $faqs) use ($faqId): void {
            $faq = $this->orderingService->findOrFail($faqs, $faqId);
            $remainingFaqs = $faqs->reject(static fn ($item) => (int) $item->id === $faqId)->values();
            $orderedIds = $remainingFaqs->pluck('id')->all();

            $faq->delete();

            if ($orderedIds !== []) {
                $this->orderingService->park($remainingFaqs);
                $this->orderingService->assign($orderedIds);
            }
        });
    }
}
