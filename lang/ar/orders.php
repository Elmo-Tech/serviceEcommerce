<?php

declare(strict_types=1);

return [
    'listed' => 'تم جلب الطلبات بنجاح.',
    'created' => 'تم إنشاء الطلب بنجاح.',
    'retrieved' => 'تم جلب الطلب بنجاح.',
    'updated' => 'تم تحديث الطلب بنجاح.',
    'deleted' => 'تم حذف الطلب بنجاح.',
    'status_updated' => 'تم تحديث حالة الطلب بنجاح.',
    'payment_updated' => 'تم تحديث ملخص الدفع بنجاح.',
    'errors' => [
        'order_number_sequence_exhausted' => 'تم استهلاك تسلسل أرقام الطلبات بالكامل لهذا اليوم.',
        'order_not_editable' => 'لم يعد مسموحًا بتعديل هذا الطلب.',
        'order_delete_not_allowed' => 'لا يمكن حذف هذا الطلب في حالته الحالية.',
        'order_requires_at_least_one_item' => 'يجب أن يحتوي الطلب على عنصر واحد على الأقل.',
        'customer_phone_invalid' => 'رقم هاتف العميل المدخل غير صالح.',
        'customer_source_invalid' => 'يجب إرسال مصدر عميل واحد فقط.',
        'customer_not_found' => 'العميل المطلوب غير موجود.',
        'customer_address_not_found' => 'عنوان العميل المطلوب غير موجود.',
        'idempotency_key_reused' => 'تم استخدام مفتاح منع التكرار هذا مسبقًا مع طلب مختلف.',
        'invalid_status_transition' => 'الانتقال المطلوب لحالة الطلب غير صالح.',
        'cancellation_reason_required' => 'يجب إدخال سبب الإلغاء.',
        'invalid_pricing_selection' => 'اختيارات التسعير المرسلة غير صالحة.',
        'pricing_option_not_found' => 'خيار التسعير المطلوب غير موجود.',
        'pricing_option_value_not_found' => 'قيمة خيار التسعير المطلوبة غير موجودة.',
        'service_unavailable' => 'الخدمة المطلوبة غير متاحة حاليًا.',
        'order_field_not_found' => 'حقل الطلب المطلوب غير موجود.',
        'required_order_field_missing' => 'هناك إجابة مطلوبة لحقل طلب مفقودة.',
        'required_service_attachment_missing' => 'يجب إرفاق ملف واحد على الأقل لهذه الخدمة.',
        'invalid_order_field_answer' => 'إجابة حقل الطلب المرسلة غير صالحة.',
        'invalid_discount' => 'بيانات الخصم المرسلة غير صالحة.',
    ],
];
