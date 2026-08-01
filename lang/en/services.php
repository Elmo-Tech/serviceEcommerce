<?php

declare(strict_types=1);

return [
    'listed' => 'Services retrieved successfully.',
    'created' => 'Service created successfully.',
    'retrieved' => 'Service retrieved successfully.',
    'updated' => 'Service updated successfully.',
    'deleted' => 'Service deleted successfully.',
    'restored' => 'Service restored successfully.',
    'errors' => [
        'not_found' => 'The requested service was not found.',
        'not_deleted' => 'This service is not deleted.',
        'subcategory_requires_category' => 'A category is required when a subcategory is selected.',
        'category_not_available' => 'The selected category is unavailable.',
        'subcategory_not_available' => 'The selected subcategory is unavailable.',
        'subcategory_parent_mismatch' => 'The selected subcategory does not belong to the selected category.',
        'base_price_must_be_positive' => 'The base price must be greater than zero.',
        'activation_requires_complete_bilingual_content' => 'The service must contain complete bilingual content before activation.',
        'start_from_requires_active_values' => 'Each active pricing option must include at least one active value before activation.',
        'specification_limit_reached' => 'The service specification limit has been reached.',
        'order_field_limit_reached' => 'The service order-field limit has been reached.',
        'pricing_option_limit_reached' => 'The service pricing-option limit has been reached.',
        'pricing_option_value_limit_reached' => 'The pricing-option value limit has been reached.',
        'category_has_services' => 'This category cannot be deleted while services still exist.',
        'subcategory_has_services' => 'This subcategory cannot be deleted while services still exist.',
        'fixed_price_prohibits_pricing_options' => 'Pricing options are not allowed for fixed-price services.',
        'pricing_option_not_found' => 'The requested pricing option was not found.',
        'pricing_option_value_not_found' => 'The requested pricing option value was not found.',
    ],
];
