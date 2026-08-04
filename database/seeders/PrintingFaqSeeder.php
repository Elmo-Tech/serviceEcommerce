<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Services\Faqs\FaqOrderingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class PrintingFaqSeeder extends Seeder
{
    public function __construct(
        private readonly FaqOrderingService $orderingService,
    ) {}

    public function run(): void
    {
        $this->orderingService->mutate(function (Collection $persistedFaqs): void {
            foreach ($this->faqs() as $faqData) {
                $exists = $persistedFaqs->contains(
                    fn (Faq $faq): bool => $faq->question_ar === $faqData['question_ar']
                        || $faq->question_en === $faqData['question_en'],
                );

                if ($exists) {
                    continue;
                }

                $this->orderingService->ensureUniqueQuestions(
                    $persistedFaqs,
                    $faqData['question_ar'],
                    $faqData['question_en'],
                );

                $faq = Faq::query()->create([
                    ...$faqData,
                    'is_active' => true,
                    'position' => $persistedFaqs->count() + 1,
                ]);

                $persistedFaqs->push($faq);
            }
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function faqs(): array
    {
        return [
            $this->faq('كيف أطلب خدمة طباعة؟', 'How do I order a printing service?', 'اختر الخدمة المناسبة، وحدد الخيارات والكمية، وارفع الملفات المطلوبة ثم أدخل بيانات التواصل والعنوان لإرسال الطلب.', 'Choose the service, select its options and quantity, upload the required files, then enter your contact and address details to submit the order.'),
            $this->faq('هل الأسعار المعروضة نهائية؟', 'Are the displayed prices final?', 'يعتمد ذلك على نوع الخدمة. السعر الثابت يكون محدداً، بينما الخدمات التي تبدأ من سعر معين تُحسب حسب المقاس والكمية والخامة والتشطيب.', 'It depends on the service. Fixed prices are defined, while starting-from services are calculated according to size, quantity, material, and finishing.'),
            $this->faq('ما صيغ الملفات المقبولة للطباعة؟', 'Which file formats are accepted for printing?', 'يمكنك إرفاق ملفات PNG أو JPEG أو WebP أو PDF أو DOC أو DOCX. للحصول على أفضل جودة يُفضل إرسال PDF عالي الدقة.', 'You can attach PNG, JPEG, WebP, PDF, DOC, or DOCX files. A high-resolution PDF is preferred for the best print quality.'),
            $this->faq('هل يمكنني طلب تصميم قبل الطباعة؟', 'Can I request design work before printing?', 'يمكن توضيح احتياجك للتصميم داخل ملاحظات الطلب وإرفاق الشعار والمحتوى المتاح، وسيتواصل معك فريقنا لتأكيد التفاصيل والتكلفة.', 'Describe your design needs in the order notes and attach the available logo and content. Our team will contact you to confirm details and cost.'),
            $this->faq('كم تستغرق مدة تنفيذ الطلب؟', 'How long does order production take?', 'تختلف مدة التنفيذ حسب نوع الخدمة والكمية والتشطيب، وتظهر المدة التقديرية داخل صفحة كل خدمة قبل إرسال الطلب.', 'Production time varies by service, quantity, and finishing. The estimated duration is shown on each service page before ordering.'),
            $this->faq('هل يمكن تعديل الطلب بعد إرساله؟', 'Can I change an order after submitting it?', 'تواصل معنا بأسرع وقت مع رقم الطلب. يمكن إجراء التعديل إذا لم يبدأ التنفيذ، وقد تتغير التكلفة أو مدة التسليم حسب التعديل.', 'Contact us as soon as possible with the order number. Changes may be possible before production starts and can affect price or delivery time.'),
            $this->faq('هل توفرون عينات قبل طباعة الكمية؟', 'Do you provide samples before printing the full quantity?', 'تتوفر العينة لبعض الخدمات حسب الخامة والكمية. اذكر طلب العينة في الملاحظات وسيتواصل معك الفريق لتأكيد إمكانية التنفيذ وتكلفته.', 'Samples are available for selected services depending on material and quantity. Request one in the notes and our team will confirm availability and cost.'),
            $this->faq('كيف أتابع حالة طلبي؟', 'How can I follow my order status?', 'يمكنك التواصل معنا باستخدام رقم الطلب، وسيقوم فريق خدمة العملاء بإبلاغك بحالة التجهيز والموعد المتوقع للتسليم.', 'Contact us using your order number and customer service will provide the production status and expected delivery date.'),
            $this->faq('هل تقدمون خدمة التوصيل؟', 'Do you provide delivery?', 'يتم تأكيد إمكانية التوصيل وتكلفته حسب المحافظة والمدينة وحجم الطلب عند مراجعة تفاصيل الطلب مع فريقنا.', 'Delivery availability and cost are confirmed according to governorate, city, and order size when our team reviews the order.'),
            $this->faq('ماذا يحدث إذا كانت جودة الملف المرفوع غير مناسبة؟', 'What happens if the uploaded file quality is unsuitable?', 'يراجع الفريق الملفات قبل بدء الطباعة، وإذا كانت الدقة أو الألوان أو المقاسات غير مناسبة سنتواصل معك لطلب ملف بديل أو اعتماد التعديل.', 'Our team reviews files before printing. If resolution, colors, or dimensions are unsuitable, we will contact you for a replacement file or approval of an adjustment.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function faq(string $questionAr, string $questionEn, string $answerAr, string $answerEn): array
    {
        return [
            'question_ar' => $questionAr,
            'question_en' => $questionEn,
            'answer_ar' => $answerAr,
            'answer_en' => $answerEn,
        ];
    }
}
