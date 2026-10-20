<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'آزمون‌ساز هوشمند';
$string['aiquizgen:view'] = 'مشاهده آزمون‌ساز هوشمند';
$string['aiquizgen:generate'] = 'تولید آزمون با هوش مصنوعی';
$string['aiquizgen:manage'] = 'مدیریت تنظیمات آزمون‌ساز هوشمند';
$string['settings_general'] = 'تنظیمات عمومی';
$string['settings_general_desc'] = 'پیکربندی عمومی آزمون‌ساز مبتنی بر هسته هوش مصنوعی مودل.';
$string['enablecoursetab'] = 'نمایش تب اختصاصی در صفحه درس';
$string['enablecoursetab_desc'] = 'در صورت فعال بودن، تب «آزمون‌ساز هوشمند» در نوار ناوبری درس به اساتید نمایش داده می‌شود.';
$string['maxquestions'] = 'حداکثر تعداد سوال در هر درخواست';
$string['maxquestions_desc'] = 'سقف تعداد سوالاتی که استاد می‌تواند در یک مرحله از هوش مصنوعی درخواست کند.';
$string['settings_avalai'] = 'سرویس هوش مصنوعی AvalAI (دسترسی مستقیم)';
$string['settings_avalai_desc'] = 'اتصال به پلتفرم AvalAI جهت دسترسی به مدل‌های پیشرفته DeepSeek V4 Flash، Gemini Flash و OpenAI بدون نیاز به پراکسی خارجی.';
$string['engine_avalai'] = 'سرویس AvalAI (مدل پیشنهادی: DeepSeek V4.1 Flash)';
$string['avalai_api_key'] = 'کلید دسترسی (API Key) سرویس AvalAI';
$string['avalai_api_key_desc'] = 'کلید معتبر دریافتی از پنل avalai.ir یا avalai.org.';
$string['avalai_model'] = 'شناسه مدل انتخابی (Model ID)';
$string['avalai_model_desc'] = 'نام دقیق مدل در AvalAI (پیش‌فرض پیشنهادی: deepseek-v4.1-flash).';
$string['avalai_endpoint'] = 'آدرس اندپوینت AvalAI';
$string['avalai_endpoint_desc'] = 'آدرس پایه API (پیش‌فرض: https://api.avalai.org/v1).';
$string['avalai_api_error'] = 'خطا در ارتباط با سرویس AvalAI: {$a}';
$string['settings_mastra'] = 'موتور ارکستراسیون ایجنت مَسرا (Mastra AI Workflow)';
$string['settings_mastra_desc'] = 'پیکربندی اتصال به موتور ایجنت پیشرفته Mastra جهت اجرای پایدار، گام‌به‌گام و تایید تعاملی (HITL) آزمون‌سازی.';
$string['ai_engine'] = 'موتور پردازش آزمون‌ساز';
$string['ai_engine_desc'] = 'انتخاب موتور هوش مصنوعی: هایبرید مَسرا با فال‌بک خودکار به مودل، یا فقط هوش مصنوعی داخلی مودل.';
$string['engine_mastra_hybrid'] = 'ایجنت مَسرا (Mastra Workflow) با فال‌بک خودکار به مودل AI';
$string['engine_moodle_ai'] = 'فقط هسته هوش مصنوعی داخلی مودل (core_ai)';
$string['mastra_endpoint'] = 'آدرس اندپوینت ورک‌فلو مَسرا';
$string['mastra_endpoint_desc'] = 'آدرس کامل سرویس ایجنت مَسرا (مثال: http://localhost:3000/api/workflows/quiz-generation-workflow)';
$string['default_category_name'] = 'سوالات تولیدی هوش مصنوعی';
$string['default_quiz_intro'] = 'این آزمون توسط هوش مصنوعی طراحی شده و پس از بازبینی و تأیید استاد در سامانه قرار گرفته است.';
$string['publish_success_message'] = 'سوالات با موفقیت به بانک سوالات درس اضافه شدند و ماژول آزمون در درس فعال گردید.';
$string['ai_generation_failed'] = 'خطا در ارتباط با سرویس هوش مصنوعی: {$a}';
$string['json_parse_error'] = 'پاسخ هوش مصنوعی در قالب استاندارد JSON دریافت نشد. لطفاً مجدداً تلاش فرمایید.';
$string['invalid_questions_payload'] = 'داده‌های ارسالی سوالات نامعتبر است.';
