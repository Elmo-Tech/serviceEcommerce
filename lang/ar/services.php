<?php

declare(strict_types=1);

return [
    'listed' => 'تم استرجاع الخدمات بنجاح.',
    'created' => 'تم إنشاء الخدمة بنجاح.',
    'retrieved' => 'تم استرجاع الخدمة بنجاح.',
    'updated' => 'تم تحديث الخدمة بنجاح.',
    'deleted' => 'تم حذف الخدمة بنجاح.',
    'restored' => 'تمت استعادة الخدمة بنجاح.',
    'errors' => [
        'not_found' => 'الخدمة المطلوبة غير موجودة.',
        'not_deleted' => 'هذه الخدمة غير محذوفة.',
        'subcategory_requires_category' => 'يجب اختيار تصنيف رئيسي عند تحديد تصنيف فرعي.',
        'category_not_available' => 'التصنيف المحدد غير متاح.',
        'subcategory_not_available' => 'التصنيف الفرعي المحدد غير متاح.',
        'subcategory_parent_mismatch' => 'التصنيف الفرعي المحدد لا ينتمي إلى التصنيف الرئيسي المحدد.',
        'base_price_must_be_positive' => 'يجب أن يكون السعر الأساسي أكبر من صفر.',
        'activation_requires_complete_bilingual_content' => 'يجب أن تحتوي الخدمة على محتوى عربي وإنجليزي كامل قبل التفعيل.',
        'start_from_requires_active_values' => 'يجب أن يحتوي كل خيار تسعير نشط على قيمة نشطة واحدة على الأقل قبل التفعيل.',
        'specification_limit_reached' => 'تم الوصول إلى الحد الأقصى لمواصفات الخدمة.',
        'order_field_limit_reached' => 'تم الوصول إلى الحد الأقصى لحقول طلب الخدمة.',
        'pricing_option_limit_reached' => 'تم الوصول إلى الحد الأقصى لخيارات تسعير الخدمة.',
        'pricing_option_value_limit_reached' => 'تم الوصول إلى الحد الأقصى لقيم خيار التسعير.',
        'category_has_services' => 'لا يمكن حذف هذا التصنيف طالما توجد خدمات مرتبطة به.',
        'subcategory_has_services' => 'لا يمكن حذف هذا التصنيف الفرعي طالما توجد خدمات مرتبطة به.',
        'fixed_price_prohibits_pricing_options' => 'لا يسمح بخيارات التسعير للخدمات ذات السعر الثابت.',
        'pricing_option_not_found' => 'خيار التسعير المطلوب غير موجود.',
        'pricing_option_value_not_found' => 'قيمة خيار التسعير المطلوبة غير موجودة.',
    ],
];
