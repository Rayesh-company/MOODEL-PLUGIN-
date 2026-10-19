# 🎓 Moodle AI Quiz Generator Plugin (`local_aiquizgen`)

افزونه جامع و هوشمند آزمون‌ساز مودل متصل به زیرسیستم نیتیو هوش مصنوعی (`core_ai`).

---

## 🌟 ویژگی‌های کلیدی (Key Features)

1. **طراحی اختصاصی برای استاد (Teacher-First Workflow)**:
   - افزودن تب اختصاصی «آزمون‌ساز هوشمند» در نوار ناوبری درس (Secondary Navigation).
   - فرآیند ۴ مرحله‌ای شفاف، ساختاریافته و هدایت‌شده (Wizard).

2. **خواندن مستقیم منابع درسی (Direct Course Resource Ingestion)**:
   - اسکن خودکار فایل‌های درسی (PDF، اسلایدهای PPTX، جزوات DOCX) از طریق Moodle File Storage API (`mod_resource` و `mod_folder`).
   - امکان افزودن یادداشت‌ها و دستورالعمل‌های خاص تدریس توسط استاد.

3. **پشتیبانی کامل از ۶ نوع سوال استاندارد دانشگاهی**:
   - چندگزینه‌ای (Multiple Choice) با بازخورد تشریحی برای هر گزینه
   - صحیح / غلط (True/False) با توجیه علمی
   - کوتاه‌پاسخ (Short Answer) با کلمات کلیدی مجاز
   - تشریحی تحلیلی (Essay) همراه با راهنمای تصحیح استاد (Rubric)
   - تطبیقی / جورکردنی (Matching)
   - مرتب‌سازی (Ordering)

4. **پیش‌نمایش، ویرایش مستقیم و بازتولید تک‌سوال (Review & Re-prompting)**:
   - ویرایش درجا (Inline Editing) متن صورت سوال و گزینه‌ها.
   - چت‌باکس اختصاصی برای هر سوال جهت صدور دستور بازتولید به هوش مصنوعی (مثلاً «گزینه‌ها را سخت‌تر کن»).
   - حذف سوالات نامطلوب یا افزودن سوال دستی.

5. **انتشار دوگانه با کنترل کامل استاد (Two-tier Native Publishing)**:
   - ثبت سازمان‌یافته سوالات در دسته‌بندی مشخص در **بانک سوالات مودل (Question Bank)**.
   - ساخت خودکار ماژول آزمون (`mod_quiz`) در صفحه درس با تنظیمات زمانی، تعداد تلاش و نمره‌دهی.
   - آزمون تنها پس از فشردن دکمه «تأیید نهایی استاد» ساخته و منتشر می‌شود.

---

## 🏗️ ساختار ماژول‌ها و فایل‌ها (Architecture)

```
public/local/aiquizgen/
├── version.php                  # نگارش پلاگین و نیازمندی‌ها (Moodle 4.5+ / 6.0dev)
├── lib.php                      # توابع عمومی و هوک‌های ناوبری
├── settings.php                 # تنظیمات پلاگین در مدیریت سایت
├── view.php                     # صفحه اصلی آزمون‌ساز در درس
├── db/
│   ├── access.php               # دسترسی‌ها و قابلیت‌ها (Capabilities)
│   ├── hooks.php                # اتصال به secondary_extend جهت ایجاد تب درس
│   ├── services.php             # وب‌سرویس‌های AJAX
│   └── install.xml              # جداول لاگ نشست‌ها و سوالات
├── classes/
│   ├── hook_callbacks.php       # اجرای افزودن تب به ناوبری مودل
│   ├── quiz_builder.php         # ثبت سوالات در Question Bank و ساخت mod_quiz
│   ├── ai_service.php           # پل ارتباطی با core_ai\manager مودل
│   └── external/                # وب‌سرویس‌های ناهمگام
│       ├── scan_resources.php   # خواندن فایل‌های درس
│       ├── generate_questions.php # تولید سوالات با هوش مصنوعی
│       ├── reprompt_question.php  # بازتولید تک‌سوال با پرامپت استاد
│       └── publish_quiz.php     # ایجاد نهایی آزمون و بانک سوال
├── templates/
│   └── wizard.mustache          # قالب رابط کاربری ۴ مرحله‌ای
├── amd/src/
│   └── wizard.js                # منطق تعاملی فرانت‌اند مودل
├── lang/
│   ├── en/local_aiquizgen.php   # رشته‌های متنی انگلیسی
│   └── fa/local_aiquizgen.php   # رشته‌های متنی فارسی
└── docs/
    ├── WAYFINDER_MAP.md         # نقشه تصمیمات معماری (Wayfinder)
    └── quiz-wizard-prototype.html # پروتوتایپ تعاملی
```

---

## 🚀 نحوه نصب و راه‌اندازی (Installation)

1. پوشه پلاگین را در مسیر `local/aiquizgen` در مودل قرار دهید:
   ```bash
   git clone https://github.com/Rayesh-company/MOODEL-PLUGIN-.git public/local/aiquizgen
   ```
2. کش مودل را پاک کرده و ارتقای دیتابیس را انجام دهید:
   ```bash
   php admin/cli/upgrade.php
   php admin/cli/purge_caches.php
   ```
3. به صفحه هر درسی که دسترسی استاد (Editing Teacher) دارید مراجعه فرمایید؛ تب **«آزمون‌ساز هوشمند»** در بالای صفحه ظاهر خواهد شد.
