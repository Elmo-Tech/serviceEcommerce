<?php

declare(strict_types=1);

return [
    'listed' => 'تم استرجاع التصنيفات بنجاح.',
    'created' => 'تم إنشاء التصنيف بنجاح.',
    'retrieved' => 'تم استرجاع التصنيف بنجاح.',
    'updated' => 'تم تحديث التصنيف بنجاح.',
    'deleted' => 'تم حذف التصنيف بنجاح.',
    'restored' => 'تمت استعادة التصنيف بنجاح.',
    'reordered' => 'تمت إعادة ترتيب التصنيفات بنجاح.',
    'subcategories_listed' => 'تم استرجاع التصنيفات الفرعية بنجاح.',
    'subcategory_created' => 'تم إنشاء التصنيف الفرعي بنجاح.',
    'subcategory_retrieved' => 'تم استرجاع التصنيف الفرعي بنجاح.',
    'subcategory_updated' => 'تم تحديث التصنيف الفرعي بنجاح.',
    'subcategory_deleted' => 'تم حذف التصنيف الفرعي بنجاح.',
    'subcategory_restored' => 'تمت استعادة التصنيف الفرعي بنجاح.',
    'subcategories_reordered' => 'تمت إعادة ترتيب التصنيفات الفرعية بنجاح.',
    'validation' => [
        'name_ar_unique' => 'الاسم المُدخل في nameAr مستخدم بالفعل.',
        'name_en_unique' => 'الاسم المُدخل في nameEn مستخدم بالفعل.',
    ],
    'errors' => [
        'category_not_found' => 'التصنيف المطلوب غير موجود.',
        'subcategory_not_found' => 'التصنيف الفرعي المطلوب غير موجود.',
        'category_has_subcategories' => 'لا يمكن حذف هذا التصنيف طالما توجد تصنيفات فرعية مرتبطة به.',
        'subcategory_has_services' => 'لا يمكن حذف هذا التصنيف الفرعي طالما توجد خدمات مرتبطة به.',
        'parent_category_deleted' => 'يجب استعادة التصنيف الأب قبل استعادة هذا التصنيف الفرعي.',
        'category_not_deleted' => 'هذا التصنيف غير محذوف.',
        'subcategory_not_deleted' => 'هذا التصنيف الفرعي غير محذوف.',
    ],
];
