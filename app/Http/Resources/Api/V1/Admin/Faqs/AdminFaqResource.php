<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Faqs;

use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Faq
 */
class AdminFaqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'questionAr' => $this->question_ar,
            'questionEn' => $this->question_en,
            'answerAr' => $this->answer_ar,
            'answerEn' => $this->answer_en,
            'isActive' => (int) $this->is_active,
            'position' => (int) $this->position,
        ];
    }
}
