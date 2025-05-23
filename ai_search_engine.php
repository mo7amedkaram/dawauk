<?php
// ai_search_engine.php - محرك البحث المعزز بالذكاء الاصطناعي باستخدام DeepSeek API

/**
 * Class AISearchEngine
 * محرك البحث المعزز بالذكاء الاصطناعي للأدوية
 */
class AISearchEngine {
    private $db;
    private $deepseekApiKey;
    private $deepseekApiEndpoint;
    private $searchEngine; // محرك البحث التقليدي لاستخدامه كنسخة احتياطية

    /**
     * AISearchEngine constructor.
     * 
     * @param mixed $db كائن قاعدة البيانات
     * @param string $apiKey مفتاح API الخاص بـ DeepSeek
     * @param string $endpoint نقطة نهاية API (اختياري، له قيمة افتراضية)
     */
    public function __construct($db, $apiKey, $endpoint = 'https://api.deepseek.com/v1/chat/completions') {
        $this->db = $db;
        $this->deepseekApiKey = $apiKey;
        $this->deepseekApiEndpoint = $endpoint;
        
        // إنشاء محرك البحث التقليدي للاستخدام كنسخة احتياطية
        if (class_exists('SearchEngine')) {
            $this->searchEngine = new SearchEngine($db);
        }
    }

    /**
     * إجراء بحث معزز بالذكاء الاصطناعي
     * 
     * @param string $query استعلام البحث
     * @param array $filters فلاتر البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @param bool $useAI استخدام الذكاء الاصطناعي (اختياري، افتراضي: true)
     * @return array نتائج البحث
     */
  public function search($query, $filters = [], $page = 1, $limit = 12, $useAI = true) {
    // إذا كان الاستعلام فارغًا أو تم تعطيل الذكاء الاصطناعي، استخدم البحث التقليدي
    if (empty($query) || !$useAI || empty($this->deepseekApiKey)) {
        return $this->fallbackSearch($query, $filters, $page, $limit);
    }

    // التعامل مع الاستعلامات القصيرة جدًا من خلال البحث التقليدي
    if (strlen($query) <= 3 && !is_numeric($query)) {
        return $this->enhanceTraditionalSearch($query, $filters, $page, $limit);
    }

    try {
        // تحليل الاستعلام (مع التخزين المؤقت المدمج)
        $queryAnalysis = $this->analyzeQuery($query);
        
        // التعرف على ما إذا كان المستخدم يبحث عن علاج لحالة صحية معينة
        $isHealthConditionSearch = $this->isHealthConditionSearch($queryAnalysis);
        
        // تنفيذ البحث المناسب
        if ($isHealthConditionSearch) {
            // استخدم البحث الموجه حسب الحالة الصحية
            $healthCondition = $this->extractHealthCondition($queryAnalysis);
            $searchParams = $this->buildHealthConditionSearchParams($healthCondition, $filters);
        } else {
            // بناء استعلام قاعدة البيانات استنادًا إلى تحليل الذكاء الاصطناعي
            $searchParams = $this->buildSearchParams($queryAnalysis, $filters);
        }
        
        // تنفيذ البحث في قاعدة البيانات
        $results = $this->executeSearch($searchParams, $page, $limit);
        
        // إضافة المعلومات التفصيلية للأدوية (بدون تفاصيل زائدة لتسريع العملية)
        $results = $this->enrichMedicationsWithBasicDetails($results);
        
        // إضافة اقتراحات ومعلومات مفيدة من الذكاء الاصطناعي
        $enhancedResults = $this->enhanceResults($results, $queryAnalysis, $query);
        
        // إضافة نصائح طبية للحالة الصحية إذا كان البحث عن علاج
        if ($isHealthConditionSearch) {
            $enhancedResults['health_condition_info'] = $this->getHealthConditionInfoCached($healthCondition);
        }
        
        return $enhancedResults;
    } catch (Exception $e) {
        // تسجيل الخطأ واستخدام البحث التقليدي كخطة بديلة
        error_log("AISearchEngine error: " . $e->getMessage());
        return $this->fallbackSearch($query, $filters, $page, $limit);
    }
}





/**
 * نسخة مبسطة من إثراء الأدوية بالتفاصيل
 */
private function enrichMedicationsWithBasicDetails($results) {
    if (empty($results['results'])) {
        return $results;
    }
    
    // افترض الحد الأدنى من المعلومات لتسريع العملية
    foreach ($results['results'] as &$medication) {
        // المعلومات الأساسية فقط
        $medication['details'] = $this->getBasicMedicationDetails($medication['id']);
        
        // إضافة روابط إلى البدائل المشابهة والشركات المصنعة
        $medication['links'] = $this->generateMedicationLinks($medication);
    }
    
    return $results;
}

/**
 * استرداد تفاصيل أساسية سريعة للدواء بدون معالجة إضافية
 */
private function getBasicMedicationDetails($medicationId) {
    // محاولة الحصول على التفاصيل من قاعدة البيانات أولاً
    $details = $this->db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$medicationId]);
    
    if ($details) {
        return $details;
    }
    
    // إذا لم تكن التفاصيل متوفرة، أعد هيكل بيانات فارغ
    return [
        'indications' => 'غير متوفر',
        'dosage' => 'غير متوفر',
        'side_effects' => 'غير متوفر',
        'contraindications' => 'غير متوفر',
        'interactions' => 'غير متوفر',
        'storage_info' => 'غير متوفر',
        'usage_instructions' => 'غير متوفر'
    ];
}

/**
 * نسخة مخزنة مؤقتًا من معلومات الحالة الصحية
 */
private function getHealthConditionInfoCached($healthCondition) {
    static $cache = [];
    
    // التحقق من وجود المعلومات في الذاكرة المؤقتة
    $cacheKey = md5($healthCondition);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    // الحصول على المعلومات من API أو من الوظيفة الأصلية
    $info = $this->getHealthConditionInfo($healthCondition);
    
    // حفظ في الذاكرة المؤقتة
    $cache[$cacheKey] = $info;
    
    return $info;
}
    
    /**
     * إنشاء روابط للأدوية المشابهة والمعلومات ذات الصلة
     * 
     * @param array $medication بيانات الدواء
     * @return array روابط ذات صلة
     */
    private function generateMedicationLinks($medication) {
        $links = [];
        
        // بدائل بنفس المادة الفعالة
        $links['alternatives'] = "search.php?scientific_name=" . urlencode($medication['scientific_name']);
        
        // أدوية من نفس الشركة
        $links['company'] = "search.php?company=" . urlencode($medication['company']);
        
        // أدوية من نفس التصنيف
        $links['category'] = "search.php?category=" . urlencode($medication['category']);
        
        // رابط للمقارنة
        $links['compare'] = "compare.php?ids=" . $medication['id'];
        
        return $links;
    }
    
    /**
     * تصنيف أغراض استخدام الدواء إلى فئات واضحة
     * 
     * @param array $medication بيانات الدواء
     * @return array تصنيفات الاستخدام
     */
    private function classifyUsagePurposes($medication) {
        $categories = [];
        $indications = $medication['details']['indications'] ?? '';
        
        // فحص استخدامات الدواء وتصنيفها
        $usagePatterns = [
            'علاج الألم' => ['مسكن', 'تخفيف الألم', 'الصداع', 'آلام'],
            'خافض للحرارة' => ['خافض حرارة', 'خفض درجة الحرارة', 'الحمى'],
            'مضاد للالتهاب' => ['مضاد للالتهاب', 'التهاب', 'روماتيزم'],
            'مضاد حيوي' => ['مضاد حيوي', 'التهاب بكتيري', 'عدوى بكتيرية'],
            'علاج القلب' => ['ضغط الدم', 'القلب', 'الأوعية الدموية', 'الذبحة'],
            'علاج السكري' => ['سكري', 'سكر الدم', 'انسولين'],
            'علاج الجهاز التنفسي' => ['ربو', 'سعال', 'تنفس', 'برد', 'انفلونزا'],
            'علاج الجهاز الهضمي' => ['هضم', 'معدة', 'حموضة', 'إسهال', 'إمساك'],
            'مضاد للحساسية' => ['حساسية', 'هستامين', 'طفح جلدي'],
            'مهدئ ومنوم' => ['قلق', 'توتر', 'أرق', 'نوم']
        ];
        
        foreach ($usagePatterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_stripos($indications, $keyword) !== false) {
                    $categories[] = $category;
                    break;
                }
            }
        }
        
        // إضافة التصنيف من بيانات الدواء مباشرة إذا لم يتم العثور على تصنيف
        if (empty($categories) && !empty($medication['category'])) {
            $categories[] = $medication['category'];
        }
        
        return array_unique($categories);
    }/**
     * تصنيف الآثار الجانبية حسب شدتها
     * 
     * @param array $medication بيانات الدواء
     * @return array تصنيف الآثار الجانبية
     */
    private function classifySideEffects($medication) {
        $classification = [
            'mild' => [],
            'moderate' => [],
            'severe' => []
        ];
        
        $sideEffects = $medication['details']['side_effects'] ?? '';
        
        // كلمات تدل على الآثار الجانبية الخفيفة
        $mildKeywords = ['خفيف', 'مؤقت', 'شائع', 'بسيط', 'عابر', 'نادر', 'صداع', 'غثيان بسيط', 'دوخة خفيفة', 'نعاس'];
        
        // كلمات تدل على الآثار الجانبية المتوسطة
        $moderateKeywords = ['متوسط', 'تقيؤ', 'إسهال', 'طفح جلدي', 'حكة', 'دوخة شديدة', 'صداع شديد'];
        
        // كلمات تدل على الآثار الجانبية الشديدة
        $severeKeywords = ['خطير', 'شديد', 'نادر جداً', 'تشنجات', 'فقدان الوعي', 'صعوبة في التنفس', 'تورم', 'حساسية مفرطة', 'طارئ'];
        
        // استخراج الآثار الجانبية وتصنيفها
        $effectLines = preg_split('/[.،؛\n]+/u', $sideEffects);
        
        foreach ($effectLines as $effect) {
            $effect = trim($effect);
            if (empty($effect)) continue;
            
            $severity = 'mild'; // افتراضي
            
            // تحديد شدة الأثر الجانبي
            foreach ($severeKeywords as $keyword) {
                if (mb_stripos($effect, $keyword) !== false) {
                    $severity = 'severe';
                    break;
                }
            }
            
            if ($severity === 'mild') {
                foreach ($moderateKeywords as $keyword) {
                    if (mb_stripos($effect, $keyword) !== false) {
                        $severity = 'moderate';
                        break;
                    }
                }
            }
            
            $classification[$severity][] = $effect;
        }
        
        return $classification;
    }
    
    /**
     * توليد معلومات الاستخدام الآمن والفعال للدواء
     * 
     * @param array $medication بيانات الدواء
     * @return array معلومات السلامة
     */
    private function generateSafetyInfo($medication) {
        $safetyInfo = [
            'pregnancy_safety' => 'غير معروف',
            'driving_safety' => 'غير معروف',
            'alcohol_interaction' => 'غير معروف',
            'storage_recommendations' => 'يحفظ في درجة حرارة الغرفة بعيداً عن الرطوبة والحرارة المباشرة',
            'special_populations' => []
        ];
        
        $contraindications = $medication['details']['contraindications'] ?? '';
        $interactions = $medication['details']['interactions'] ?? '';
        $usageInstructions = $medication['details']['usage_instructions'] ?? '';
        $storageInfo = $medication['details']['storage_info'] ?? '';
        
        // تحديد سلامة الاستخدام أثناء الحمل
        if (mb_stripos($contraindications, 'حامل') !== false || 
            mb_stripos($contraindications, 'الحمل') !== false) {
            $safetyInfo['pregnancy_safety'] = 'غير آمن خلال فترة الحمل';
        } elseif (mb_stripos($contraindications, 'فئة ب') !== false || 
                 mb_stripos($contraindications, 'فئة c') !== false) {
            $safetyInfo['pregnancy_safety'] = 'يستخدم بحذر خلال فترة الحمل وتحت إشراف الطبيب';
        }
        
        // تحديد سلامة القيادة
        if (mb_stripos($usageInstructions, 'نعاس') !== false || 
            mb_stripos($usageInstructions, 'دوخة') !== false || 
            mb_stripos($usageInstructions, 'قيادة') !== false) {
            $safetyInfo['driving_safety'] = 'قد يؤثر على القدرة على القيادة أو تشغيل الآلات';
        } else {
            $safetyInfo['driving_safety'] = 'لا يؤثر عادة على القدرة على القيادة';
        }
        
        // تحديد التفاعل مع الكحول
        if (mb_stripos($interactions, 'كحول') !== false || 
            mb_stripos($interactions, 'مشروبات كحولية') !== false) {
            $safetyInfo['alcohol_interaction'] = 'يجب تجنب الكحول أثناء تناول هذا الدواء';
        }
        
        // تحديد توصيات التخزين
        if (!empty($storageInfo)) {
            $safetyInfo['storage_recommendations'] = $storageInfo;
        }
        
        // تحديد الفئات الخاصة
        if (mb_stripos($contraindications, 'كلى') !== false || 
            mb_stripos($contraindications, 'كلوي') !== false) {
            $safetyInfo['special_populations'][] = 'مرضى الكلى';
        }
        
        if (mb_stripos($contraindications, 'كبد') !== false || 
            mb_stripos($contraindications, 'كبدي') !== false) {
            $safetyInfo['special_populations'][] = 'مرضى الكبد';
        }
        
        if (mb_stripos($contraindications, 'قلب') !== false || 
            mb_stripos($contraindications, 'قلبي') !== false) {
            $safetyInfo['special_populations'][] = 'مرضى القلب';
        }
        
        return $safetyInfo;
    }
    
    /**
     * توليد تفاصيل الدواء باستخدام الذكاء الاصطناعي
     * 
     * @param array $medication بيانات الدواء
     * @return array تفاصيل الدواء
     */
    private function generateMedicationDetails($medication) {
        try {
            // البحث أولاً عن تفاصيل الدواء في قاعدة البيانات
            $details = $this->db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$medication['id']]);
            
            if ($details) {
                return $details;
            }
            
            // إذا لم تتوفر التفاصيل، قم بتوليدها باستخدام الذكاء الاصطناعي
            $prompt = $this->buildMedicationDetailsPrompt($medication);
            
            $data = [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'أنت خبير صيدلاني متخصص في المعلومات الدوائية. مهمتك توفير معلومات دقيقة وشاملة حول الأدوية استناداً إلى المعلومات المتاحة عن الدواء.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.2,
                'response_format' => [
                    'type' => 'json_object'
                ]
            ];
            
            $response = $this->callDeepSeekAPI($data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                $detailsResult = json_decode($content, true);
                
                // التحقق من صحة التنسيق
                if (json_last_error() === JSON_ERROR_NONE) {
                    // حفظ التفاصيل في قاعدة البيانات للاستخدام المستقبلي
                    $this->saveMedicationDetails($medication['id'], $detailsResult);
                    
                    return $detailsResult;
                }
            }
            
            // إذا فشل توليد التفاصيل، أعد هيكل بيانات فارغ
            return [
                'indications' => 'غير متوفر',
                'dosage' => 'غير متوفر',
                'side_effects' => 'غير متوفر',
                'contraindications' => 'غير متوفر',
                'interactions' => 'غير متوفر',
                'storage_info' => 'غير متوفر',
                'usage_instructions' => 'غير متوفر'
            ];
        } catch (Exception $e) {
            error_log("Failed to generate medication details: " . $e->getMessage());
            
            // إرجاع هيكل بيانات فارغ في حالة الخطأ
            return [
                'indications' => 'غير متوفر',
                'dosage' => 'غير متوفر',
                'side_effects' => 'غير متوفر',
                'contraindications' => 'غير متوفر',
                'interactions' => 'غير متوفر',
                'storage_info' => 'غير متوفر',
                'usage_instructions' => 'غير متوفر'
            ];
        }
    }
    
    /**
     * بناء الـ Prompt لتوليد تفاصيل الدواء
     * 
     * @param array $medication بيانات الدواء
     * @return string الـ prompt المستخدم للتوليد
     */
    private function buildMedicationDetailsPrompt($medication) {
        return <<<PROMPT
استناداً إلى المعلومات التالية حول الدواء، قم بتوليد تفاصيل شاملة ودقيقة عنه:

اسم الدواء التجاري: {$medication['trade_name']}
المادة الفعالة: {$medication['scientific_name']}
الشركة المنتجة: {$medication['company']}
التصنيف: {$medication['category']}
الوصف الإضافي: {$medication['description']}

قدم المعلومات التالية بتنسيق JSON:
1. دواعي الاستعمال (لماذا يُستخدم هذا الدواء وللحالات المرضية التي يعالجها)
2. الجرعات (كيفية استخدام الدواء للبالغين والأطفال إن أمكن)
3. الآثار الجانبية (الشائعة والنادرة)
4. موانع الاستعمال (متى يجب تجنب استخدام هذا الدواء)
5. التفاعلات الدوائية (مع أدوية أخرى، أطعمة، أو حالات صحية)
6. ظروف التخزين (كيفية حفظ الدواء)
7. إرشادات الاستخدام (توجيهات خاصة حول استخدام الدواء)

أعد النتائج بهذا التنسيق:
{
    "indications": "دواعي الاستعمال",
    "dosage": "الجرعات",
    "side_effects": "الآثار الجانبية",
    "contraindications": "موانع الاستعمال",
    "interactions": "التفاعلات الدوائية",
    "storage_info": "ظروف التخزين",
    "usage_instructions": "إرشادات الاستخدام"
}

ملاحظة مهمة: قدم المعلومات بشكل موجز ودقيق. لا تضيف أي تحذيرات أو توصيات إضافية خارج السياق. تأكد من تجنب المعلومات غير المؤكدة أو غير الدقيقة. افترض أن المعلومات ستراجع من قبل صيدلي أو طبيب قبل استخدامها.
PROMPT;
    }
    
    /**
     * حفظ تفاصيل الدواء في قاعدة البيانات
     * 
     * @param int $medicationId معرف الدواء
     * @param array $details تفاصيل الدواء
     * @return bool نجاح العملية
     */
    private function saveMedicationDetails($medicationId, $details) {
        try {
            // التحقق مما إذا كانت التفاصيل موجودة بالفعل
            $existingDetails = $this->db->fetchOne("SELECT id FROM medication_details WHERE medication_id = ?", [$medicationId]);
            
            $data = [
                'indications' => $details['indications'] ?? 'غير متوفر',
                'dosage' => $details['dosage'] ?? 'غير متوفر',
                'side_effects' => $details['side_effects'] ?? 'غير متوفر',
                'contraindications' => $details['contraindications'] ?? 'غير متوفر',
                'interactions' => $details['interactions'] ?? 'غير متوفر',
                'storage_info' => $details['storage_info'] ?? 'غير متوفر',
                'usage_instructions' => $details['usage_instructions'] ?? 'غير متوفر',
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            if ($existingDetails) {
                // تحديث التفاصيل الموجودة
                $this->db->update('medication_details', $data, 'medication_id = ?', [$medicationId]);
            } else {
                // إضافة تفاصيل جديدة
                $data['medication_id'] = $medicationId;
                $this->db->insert('medication_details', $data);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to save medication details: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * استدعاء DeepSeek API
     * 
     * @param array $data بيانات الاستدعاء
     * @return array استجابة API
     * @throws Exception
     */
private function callDeepSeekAPI($data) {
    $ch = curl_init($this->deepseekApiEndpoint);
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $this->deepseekApiKey
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    // تقليل المهلة إلى 10 ثوانٍ بدلاً من 30
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // إضافة خيارات لتسريع الاتصال
    curl_setopt($ch, CURLOPT_TCP_FASTOPEN, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        // استخدام البحث التقليدي في حالة فشل الاتصال
        return ['status' => 'error', 'message' => 'فشل الاتصال بـ API: ' . curl_error($ch)];
    }
    
    curl_close($ch);
    
    if ($httpCode != 200) {
        // استخدام البحث التقليدي في حالة خطأ في API
        return ['status' => 'error', 'message' => "رمز الخطأ من API: $httpCode, الاستجابة: $response"];
    }
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'message' => 'فشل في فك تشفير استجابة API: ' . json_last_error_msg()];
    }
    
    return $decodedResponse;
}
    /**
     * البحث باستخدام اللغة الطبيعية (بحث شبيه بالدردشة) - محسن لدقة المعلومات الطبية
     * 
     * @param string $query استعلام البحث
     * @param array $userHistory سجل المحادثة السابقة
     * @param array $filters فلاتر البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @return array نتائج البحث مع إجابة الدردشة
     */
    public function chatSearch($query, $userHistory = [], $filters = [], $page = 1, $limit = 12) {
        try {
            // تحليل الاستعلام أولاً
            $queryAnalysis = $this->analyzeQuery($query);
            
            // التحقق مما إذا كان الاستعلام يتعلق بحالة صحية
            $isHealthCondition = $this->isHealthConditionSearch($queryAnalysis);
            
            // البحث في قاعدة البيانات بناءً على تحليل الاستعلام
            if ($isHealthCondition) {
                $healthCondition = $this->extractHealthCondition($queryAnalysis);
                $searchParams = $this->buildHealthConditionSearchParams($healthCondition, $filters);
            } else {
                $searchParams = $this->buildSearchParams($queryAnalysis, $filters);
            }
            
            $results = $this->executeSearch($searchParams, $page, $limit);
            
            // إضافة تحليل الاستعلام للنتائج
            $results['query_analysis'] = $queryAnalysis;
            
            // إثراء نتائج البحث بالتفاصيل
            $results = $this->enrichMedicationsWithDetails($results);
            
            // إعداد معلومات السياق للرد على الاستعلام بنمط الدردشة
            $context = $this->prepareResponseContext($results, $query, $userHistory, $isHealthCondition);
            
            // توليد الرد المخصص بأسلوب الدردشة
            $chatResponse = $this->generateChatResponse($context);
            
            // إضافة الرد المخصص إلى النتائج
            $results['chat_response'] = $chatResponse;
            
            // إضافة معلومات إضافية عن الحالة الصحية إذا كان البحث متعلقًا بحالة صحية
            if ($isHealthCondition) {
                $healthCondition = $this->extractHealthCondition($queryAnalysis);
                $results['health_condition_info'] = $this->getHealthConditionInfo($healthCondition);
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Chat search error: " . $e->getMessage());
            
             // إعادة نتائج البحث العادي مع رسالة خطأ
            $fallbackResults = $this->fallbackSearch($query, $filters, $page, $limit);
            $fallbackResults['chat_response'] = [
                'text' => 'عذراً، لم أتمكن من فهم استعلامك بشكل كامل. إليك نتائج البحث العادي.',
                'error' => true
            ];
            
            return $fallbackResults;
        }
    }
    
    /**
     * إعداد سياق الاستجابة المحسن للرد على الاستعلام بنمط الدردشة
     * 
     * @param array $results نتائج البحث
     * @param string $query استعلام البحث
     * @param array $userHistory سجل المحادثة السابقة
     * @param bool $isHealthCondition ما إذا كان الاستعلام متعلقًا بحالة صحية
     * @return string سياق الاستجابة
     */
    private function prepareResponseContext($results, $query, $userHistory, $isHealthCondition = false) {
        $totalResults = $results['total'];
        $medications = $results['results'];
        $analysis = $results['query_analysis'];
        
        // بناء سياق بيانات الأدوية للرد
        $medicationsContext = '';
        
        if (!empty($medications)) {
            $medicationsContext .= "بيانات الأدوية ذات الصلة:\n";
            
            foreach ($medications as $index => $med) {
                if ($index >= 5) break; // الاكتفاء بأهم 5 نتائج لتجنب تجاوز حجم السياق
                
                $medicationsContext .= "- دواء {$med['trade_name']}:\n";
                $medicationsContext .= "  المادة الفعالة: {$med['scientific_name']}\n";
                $medicationsContext .= "  الشركة المنتجة: {$med['company']}\n";
                $medicationsContext .= "  السعر: {$med['current_price']} ج.م\n";
                
                // إضافة المزيد من التفاصيل المفيدة
                if (!empty($med['details']['indications'])) {
                    $indications = $med['details']['indications'];
                    if (mb_strlen($indications) > 100) {
                        $indications = mb_substr($indications, 0, 97) . '...';
                    }
                    $medicationsContext .= "  دواعي الاستعمال: {$indications}\n";
                }
                
                if (!empty($med['details']['dosage'])) {
                    $dosage = $med['details']['dosage'];
                    if (mb_strlen($dosage) > 100) {
                        $dosage = mb_substr($dosage, 0, 97) . '...';
                    }
                    $medicationsContext .= "  الجرعات: {$dosage}\n";
                }
                
                if (!empty($med['details']['side_effects'])) {
                    $sideEffects = $med['details']['side_effects'];
                    if (mb_strlen($sideEffects) > 100) {
                        $sideEffects = mb_substr($sideEffects, 0, 97) . '...';
                    }
                    $medicationsContext .= "  الآثار الجانبية: {$sideEffects}\n";
                }
                
                if (!empty($med['usage_categories'])) {
                    $categories = implode(', ', $med['usage_categories']);
                    $medicationsContext .= "  فئات الاستخدام: {$categories}\n";
                }
                
                if (!empty($med['safety_info']['pregnancy_safety']) && $med['safety_info']['pregnancy_safety'] !== 'غير معروف') {
                    $medicationsContext .= "  الاستخدام أثناء الحمل: {$med['safety_info']['pregnancy_safety']}\n";
                }
                
                $medicationsContext .= "\n";
            }
            
            $medicationsContext .= "إجمالي النتائج: {$totalResults}\n";
        } else {
            $medicationsContext .= "لم يتم العثور على أدوية تطابق معايير البحث.\n";
        }
        
        // بناء سياق المحادثة السابقة
        $historyContext = '';
        if (!empty($userHistory)) {
            $historyContext .= "سجل المحادثة السابقة:\n";
            foreach ($userHistory as $item) {
                $historyContext .= "- المستخدم: {$item['query']}\n";
                if (!empty($item['response'])) {
                    $historyContext .= "- الاستجابة: {$item['response']}\n";
                }
            }
            $historyContext .= "\n";
        }
        
        // بناء سياق تحليل الاستعلام
        $analysisContext = '';
        if (!empty($analysis)) {
            $intent = $analysis['intent'] ?? 'general_search';
            $entities = $analysis['entities'] ?? [];
            
            $analysisContext .= "تحليل الاستعلام:\n";
            $analysisContext .= "- النية: {$intent}\n";
            
            if (!empty($entities)) {
                $analysisContext .= "- الكيانات المستخرجة:\n";
                foreach ($entities as $entity) {
                    $analysisContext .= "  * {$entity['type']}: {$entity['value']}\n";
                }
            }
            
            if (!empty($analysis['suggestions'])) {
                $analysisContext .= "- اقتراحات بديلة: " . implode(", ", $analysis['suggestions']) . "\n";
            }
            
            $analysisContext .= "\n";
        }
        
        // إضافة معلومات تحذيرية إذا كان البحث متعلقًا بحالة صحية
        $healthContext = '';
        if ($isHealthCondition) {
            $healthContext .= "ملاحظات هامة:\n";
            $healthContext .= "- هذا البحث متعلق بحالة صحية، وينبغي التأكيد على استشارة الطبيب.\n";
            $healthContext .= "- المعلومات المقدمة هي للإرشاد فقط ولا تغني عن الاستشارة الطبية المتخصصة.\n";
            
            if (!empty($results['warnings']['specific'])) {
                $healthContext .= "- تحذيرات متعلقة بالأدوية المقترحة:\n";
                foreach (array_slice($results['warnings']['specific'], 0, 3) as $warning) {
                    $healthContext .= "  * {$warning}\n";
                }
            }
            
            $healthContext .= "\n";
        }
        
        // بناء السياق الكامل
        $context = "استعلام المستخدم: \"{$query}\"\n\n";
        $context .= $analysisContext;
        $context .= $healthContext;
        $context .= $medicationsContext;
        $context .= $historyContext;
        
        return $context;
    }
    
    /**
     * توليد رد الدردشة المخصص للاستعلام - محسن لدقة المعلومات الطبية
     * 
     * @param string $context سياق الاستجابة
     * @return array الرد المولد
     */
    private function generateChatResponse($context) {
        $prompt = <<<PROMPT
أنت مساعد صيدلاني ذكي متخصص في منصة للأدوية. مهمتك هي الرد على استعلامات المستخدمين حول الأدوية والحالات الصحية بطريقة مفيدة ودقيقة وشاملة.

استنادًا إلى السياق التالي، قدم ردًا مفيدًا على استعلام المستخدم. تأكد من تضمين:
1. معلومات دقيقة عن الأدوية المناسبة للحالة أو الاستعلام
2. معلومات عن الجرعات والآثار الجانبية والتحذيرات الهامة
3. إرشادات واضحة حول كيفية الاستخدام الآمن والفعال
4. تأكيد على أهمية استشارة الطبيب أو الصيدلي قبل استخدام أي دواء
5. معلومات عن البدائل المتاحة إذا كانت مناسبة

في ردك، استخدم أسلوبًا واضحًا ومهنيًا:
- كن دقيقًا وموجزًا في تقديم المعلومات
- قدم معلومات مفصلة عن الأدوية والجرعات والآثار الجانبية
- أكد على أي تحذيرات مهمة
- شرح كيفية تناول الدواء بشكل صحيح
- نص على ضرورة استشارة الطبيب
- لا تذكر أنك تقوم بعملية بحث أو تدرس المعلومات، قدم المعلومات مباشرة

السياق:
{$context}
PROMPT;

        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt
                ],
                [
                    'role' => 'user',
                    'content' => $context
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 500
        ];

        try {
            $response = $this->callDeepSeekAPI($data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                return [
                    'text' => $response['choices'][0]['message']['content'],
                    'error' => false
                ];
            }
        } catch (Exception $e) {
            error_log("Failed to generate chat response: " . $e->getMessage());
        }
        
        // استجابة افتراضية في حالة الفشل
        return [
            'text' => 'وجدت بعض الأدوية التي قد تناسب استعلامك. يرجى مراجعة نتائج البحث أدناه والاطلاع على تفاصيل كل دواء. تذكر دائمًا استشارة الطبيب أو الصيدلي قبل استخدام أي دواء.',
            'error' => true
        ];
    }
    
    /**
     * توليد إرشادات محددة للحالة الصحية
     * 
     * @param string $condition الحالة الصحية
     * @return array إرشادات استخدام الأدوية لهذه الحالة
     */
    private function generateConditionSpecificGuidelines($condition) {
        // استعلام الذكاء الاصطناعي للحصول على إرشادات خاصة بالحالة الصحية
        $prompt = <<<PROMPT
قدم إرشادات استخدام الأدوية وتوصيات عامة لحالة: $condition
قدم النتائج بإيجاز في 3 نقاط فقط تتعلق بكيفية استخدام الأدوية بشكل صحيح لهذه الحالة المحددة.
PROMPT;

        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت مساعد طبي موثوق يقدم إرشادات دقيقة وموجزة حول استخدام الأدوية للحالات الصحية المختلفة.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 150
        ];
        
        try {
            $response = $this->callDeepSeekAPI($data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                
                // تنظيف النص وتقسيمه إلى نقاط
                $lines = preg_split('/\r\n|\r|\n/', $content);
                $guidelines = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // إزالة الترقيم في بداية السطر إن وجد
                    $line = preg_replace('/^[\d\-\.\*]+[\.\)\-]\s*/', '', $line);
                    
                    if (!empty($line)) {
                        $guidelines[] = $line;
                    }
                }
                
                return $guidelines;
            }
        } catch (Exception $e) {
            error_log("Failed to generate condition specific guidelines: " . $e->getMessage());
        }
        
        // إرشادات افتراضية في حالة الفشل
        return [
            'اتبع تعليمات الطبيب حول كيفية تناول الدواء وجرعته',
            'أكمل دورة العلاج الموصوفة بالكامل، حتى لو شعرت بتحسن قبل انتهائها',
            'راقب أي آثار جانبية غير عادية واستشر الطبيب إذا ظهرت عليك أعراض شديدة'
        ];
    }
    
    /**
     * الحصول على معلومات حول الحالة الصحية
     * 
     * @param string $healthCondition الحالة الصحية
     * @return array معلومات عن الحالة الصحية
     */
    private function getHealthConditionInfo($healthCondition) {
        $prompt = <<<PROMPT
قدم معلومات موجزة وموثوقة عن الحالة الصحية التالية: $healthCondition

المطلوب تقديم المعلومات التالية بتنسيق JSON:
1. وصف موجز للحالة
2. الأعراض الرئيسية
3. أسباب شائعة
4. علاجات عامة
5. متى يجب طلب الرعاية الطبية العاجلة

قدم النتائج بهذا التنسيق:
{
    "description": "وصف موجز للحالة",
    "symptoms": ["عرض 1", "عرض 2", "عرض 3"],
    "causes": ["سبب 1", "سبب 2", "سبب 3"],
    "treatments": ["علاج 1", "علاج 2", "علاج 3"],
    "seek_medical_help": "متى يجب طلب المساعدة الطبية"
}
PROMPT;

        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت خبير طبي متخصص في تقديم معلومات صحية دقيقة وموثوقة. تستخدم مصادر علمية موثوقة وتقدم المعلومات بأسلوب واضح وموجز.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.2,
            'response_format' => [
                'type' => 'json_object'
            ]
        ];
        
        try {
            $response = $this->callDeepSeekAPI($data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                $result = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $result;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to get health condition info: " . $e->getMessage());
        }
        
        // معلومات افتراضية في حالة الفشل
        return [
            'description' => "معلومات عن " . $healthCondition,
            'symptoms' => ["يرجى استشارة الطبيب للتعرف على الأعراض"],
            'causes' => ["متعددة ومتنوعة، يمكن للطبيب تحديدها"],
            'treatments' => ["يعتمد العلاج على تشخيص الحالة من قبل الطبيب المختص"],
            'seek_medical_help' => "يجب طلب المساعدة الطبية عند ظهور أعراض شديدة أو مستمرة"
        ];
    }
    
    
    
    
    /**
 * نسخة محسنة من البحث التقليدي مع بعض التحسينات الذكية
 */
private function enhanceTraditionalSearch($query, $filters = [], $page = 1, $limit = 12) {
    // نفذ البحث التقليدي أولاً
    $results = $this->fallbackSearch($query, $filters, $page, $limit);
    
    // أضف تحليل بسيط للاستعلام
    $results['query_analysis'] = $this->simpleQueryAnalysis($query);
    
    // أضف بعض الاقتراحات البسيطة
    $results['search_suggestions'] = $this->generateSimpleSuggestions($query);
    
    return $results;
}


/**
 * إنشاء اقتراحات بسيطة للبحث بدون API
 */
private function generateSimpleSuggestions($query) {
    // استعلام بسيط لاقتراح أدوية مشابهة
    $suggestions = [];
    
    if (strlen($query) >= 2) {
        $sql = "
            SELECT DISTINCT trade_name 
            FROM medications 
            WHERE trade_name LIKE ? 
            LIMIT 3
        ";
        
        $results = $this->db->fetchAll($sql, ["%" . substr($query, 0, 2) . "%"]);
        
        foreach ($results as $result) {
            if (strtolower($result['trade_name']) !== strtolower($query)) {
                $suggestions[] = $result['trade_name'];
            }
        }
    }
    
    return $suggestions;
}

    
    /**
     * تعزيز نتائج البحث باقتراحات ومعلومات إضافية
     * 
     * @param array $results نتائج البحث الأولية
     * @param array $analysis تحليل الاستعلام
     * @param string $query استعلام البحث الأصلي
     * @return array نتائج البحث المعززة
     */
    private function enhanceResults($results, $analysis, $query) {
        // إضافة تحليل الاستعلام للنتائج
        $results['query_analysis'] = $analysis;
        
        // إضافة اقتراحات بديلة للبحث
        if (!empty($analysis['suggestions'])) {
            $results['search_suggestions'] = $analysis['suggestions'];
        }
        
        // إضافة تحذيرات وتنبيهات عامة
        $results['warnings'] = [
            'general' => [
                'يجب استشارة الطبيب قبل تناول أي دواء.',
                'المعلومات المقدمة هي للإرشاد فقط ولا تغني عن الاستشارة الطبية المتخصصة.'
            ],
            'specific' => []
        ];
        
        // إضافة تحذيرات خاصة بالأدوية المعروضة
        if (!empty($results['results'])) {
            $pregnancyWarnings = [];
            $sideEffectsWarnings = [];
            $interactionWarnings = [];
            
            foreach ($results['results'] as $med) {
                // تحذيرات الحمل
                if (isset($med['safety_info']['pregnancy_safety']) && 
                    $med['safety_info']['pregnancy_safety'] !== 'غير معروف' &&
                    $med['safety_info']['pregnancy_safety'] !== 'لا يؤثر عادة على الحمل') {
                    $pregnancyWarnings[] = "دواء {$med['trade_name']}: {$med['safety_info']['pregnancy_safety']}";
                }
                
                // تحذيرات الآثار الجانبية الشديدة
                if (!empty($med['side_effect_severity']['severe'])) {
                    $sideEffectsWarnings[] = "دواء {$med['trade_name']} قد يسبب آثارًا جانبية شديدة في بعض الحالات";
                }
                
                // تحذيرات التفاعلات
                if (isset($med['safety_info']['alcohol_interaction']) && 
                    $med['safety_info']['alcohol_interaction'] !== 'غير معروف') {
                    $interactionWarnings[] = "دواء {$med['trade_name']}: {$med['safety_info']['alcohol_interaction']}";
                }
            }
            
            $results['warnings']['specific'] = array_merge(
                $pregnancyWarnings, 
                $sideEffectsWarnings, 
                $interactionWarnings
            );
            
            // تحديد عدد التحذيرات المحددة التي سيتم عرضها
            $results['warnings']['specific'] = array_slice($results['warnings']['specific'], 0, 5);
        }
        
        return $results;
    }
    
    /**
     * البحث التقليدي كخطة بديلة - يستخدم في حالة فشل البحث المعزز بالذكاء الاصطناعي
     * 
     * @param string $query استعلام البحث
     * @param array $filters فلاتر البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @return array نتائج البحث
     */
    private function fallbackSearch($query, $filters = [], $page = 1, $limit = 12) {
        // استخدام محرك البحث التقليدي إذا كان متاحًا
        if ($this->searchEngine) {
            return $this->searchEngine->search($query, $filters, $page, $limit);
        }
        
        // تنفيذ بحث بسيط في حالة عدم توفر محرك البحث التقليدي
        $searchParams = [
            'whereClauses' => ["(trade_name LIKE ? OR scientific_name LIKE ? OR description LIKE ?)"],
            'params' => ["%" . $query . "%", "%" . $query . "%", "%" . $query . "%"],
            'orderBy' => 'visit_count DESC'
        ];
        
        // إضافة الفلاتر
        $this->mergeUserFilters($searchParams, $filters);
        
        // تنفيذ البحث
        $results = $this->executeSearch($searchParams, $page, $limit);
        
        return $results;
    }

    /**
     * تحليل استعلام البحث باستخدام DeepSeek API - تحسين بإضافة تحليل للحالات الصحية
     * 
     * @param string $query استعلام البحث
     * @return array نتيجة التحليل
     */
  private function analyzeQuery($query) {
    // استخدام التخزين المؤقت للاستعلامات المتكررة
    static $cache = [];
    
    // التحقق من وجود الاستعلام في الذاكرة المؤقتة
    $cacheKey = md5($query);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    // لاستخدام تحليل أبسط وأسرع للاستعلامات القصيرة جدًا
    if (strlen($query) <= 3 || str_word_count($query) <= 1) {
        $simpleAnalysis = $this->simpleQueryAnalysis($query);
        $cache[$cacheKey] = $simpleAnalysis;
        return $simpleAnalysis;
    }
    
    // بناء prompt لتحليل الاستعلام
    $prompt = $this->buildAnalysisPrompt($query);
    
    // تقليل مقدار العمل على الـ API عن طريق تقليل سياق المطالبة
    $data = [
        'model' => 'deepseek-chat',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'أنت مساعد متخصص في تحليل استعلامات البحث عن الأدوية.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.1,
        'max_tokens' => 250, // تقليل حجم الاستجابة للإسراع
        'response_format' => [
            'type' => 'json_object'
        ]
    ];

    // الاتصال بـ API للتحليل
    $response = $this->callDeepSeekAPI($data);
    
    // التعامل مع الخطأ والرجوع إلى التحليل البسيط
    if (isset($response['status']) && $response['status'] === 'error') {
        $simpleAnalysis = $this->simpleQueryAnalysis($query);
        $cache[$cacheKey] = $simpleAnalysis;
        return $simpleAnalysis;
    }
    
    // استخراج تحليل الاستعلام من الاستجابة
    if (isset($response['choices'][0]['message']['content'])) {
        $content = $response['choices'][0]['message']['content'];
        $analysisResult = json_decode($content, true);
        
        // التحقق من صحة البيانات
        if (json_last_error() === JSON_ERROR_NONE && isset($analysisResult['keywords'])) {
            // حفظ في الذاكرة المؤقتة للاستخدام المستقبلي
            $cache[$cacheKey] = $analysisResult;
            return $analysisResult;
        }
    }
    
    // إذا فشل التحليل المتقدم، استخدم التحليل البسيط
    $simpleAnalysis = $this->simpleQueryAnalysis($query);
    $cache[$cacheKey] = $simpleAnalysis;
    return $simpleAnalysis;
}



/**
 * تحليل بسيط وسريع للاستعلامات البسيطة
 * 
 * @param string $query استعلام البحث
 * @return array نتيجة التحليل البسيط
 */
private function simpleQueryAnalysis($query) {
    // إرجاع هيكل تحليل بسيط وسريع
    return [
        'original_query' => $query,
        'keywords' => [$query],
        'entities' => [
            ['type' => 'drug_name', 'value' => $query, 'confidence' => 0.5]
        ],
        'intent' => 'general_search',
        'suggestions' => [],
        'health_condition' => [
            'is_health_search' => false,
            'condition_name' => '',
            'symptoms' => []
        ]
    ];
}



    /**
     * بناء الـ Prompt المحسن لتحليل الاستعلام - بإضافة تحليل للحالات الصحية
     * 
     * @param string $query استعلام البحث
     * @return string الـ prompt المستخدم للتحليل
     */
    private function buildAnalysisPrompt($query) {
        return <<<PROMPT
حلل استعلام البحث التالي عن الأدوية أو الحالات الصحية وقدم الرد بتنسيق JSON فقط:

استعلام البحث: "{$query}"

المطلوب:
1. استخراج الكلمات المفتاحية الرئيسية من الاستعلام.
2. تحديد الكيانات المهمة (مثل: اسم الدواء، المادة الفعالة، الشركة المصنعة، التصنيف، الأعراض، الحالة الصحية).
3. تحديد نية المستخدم (بحث عام، بحث عن دواء محدد، بحث عن بديل، بحث حسب الأعراض، بحث عن علاج لحالة صحية).
4. اقتراح مصطلحات بديلة أو معدلة قد تساعد في البحث.
5. تحديد أي معايير تصفية محتملة من الاستعلام.
6. تحديد ما إذا كان المستخدم يبحث عن علاج لحالة صحية محددة.

أعد النتائج بهذا التنسيق:
{
    "original_query": "الاستعلام الأصلي",
    "keywords": ["كلمة1", "كلمة2", ...],
    "entities": [
        {"type": "drug_name", "value": "اسم الدواء", "confidence": 0.9},
        {"type": "active_ingredient", "value": "المادة الفعالة", "confidence": 0.8},
        {"type": "health_condition", "value": "الحالة الصحية", "confidence": 0.9},
        {"type": "symptom", "value": "العرض المرضي", "confidence": 0.8},
        ...
    ],
    "intent": "نوع البحث (general_search, specific_drug, alternative, symptom_search, health_condition_treatment)",
    "filters": {
        "price_min": null,
        "price_max": null,
        "company": null,
        "category": null
    },
    "health_condition": {
        "is_health_search": true/false,
        "condition_name": "اسم الحالة الصحية",
        "symptoms": ["عرض1", "عرض2", ...]
    },
    "suggestions": ["اقتراح1", "اقتراح2", ...]
}

عد فقط بتنسيق JSON، بدون أي تعليقات أو نص إضافي.
PROMPT;
    }

    /**
     * تحديد ما إذا كان المستخدم يبحث عن علاج لحالة صحية
     * 
     * @param array $queryAnalysis نتيجة تحليل الاستعلام
     * @return bool ما إذا كان البحث عن علاج لحالة صحية
     */
    private function isHealthConditionSearch($queryAnalysis) {
        // التحقق مما إذا كان التحليل يتضمن معلومات عن الحالة الصحية
        if (isset($queryAnalysis['health_condition']['is_health_search'])) {
            return $queryAnalysis['health_condition']['is_health_search'];
        }
        
        // التحقق من نية البحث
        if (isset($queryAnalysis['intent']) && $queryAnalysis['intent'] === 'health_condition_treatment') {
            return true;
        }
        
        // البحث عن كيانات الحالة الصحية أو الأعراض
        if (isset($queryAnalysis['entities']) && is_array($queryAnalysis['entities'])) {
            foreach ($queryAnalysis['entities'] as $entity) {
                if (in_array($entity['type'], ['health_condition', 'symptom']) && $entity['confidence'] > 0.6) {
                    return true;
                }
            }
        }
        
    // البحث عن كلمات مفتاحية تدل على البحث عن علاج
        $healthSearchKeywords = ['علاج', 'دواء لـ', 'أعاني من', 'مريض بـ', 'مصاب بـ', 'تعالج'];
        foreach ($healthSearchKeywords as $keyword) {
            if (strpos($queryAnalysis['original_query'], $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * استخراج معلومات الحالة الصحية من تحليل الاستعلام
     * 
     * @param array $queryAnalysis نتيجة تحليل الاستعلام
     * @return string اسم الحالة الصحية
     */
    private function extractHealthCondition($queryAnalysis) {
        // التحقق مما إذا كان الاستعلام يتضمن معلومات عن الحالة الصحية
        if (isset($queryAnalysis['health_condition']['condition_name'])) {
            return $queryAnalysis['health_condition']['condition_name'];
        }
        
        // البحث عن كيانات الحالة الصحية
        if (isset($queryAnalysis['entities']) && is_array($queryAnalysis['entities'])) {
            foreach ($queryAnalysis['entities'] as $entity) {
                if ($entity['type'] === 'health_condition' && $entity['confidence'] > 0.6) {
                    return $entity['value'];
                }
            }
            
            // إذا لم يتم العثور على حالة صحية محددة، نبحث عن الأعراض
            foreach ($queryAnalysis['entities'] as $entity) {
                if ($entity['type'] === 'symptom' && $entity['confidence'] > 0.6) {
                    return $entity['value'];
                }
            }
        }
        
        // إذا لم يتم العثور على حالة صحية، نستخدم الاستعلام الأصلي
        return $queryAnalysis['original_query'];
    }

    /**
     * بناء معلمات البحث للحالة الصحية
     * 
     * @param string $healthCondition الحالة الصحية المستهدفة
     * @param array $filters الفلاتر المقدمة من المستخدم
     * @return array معلمات البحث
     */
    private function buildHealthConditionSearchParams($healthCondition, $filters) {
        $searchParams = [
            'whereClauses' => [],
            'params' => [],
            'orderBy' => 'visit_count DESC, id DESC',
        ];
        
        // البحث في وصف الدواء ودواعي الاستعمال عن الحالة الصحية
        $searchParams['whereClauses'][] = "(description LIKE ? OR indications LIKE ? OR dosage LIKE ?)";
        $searchParams['params'][] = "%" . $healthCondition . "%";
        $searchParams['params'][] = "%" . $healthCondition . "%";
        $searchParams['params'][] = "%" . $healthCondition . "%";
        
        // دمج الفلاتر المقدمة من المستخدم
        $this->mergeUserFilters($searchParams, $filters);
        
        return $searchParams;
    }
    
    /**
     * بناء معلمات البحث استنادًا إلى تحليل الاستعلام
     * 
     * @param array $analysis نتيجة تحليل الاستعلام
     * @param array $filters الفلاتر المقدمة من المستخدم
     * @return array معلمات البحث
     */
    private function buildSearchParams($analysis, $filters) {
        $searchParams = [
            'whereClauses' => [],
            'params' => [],
            'orderBy' => 'id DESC',
        ];

        // دمج الفلاتر المقدمة من المستخدم
        $this->mergeUserFilters($searchParams, $filters);

        // دمج الفلاتر المستخرجة من الذكاء الاصطناعي
        $this->mergeAIFilters($searchParams, $analysis);

        // بناء استعلام البحث الرئيسي من الكلمات المفتاحية المستخرجة
        $this->buildKeywordConditions($searchParams, $analysis);
        
        // تعيين ترتيب النتائج
        $this->setResultsOrder($searchParams, $analysis, $filters);
        
        return $searchParams;
    }

    /**
     * دمج فلاتر المستخدم مع معلمات البحث
     * 
     * @param array &$searchParams معلمات البحث (مرجع)
     * @param array $filters فلاتر المستخدم
     */
    private function mergeUserFilters(&$searchParams, $filters) {
        if (!empty($filters['category'])) {
            $searchParams['whereClauses'][] = "category LIKE ?";
            $searchParams['params'][] = "%" . $filters['category'] . "%";
        }
        
        if (!empty($filters['company'])) {
            $searchParams['whereClauses'][] = "company = ?";
            $searchParams['params'][] = $filters['company'];
        }
        
        if (!empty($filters['scientific_name'])) {
            $searchParams['whereClauses'][] = "scientific_name LIKE ?";
            $searchParams['params'][] = "%" . $filters['scientific_name'] . "%";
        }
        
        if (isset($filters['price_min']) && is_numeric($filters['price_min'])) {
            $searchParams['whereClauses'][] = "current_price >= ?";
            $searchParams['params'][] = (float)$filters['price_min'];
        }
        
        if (isset($filters['price_max']) && is_numeric($filters['price_max'])) {
            $searchParams['whereClauses'][] = "current_price <= ?";
            $searchParams['params'][] = (float)$filters['price_max'];
        }
    }

    /**
     * دمج فلاتر الذكاء الاصطناعي مع معلمات البحث
     * 
     * @param array &$searchParams معلمات البحث (مرجع)
     * @param array $analysis تحليل الاستعلام
     */
    private function mergeAIFilters(&$searchParams, $analysis) {
        if (isset($analysis['filters']) && is_array($analysis['filters'])) {
            foreach ($analysis['filters'] as $key => $value) {
                if (!empty($value)) {
                    switch ($key) {
                        case 'price_min':
                            $searchParams['whereClauses'][] = "current_price >= ?";
                            $searchParams['params'][] = (float)$value;
                            break;
                        case 'price_max':
                            $searchParams['whereClauses'][] = "current_price <= ?";
                            $searchParams['params'][] = (float)$value;
                            break;
                        case 'company':
                            $searchParams['whereClauses'][] = "company LIKE ?";
                            $searchParams['params'][] = "%" . $value . "%";
                            break;
                        case 'category':
                            $searchParams['whereClauses'][] = "category LIKE ?";
                            $searchParams['params'][] = "%" . $value . "%";
                            break;
                    }
                }
            }
        }
    }
    
    
    /**
     * بناء شروط البحث بالكلمات المفتاحية
     * 
     * @param array &$searchParams معلمات البحث (مرجع)
     * @param array $analysis تحليل الاستعلام
     */
    private function buildKeywordConditions(&$searchParams, $analysis) {
        $keywordsConditions = [];
        
        // البحث حسب الكيانات المستخرجة (اسم الدواء، المادة الفعالة، إلخ)
        if (!empty($analysis['entities'])) {
            foreach ($analysis['entities'] as $entity) {
                $value = $entity['value'];
                $confidence = $entity['confidence'] ?? 0.5;
                
                if ($confidence < 0.3) continue; // تجاهل الكيانات منخفضة الثقة
                
                switch ($entity['type']) {
                    case 'drug_name':
                        $keywordsConditions[] = "(trade_name LIKE ? OR arabic_name LIKE ?)";
                        $searchParams['params'][] = "%" . $value . "%";
                        $searchParams['params'][] = "%" . $value . "%";
                        break;
                    case 'active_ingredient':
                        $keywordsConditions[] = "scientific_name LIKE ?";
                        $searchParams['params'][] = "%" . $value . "%";
                        break;
                    case 'company':
                        $keywordsConditions[] = "company LIKE ?";
                        $searchParams['params'][] = "%" . $value . "%";
                        break;
                    case 'category':
                        $keywordsConditions[] = "category LIKE ?";
                        $searchParams['params'][] = "%" . $value . "%";
                        break;
                    case 'barcode':
                        $keywordsConditions[] = "barcode = ?";
                        $searchParams['params'][] = $value;
                        break;
                }
            }
        }
        
        // استخدام الكلمات المفتاحية المستخرجة للبحث العام
        if (!empty($analysis['keywords'])) {
            $keywordGroup = [];
            foreach ($analysis['keywords'] as $keyword) {
                if (strlen($keyword) >= 2) {
                    $keywordGroup[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR description LIKE ?)";
                    for ($i = 0; $i < 4; $i++) {
                        $searchParams['params'][] = "%" . $keyword . "%";
                    }
                }
            }
            
            if (!empty($keywordGroup)) {
                $keywordsConditions[] = "(" . implode(" OR ", $keywordGroup) . ")";
            }
        }
        
        // دمج شروط الكلمات المفتاحية في شروط البحث الرئيسية
        if (!empty($keywordsConditions)) {
            $searchParams['whereClauses'][] = "(" . implode(" OR ", $keywordsConditions) . ")";
        }
    }
    
    /**
     * تعيين ترتيب نتائج البحث
     * 
     * @param array &$searchParams معلمات البحث (مرجع)
     * @param array $analysis تحليل الاستعلام
     * @param array $filters فلاتر المستخدم
     */
    private function setResultsOrder(&$searchParams, $analysis, $filters) {
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'price_asc':
                    $searchParams['orderBy'] = "current_price ASC";
                    break;
                case 'price_desc':
                    $searchParams['orderBy'] = "current_price DESC";
                    break;
                case 'name_asc':
                    $searchParams['orderBy'] = "trade_name ASC";
                    break;
                case 'name_desc':
                    $searchParams['orderBy'] = "trade_name DESC";
                    break;
                case 'visits_desc':
                    $searchParams['orderBy'] = "visit_count DESC";
                    break;
                case 'date_desc':
                    $searchParams['orderBy'] = "price_updated_date DESC";
                    break;
            }
        } else {
            // ترتيب افتراضي حسب نية البحث
            if (!empty($analysis['intent'])) {
                switch ($analysis['intent']) {
                    case 'specific_drug':
                        // ترتيب حسب التطابق الأفضل لاسم الدواء
                        $searchParams['orderBy'] = "CASE 
                            WHEN trade_name = ? THEN 1 
                            WHEN trade_name LIKE ? THEN 2 
                            ELSE 3 
                        END, visit_count DESC";
                        // إضافة المعاملات للترتيب (نفس اسم الدواء المستهدف)
                        if (!empty($analysis['entities'])) {
                            foreach ($analysis['entities'] as $entity) {
                                if ($entity['type'] === 'drug_name') {
                                    $searchParams['params'][] = $entity['value'];
                                    $searchParams['params'][] = $entity['value'] . "%";
                                    break;
                                }
                            }
                        }
                        break;
                    case 'alternative':
                        // ترتيب حسب السعر للبدائل
                        $searchParams['orderBy'] = "current_price ASC";
                        break;
                    case 'symptom_search':
                    case 'health_condition_treatment':
                        // ترتيب حسب الشعبية والتقييمات للأعراض والحالات الصحية
                        $searchParams['orderBy'] = "visit_count DESC";
                        break;
                    default:
                        // الترتيب الافتراضي حسب الصلة
                        $searchParams['orderBy'] = "visit_count DESC, id DESC";
                }
            }
        }
    }
    
    /**
     * تنفيذ البحث في قاعدة البيانات باستخدام معلمات البحث
     * 
     * @param array $searchParams معلمات البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @return array نتائج البحث
     */
    private function executeSearch($searchParams, $page, $limit) {
        $offset = ($page - 1) * $limit;
        $whereClauses = $searchParams['whereClauses'];
        $params = $searchParams['params'];
        $orderBy = $searchParams['orderBy'];
        
        // إنشاء استعلام البحث
        $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
        
        // عدد النتائج الكلي
        $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
        $totalResultsData = $this->db->fetchOne($countSql, $params);
        $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
        
        // جلب نتائج البحث
        $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
        $searchResults = $this->db->fetchAll($searchSql, $params);
        
        // حساب عدد الصفحات
        $totalPages = ceil($totalResults / $limit);
        
        return [
            'results' => $searchResults,
            'total' => $totalResults,
            'page' => $page,
            'limit' => $limit,
            'pages' => $totalPages,
            'search_params' => $searchParams
        ];
    }

    /**
     * إثراء نتائج البحث بتفاصيل الأدوية من الجداول ذات الصلة
     * 
     * @param array $results نتائج البحث
     * @return array نتائج البحث المحسنة
     */
    private function enrichMedicationsWithDetails($results) {
        if (empty($results['results'])) {
            return $results;
        }
        
        $medicationIds = array_column($results['results'], 'id');
        $idsPlaceholders = implode(',', array_fill(0, count($medicationIds), '?'));
        
        // جلب التفاصيل الإضافية للأدوية
        $detailsSql = "SELECT * FROM medication_details WHERE medication_id IN ($idsPlaceholders)";
        $details = $this->db->fetchAll($detailsSql, $medicationIds);
        
        // تنظيم التفاصيل حسب معرف الدواء
        $detailsByMedId = [];
        foreach ($details as $detail) {
            $detailsByMedId[$detail['medication_id']] = $detail;
        }
        
        // دمج التفاصيل مع نتائج البحث
        foreach ($results['results'] as &$medication) {
            if (isset($detailsByMedId[$medication['id']])) {
                $medication['details'] = $detailsByMedId[$medication['id']];
            } else {
                // جلب تفاصيل الدواء من الذكاء الاصطناعي إذا لم تكن متوفرة
                $medication['details'] = $this->generateMedicationDetails($medication);
            }
            
            // إضافة روابط إلى البدائل المشابهة والشركات المصنعة، إلخ.
            $medication['links'] = $this->generateMedicationLinks($medication);
            
            // إضافة تصنيفات إضافية للاستخدامات والآثار الجانبية
            $medication['usage_categories'] = $this->classifyUsagePurposes($medication);
            $medication['side_effect_severity'] = $this->classifySideEffects($medication);
            
            // إضافة معلومات الاستخدام الآمن والفعال
            $medication['safety_info'] = $this->generateSafetyInfo($medication);
        }
        
        return $results;
    }
}
?>