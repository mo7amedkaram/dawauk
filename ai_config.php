<?php
// ai_config.php - ملف إعدادات الذكاء الاصطناعي

// مفتاح API الخاص بـ DeepSeek
// قم بتغيير هذه القيمة بمفتاح API الحقيقي الخاص بك
define('DEEPSEEK_API_KEY', 'sk-5446781b804f451d971e021178d13c85');

// نقطة نهاية API الخاصة بـ DeepSeek
define('DEEPSEEK_API_ENDPOINT', 'https://api.deepseek.com/v1/chat/completions');

// تكوين عام لمحرك البحث المعزز بالذكاء الاصطناعي
$AI_SEARCH_CONFIG = [
    // تفعيل أو تعطيل البحث المعزز بالذكاء الاصطناعي
    'enabled' => true,
    
    // الإعدادات المتقدمة
    'settings' => [
        // عدد الاقتراحات التي يتم عرضها للمستخدم
        'max_suggestions' => 5,
        
        // حجم ذاكرة المحادثة للبحث بالدردشة (عدد الرسائل المحفوظة)
        'chat_history_size' => 5,
        
        // نموذج الذكاء الاصطناعي المستخدم
        'model' => 'deepseek-chat',
        
        // تعيين مستوى الإبداع (1.0 = أكثر إبداعًا، 0.1 = أقل إبداعًا)
        'temperature' => 0.3,
        
        // تكوين البحث
        'search_mode' => 'hybrid', // 'ai', 'traditional', 'hybrid'
        
        // تكوين الواجهة
        'show_ai_badge' => true,  // عرض شارة "بحث ذكي" في الواجهة
        'show_ai_suggestions' => true, // عرض اقتراحات الذكاء الاصطناعي
        'show_ai_comment' => true,   // عرض تعليق الذكاء الاصطناعي على نتائج البحث
    ],
    
    // عبارات أو كلمات لتفعيل وضع البحث بالدردشة
    'chat_triggers' => [
        'أخبرني عن',
        'ما هو',
        'ما هي',
        'كيف يمكن',
        'كيف أستخدم',
        'أريد معلومات حول',
        'ابحث عن بديل',
        'أعاني من',
        'ما الفرق بين',
        'دواء يعالج'
    ]
];

// دالة مساعدة للتحقق من تفعيل البحث المعزز بالذكاء الاصطناعي
function isAISearchEnabled() {
    global $AI_SEARCH_CONFIG;
    return $AI_SEARCH_CONFIG['enabled'] && !empty(DEEPSEEK_API_KEY) && DEEPSEEK_API_KEY != 'your_deepseek_api_key_here';
}

// دالة مساعدة للتحقق مما إذا كان استعلام البحث يجب معالجته كاستعلام دردشة
function isChatSearchQuery($query) {
    global $AI_SEARCH_CONFIG;
    
    if (empty($query)) return false;
    
    foreach ($AI_SEARCH_CONFIG['chat_triggers'] as $trigger) {
        if (mb_stripos($query, $trigger) !== false) {
            return true;
        }
    }
    
    // التحقق من طول الاستعلام (إذا كان طويلًا، فمن المحتمل أن يكون سؤالًا بنمط الدردشة)
    $words = explode(' ', trim($query));
    if (count($words) > 5) {
        return true;
    }
    
    return false;
}