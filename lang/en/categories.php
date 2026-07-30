<?php

declare(strict_types=1);

return [
    'listed' => 'Categories retrieved successfully.',
    'created' => 'Category created successfully.',
    'retrieved' => 'Category retrieved successfully.',
    'updated' => 'Category updated successfully.',
    'deleted' => 'Category deleted successfully.',
    'restored' => 'Category restored successfully.',
    'reordered' => 'Categories reordered successfully.',
    'subcategories_listed' => 'Subcategories retrieved successfully.',
    'subcategory_created' => 'Subcategory created successfully.',
    'subcategory_retrieved' => 'Subcategory retrieved successfully.',
    'subcategory_updated' => 'Subcategory updated successfully.',
    'subcategory_deleted' => 'Subcategory deleted successfully.',
    'subcategory_restored' => 'Subcategory restored successfully.',
    'subcategories_reordered' => 'Subcategories reordered successfully.',
    'errors' => [
        'category_not_found' => 'The requested category was not found.',
        'subcategory_not_found' => 'The requested subcategory was not found.',
        'category_has_subcategories' => 'This category cannot be deleted while subcategories still exist.',
        'subcategory_has_services' => 'This subcategory cannot be deleted while services still exist.',
        'parent_category_deleted' => 'The parent category must be restored before restoring this subcategory.',
        'category_not_deleted' => 'This category is not deleted.',
        'subcategory_not_deleted' => 'This subcategory is not deleted.',
    ],
];
