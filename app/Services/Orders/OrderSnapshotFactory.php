<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OrderItemAnswer;
use App\Models\Service;
use App\Models\ServiceOrderField;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use App\Support\Customers\CustomerPhoneFormatter;

class OrderSnapshotFactory
{
    /**
     * @param  array{name:string,email:?string,phone:string}|null  $submittedCustomer
     * @return array{customer_id:?int,customer_name:string,customer_phone:string,customer_email:?string}
     */
    public function customerSnapshot(Customer $customer, ?array $submittedCustomer = null): array
    {
        $customerPhone = CustomerPhoneFormatter::forResponse(
            $customer->phone,
            $customer->phone_normalized,
        ) ?? $customer->phone;

        return [
            'customer_id' => $customer->getKey(),
            'customer_name' => $submittedCustomer['name'] ?? $customer->name,
            'customer_phone' => $submittedCustomer['phone'] ?? $customerPhone,
            'customer_email' => $submittedCustomer['email'] ?? $customer->email,
        ];
    }

    /**
     * @return array{customer_address_id:?int,address_province:?string,address_city:?string,address_text:?string}
     */
    public function addressSnapshot(?CustomerAddress $address = null, ?array $submittedAddress = null): array
    {
        if ($address instanceof CustomerAddress) {
            return [
                'customer_address_id' => $address->getKey(),
                'address_province' => $address->province,
                'address_city' => $address->city,
                'address_text' => $address->address,
            ];
        }

        if (is_array($submittedAddress)) {
            return [
                'customer_address_id' => null,
                'address_province' => (string) $submittedAddress['province'],
                'address_city' => (string) $submittedAddress['city'],
                'address_text' => (string) $submittedAddress['address'],
            ];
        }

        return [
            'customer_address_id' => null,
            'address_province' => null,
            'address_city' => null,
            'address_text' => null,
        ];
    }

    /**
     * @return array{
     *   service_id:?int,
     *   service_name_ar:string,
     *   service_name_en:string,
     *   service_slug_ar:string,
     *   service_slug_en:string,
     *   price_type:int,
     *   base_price:string
     * }
     */
    public function serviceSnapshot(Service $service): array
    {
        return [
            'service_id' => $service->getKey(),
            'service_name_ar' => $service->name_ar,
            'service_name_en' => $service->name_en,
            'service_slug_ar' => $service->slug_ar,
            'service_slug_en' => $service->slug_en,
            'price_type' => $service->price_type->value,
            'base_price' => $service->base_price,
        ];
    }

    /**
     * @param  list<ServicePricingOptionValue>  $values
     * @return array{
     *   pricing_option_id:?int,
     *   option_name_ar:string,
     *   option_name_en:string,
     *   input_type:int,
     *   is_required:bool,
     *   values:list<array{pricing_option_value_id:?int,value_label_ar:string,value_label_en:string,price_adjustment:string}>
     * }
     */
    public function selectedOptionSnapshot(ServicePricingOption $option, array $values): array
    {
        return [
            'pricing_option_id' => $option->getKey(),
            'option_name_ar' => $option->name_ar,
            'option_name_en' => $option->name_en,
            'input_type' => $option->input_type->value,
            'is_required' => (bool) $option->is_required,
            'values' => array_map(
                fn (ServicePricingOptionValue $value): array => [
                    'pricing_option_value_id' => $value->getKey(),
                    'value_label_ar' => $value->label_ar,
                    'value_label_en' => $value->label_en,
                    'price_adjustment' => $value->price_adjustment,
                ],
                $values,
            ),
        ];
    }

    /**
     * @return array{
     *   service_order_field_id:?int,
     *   question_ar:string,
     *   question_en:string,
     *   field_type:int,
     *   is_required:bool,
     *   answer:string
     * }
     */
    public function answerSnapshot(ServiceOrderField $field, string $answer): array
    {
        return [
            'service_order_field_id' => $field->getKey(),
            'question_ar' => $field->label_ar,
            'question_en' => $field->label_en,
            'field_type' => $field->field_type->value,
            'is_required' => (bool) $field->is_required,
            'answer' => trim($answer),
        ];
    }

    /**
     * @return array{
     *   orderItemAnswerId:int,
     *   question_ar:string,
     *   question_en:string,
     *   field_type:int,
     *   is_required:bool,
     *   answer:string
     * }
     */
    public function persistedAnswerSnapshot(OrderItemAnswer $answer): array
    {
        return [
            'orderItemAnswerId' => (int) $answer->getKey(),
            'question_ar' => $answer->question_ar,
            'question_en' => $answer->question_en,
            'field_type' => $answer->field_type->value,
            'is_required' => (bool) $answer->is_required,
            'answer' => $answer->answer,
        ];
    }
}
