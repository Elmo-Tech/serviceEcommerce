<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\Faqs;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Faq
 */
class PublicFaqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isEnglish = app()->getLocale() === 'en';

        return [
            'question' => $isEnglish ? $this->question_en : $this->question_ar,
            'answer' => $isEnglish ? $this->answer_en : $this->answer_ar,
        ];
    }
}
