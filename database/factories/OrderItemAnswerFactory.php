<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServiceOrderFieldType;
use App\Models\OrderItem;
use App\Models\OrderItemAnswer;
use App\Models\ServiceOrderField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemAnswer>
 */
class OrderItemAnswerFactory extends Factory
{
    protected $model = OrderItemAnswer::class;

    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'service_order_field_id' => ServiceOrderField::factory(),
            'question_ar' => 'اكتب المقاس المطلوب',
            'question_en' => 'Enter the required measurement',
            'field_type' => ServiceOrderFieldType::TEXT,
            'is_required' => true,
            'answer' => '200 x 100 cm',
        ];
    }
}
