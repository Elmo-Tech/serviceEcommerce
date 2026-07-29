<?php

declare(strict_types=1);

return [
    'listed' => 'Customers retrieved successfully.',
    'created' => 'Customer created successfully.',
    'retrieved' => 'Customer retrieved successfully.',
    'updated' => 'Customer updated successfully.',
    'deleted' => 'Customer deleted successfully.',
    'restored' => 'Customer restored successfully.',
    'errors' => [
        'not_found' => 'The requested customer was not found.',
        'phone_invalid' => 'The provided phone number is invalid.',
        'phone_exists' => 'Another customer already uses this phone number.',
        'email_exists' => 'Another customer already uses this email address.',
        'deleted' => 'This customer must be restored before managing addresses.',
    ],
];
