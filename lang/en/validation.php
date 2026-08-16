<?php

declare(strict_types=1);

return [
    'invalid_payload' => 'The submitted data is invalid.',
    'invalid_file_upload' => 'The uploaded file is invalid.',
    'invalid_image_upload' => 'The uploaded image must be a JPG, JPEG, PNG, or WebP file.',
    'file_too_large' => 'The uploaded file exceeds the maximum allowed size.',
    'password_same_as_current' => 'The new password must be different from the current password.',
    'required' => ':attribute is required.',
    'unique' => ':attribute has already been taken.',
    'mimes' => 'The :attribute must be a file of type: :values.',
    'attributes' => [
        'categoryId' => 'The category',
        'subcategoryId' => 'The subcategory',
        'nameAr' => 'The arabic name',
        'nameEn' => 'The english name',
        'shortDescriptionAr' => 'The arabic short description',
        'shortDescriptionEn' => 'The english short description',
        'descriptionAr' => 'The arabic description',
        'descriptionEn' => 'The english description',
        'slugAr' => 'The arabic slug',
        'slugEn' => 'The english slug',
        'productionTimeAr' => 'The arabic production time',
        'productionTimeEn' => 'The english production time',
        'priceType' => 'The price type',
        'basePrice' => 'The base price',
        'isActive' => 'The active status',
        'isAvailable' => 'The availability status',
        'isAttachmentRequired' => 'The attachment requirement',
    ],
];
