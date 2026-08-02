<?php

declare(strict_types=1);

return [
    'listed' => 'Orders retrieved successfully.',
    'created' => 'Order created successfully.',
    'retrieved' => 'Order retrieved successfully.',
    'updated' => 'Order updated successfully.',
    'deleted' => 'Order deleted successfully.',
    'status_updated' => 'Order status updated successfully.',
    'payment_updated' => 'Order payment updated successfully.',
    'errors' => [
        'order_number_sequence_exhausted' => 'The daily order-number sequence is exhausted.',
        'order_not_editable' => 'This order is no longer editable.',
        'order_delete_not_allowed' => 'This order cannot be deleted in its current state.',
        'order_requires_at_least_one_item' => 'The order must contain at least one item.',
        'customer_phone_invalid' => 'The provided customer phone number is invalid.',
        'customer_source_invalid' => 'Provide exactly one customer source.',
        'customer_not_found' => 'The requested customer was not found.',
        'customer_address_not_found' => 'The requested customer address was not found.',
        'idempotency_key_reused' => 'This idempotency key was already used with a different request.',
        'invalid_status_transition' => 'The requested order status transition is invalid.',
        'cancellation_reason_required' => 'A cancellation reason is required.',
        'invalid_pricing_selection' => 'The submitted pricing selection is invalid.',
        'pricing_option_not_found' => 'The requested pricing option was not found.',
        'pricing_option_value_not_found' => 'The requested pricing option value was not found.',
        'service_unavailable' => 'The requested service is currently unavailable.',
        'order_field_not_found' => 'The requested order field was not found.',
        'required_order_field_missing' => 'A required order field answer is missing.',
        'invalid_order_field_answer' => 'The submitted order field answer is invalid.',
        'invalid_discount' => 'The submitted discount is invalid.',
    ],
];
