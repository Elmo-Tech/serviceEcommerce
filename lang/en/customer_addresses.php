<?php

declare(strict_types=1);

return [
    'listed' => 'Customer addresses retrieved successfully.',
    'created' => 'Customer address created successfully.',
    'retrieved' => 'Customer address retrieved successfully.',
    'updated' => 'Customer address updated successfully.',
    'deleted' => 'Customer address deleted successfully.',
    'restored' => 'Customer address restored successfully.',
    'default_updated' => 'Default customer address updated successfully.',
    'errors' => [
        'not_found' => 'The requested customer address was not found.',
        'already_exists' => 'An active matching address already exists for this customer.',
        'limit_exceeded' => 'This customer already has the maximum number of active addresses.',
        'default_required' => 'Select another default address instead of removing the current default directly.',
        'restore_conflict' => 'This address cannot be restored because it conflicts with an active saved address.',
    ],
];
