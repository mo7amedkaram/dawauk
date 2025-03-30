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

        try {
            // تحليل الاستعلام باستخدام DeepSeek API
            $queryAnalysis = $this->analyzeQuery($query);
            
            // بناء استعلام قاعدة البيانات استنادًا إلى تحليل الذكاء الاصطناعي
            $searchParams = $this->buildSearchParams($queryAnalysis, $filters);
            
            // تنفيذ البحث في قاعدة البيانات
            $results = $this->executeSearch($searchParams, $page, $limit);
            
            // إضافة اقتراحات ومعلومات مفيدة من الذكاء الاصطناعي
            $enhancedResults = $this->enhanceResults($results, $queryAnalysis, $query);
            
            return $enhancedResults;
        } catch (Exception $e) {
            // تسجيل الخطأ واستخدام البحث التقليدي كخطة بديلة
            error_log("AISearchEngine error: " . $e->getMessage());
            return $this->fallbackSearch($query, $filters, $page, $limit);
        }
    }

    /**
     * تحليل استعلام البحث باستخدام DeepSeek API
     * 
     * @param string $query استعلام البحث
     * @return array نتيجة التحليل
     */
    private function analyzeQuery($query) {
        $prompt = $this->buildAnalysisPrompt($query);
        
        $data = [
            'model' => 'deepseek-chat', // يمكن تغييره حسب النموذج المتاح لديك
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت مساعد مختص في فهم استعلامات البحث عن الأدوية. مهمتك تحليل استعلامات المستخدمين وتحويلها إلى معايير بحث دقيقة.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'response_format' => [
                'type' => 'json_object'
            ]
        ];

        $response = $this->callDeepSeekAPI($data);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            $analysisResult = json_decode($content, true);
            
            // التحقق من صحة التنسيق والبيانات المطلوبة
            if (json_last_error() === JSON_ERROR_NONE && isset($analysisResult['keywords'])) {
                return $analysisResult;
            }
        }
        
        // إذا فشل التحليل، أعد ببنية بسيطة
        return [
            'original_query' => $query,
            'keywords' => [$query],
            'entities' => [],
            'intent' => 'general_search',
            'suggestions' => []
        ];
    }

    /**
     * بناء الـ Prompt لتحليل الاستعلام
     * 
     * @param string $query استعلام البحث
     * @return string الـ prompt المستخدم للتحليل
     */
    private function buildAnalysisPrompt($query) {
        return <<<PROMPT
حلل استعلام البحث التالي عن الأدوية وقدم الرد بتنسيق JSON فقط:

استعلام البحث: "{$query}"

المطلوب:
1. استخراج الكلمات المفتاحية الرئيسية من الاستعلام.
2. تحديد الكيانات المهمة (مثل: اسم الدواء، المادة الفعالة، الشركة المصنعة، التصنيف، الأعراض).
3. تحديد نية المستخدم (بحث عام، بحث عن دواء محدد، بحث عن بديل، بحث حسب الأعراض).
4. اقتراح مصطلحات بديلة أو معدلة قد تساعد في البحث.
5. تحديد أي معايير تصفية محتملة من الاستعلام (مثل نطاق السعر، الشركة المصنعة، وما إلى ذلك).

أعد النتائج بهذا التنسيق:
{
    "original_query": "الاستعلام الأصلي",
    "keywords": ["كلمة1", "كلمة2", ...],
    "entities": [
        {"type": "drug_name", "value": "اسم الدواء", "confidence": 0.9},
        {"type": "active_ingredient", "value": "المادة الفعالة", "confidence": 0.8},
        ...
    ],
    "intent": "نوع البحث (general_search, specific_drug, alternative, symptom_search)",
    "filters": {
        "price_min": null,
        "price_max": null,
        "company": null,
        "category": null
    },
    "suggestions": ["اقتراح1", "اقتراح2", ...]
}

عد فقط بتنسيق JSON، بدون أي تعليقات أو نص إضافي.
PROMPT;
    }

    /**
     * استدعاء DeepSeek API
     * 
     * @param array $data البيانات المراد إرسالها
     * @return array الاستجابة من API
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 ثوانٍ للمهلة الزمنية
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception("DeepSeek API request failed: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("DeepSeek API returned error code: " . $httpCode);
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response from DeepSeek API");
        }
        
        return $decoded;
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

        // دمج الفلاتر المحددة من المستخدم
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

        // دمج الفلاتر المستخرجة من الذكاء الاصطناعي
        if (isset($analysis['filters']) && is_array($analysis['filters'])) {
            foreach ($analysis['filters'] as $key => $value) {
                if (!empty($value) && !isset($filters[$key])) {
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

        // بناء استعلام البحث الرئيسي من الكلمات المفتاحية المستخرجة
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
        
        // تعيين ترتيب النتائج
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
                        // ترتيب حسب الشعبية والتقييمات للأعراض
                        $searchParams['orderBy'] = "visit_count DESC";
                        break;
                    default:
                        // الترتيب الافتراضي حسب الصلة
                        $searchParams['orderBy'] = "visit_count DESC, id DESC";
                }
            }
        }
        
        return $searchParams;
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
     * تحسين نتائج البحث بإضافة معلومات مفيدة من الذكاء الاصطناعي
     * 
     * @param array $results نتائج البحث
     * @param array $analysis تحليل الاستعلام
     * @param string $originalQuery الاستعلام الأصلي
     * @return array نتائج البحث المحسنة
     */
    private function enhanceResults($results, $analysis, $originalQuery) {
        // إضافة تحليل الاستعلام للنتائج
        $results['query_analysis'] = [
            'original' => $originalQuery,
            'analyzed' => $analysis,
            'enhanced' => true
        ];
        
        // إضافة اقتراحات البحث من الذكاء الاصطناعي
        if (!empty($analysis['suggestions'])) {
            $results['suggestions'] = $analysis['suggestions'];
        }
        
        // في حالة عدم وجود نتائج، حاول الحصول على توصيات بديلة
        if (empty($results['results']) && !empty($analysis['entities'])) {
            $alternativeSuggestions = $this->getAlternativeSuggestions($analysis);
            if (!empty($alternativeSuggestions)) {
                $results['alternative_suggestions'] = $alternativeSuggestions;
            }
        }
        
        // إضافة تعليق مفيد من الذكاء الاصطناعي للمساعدة في فهم النتائج
        $results['ai_comment'] = $this->generateSearchComment($results, $analysis, $originalQuery);
        
        return $results;
    }

    /**
     * الحصول على اقتراحات بديلة للبحث عندما لا توجد نتائج
     * 
     * @param array $analysis تحليل الاستعلام
     * @return array الاقتراحات البديلة
     */
    private function getAlternativeSuggestions($analysis) {
        $suggestions = [];
        
        // البحث عن بدائل للأدوية المحددة
        foreach ($analysis['entities'] as $entity) {
            if ($entity['type'] === 'drug_name' && !empty($entity['value'])) {
                // البحث عن أدوية مشابهة في الاسم
                $similarByName = $this->db->fetchAll(
                    "SELECT id, trade_name FROM medications 
                     WHERE trade_name LIKE ? 
                     ORDER BY CASE 
                         WHEN trade_name = ? THEN 1 
                         WHEN trade_name LIKE ? THEN 2 
                         ELSE 3 
                     END LIMIT 5",
                    ["%" . $entity['value'] . "%", $entity['value'], $entity['value'] . "%"]
                );
                
                if (!empty($similarByName)) {
                    $suggestions['similar_by_name'] = $similarByName;
                }
                
                // البحث عن أدوية تحتوي على نفس المادة الفعالة
                $drugsWithSameIngredient = $this->db->fetchAll(
                    "SELECT DISTINCT scientific_name FROM medications 
                     WHERE trade_name LIKE ? LIMIT 1",
                    ["%" . $entity['value'] . "%"]
                );
                
                if (!empty($drugsWithSameIngredient[0]['scientific_name'])) {
                    $scientificName = $drugsWithSameIngredient[0]['scientific_name'];
                    
                    $alternatives = $this->db->fetchAll(
                        "SELECT id, trade_name, company, current_price 
                         FROM medications 
                         WHERE scientific_name = ? 
                         ORDER BY current_price ASC LIMIT 5",
                        [$scientificName]
                    );
                    
                    if (!empty($alternatives)) {
                        $suggestions['alternatives'] = $alternatives;
                        $suggestions['active_ingredient'] = $scientificName;
                    }
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * إنشاء تعليق مفيد على نتائج البحث
     * 
     * @param array $results نتائج البحث
     * @param array $analysis تحليل الاستعلام
     * @param string $originalQuery الاستعلام الأصلي
     * @return string التعليق المفيد
     */
    private function generateSearchComment($results, $analysis, $originalQuery) {
        // تجاهل التعليق إذا لم تكن هناك رؤى مفيدة للتعليق عليها
        if (empty($analysis) || (!empty($results['results']) && count($results['results']) > 10)) {
            return null;
        }
        
        // استخدام الذكاء الاصطناعي لتوليد تعليق مفيد
        $prompt = $this->buildCommentPrompt($results, $analysis, $originalQuery);
        
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت مساعد مختص في تحليل نتائج البحث عن الأدوية وتقديم تعليقات مفيدة للمستخدمين. تعليقاتك قصيرة وموجزة ومفيدة.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.5,
            'max_tokens' => 150
        ];

        try {
            $response = $this->callDeepSeekAPI($data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                return $response['choices'][0]['message']['content'];
            }
        } catch (Exception $e) {
            error_log("Failed to generate search comment: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * بناء الـ Prompt لتعليق على نتائج البحث
     * 
     * @param array $results نتائج البحث
     * @param array $analysis تحليل الاستعلام
     * @param string $originalQuery الاستعلام الأصلي
     * @return string الـ prompt لتعليق على النتائج
     */
    private function buildCommentPrompt($results, $analysis, $originalQuery) {
        $totalResults = $results['total'];
        $intent = $analysis['intent'] ?? 'general_search';
        $entities = json_encode($analysis['entities'] ?? [], JSON_UNESCAPED_UNICODE);
        
        $prompt = <<<PROMPT
قم بتقديم تعليق مفيد وقصير جداً (1-2 جمل) على نتائج البحث التالية:

استعلام المستخدم: "{$originalQuery}"
نوع البحث: {$intent}
عدد النتائج: {$totalResults}
الكيانات المستخرجة: {$entities}

ملاحظات:
- إذا كان عدد النتائج 0، اقترح توسيع معايير البحث أو تقديم كلمات بديلة.
- إذا كان البحث عن دواء محدد، قدم نصيحة حول البدائل أو المعلومات المهمة.
- إذا كان البحث عامًا، اذكر كيف يمكن للمستخدم تحسين البحث.
- احرص على أن يكون التعليق موجزاً جداً - جملة أو جملتين كحد أقصى.
- لا تضف مقدمات مثل "بناءً على بحثك" أو "فيما يتعلق باستعلامك".
PROMPT;

        return $prompt;
    }

    /**
     * استخدام البحث التقليدي كنسخة احتياطية عند فشل البحث المعزز بالذكاء الاصطناعي
     * 
     * @param string $query استعلام البحث
     * @param array $filters فلاتر البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @return array نتائج البحث
     */
    private function fallbackSearch($query, $filters, $page, $limit) {
        // استخدام محرك البحث التقليدي إذا كان متاحًا
        if ($this->searchEngine) {
            return $this->searchEngine->search($query, $filters, $page, $limit);
        }
        
        // تنفيذ بحث بسيط إذا لم يكن محرك البحث التقليدي متاحًا
        $whereClauses = [];
        $params = [];
        $orderBy = "id DESC";
        $offset = ($page - 1) * $limit;
        
        if (!empty($query)) {
            $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR barcode = ?)";
            $searchParam = "%$query%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $query;
        }
        
        // إضافة فلاتر البحث
        if (!empty($filters['category'])) {
            $whereClauses[] = "category LIKE ?";
            $params[] = "%" . $filters['category'] . "%";
        }
        
        if (!empty($filters['company'])) {
            $whereClauses[] = "company = ?";
            $params[] = $filters['company'];
        }
        
        if (!empty($filters['scientific_name'])) {
            $whereClauses[] = "scientific_name LIKE ?";
            $params[] = "%" . $filters['scientific_name'] . "%";
        }
        
        if (isset($filters['price_min']) && is_numeric($filters['price_min'])) {
            $whereClauses[] = "current_price >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (isset($filters['price_max']) && is_numeric($filters['price_max'])) {
            $whereClauses[] = "current_price <= ?";
            $params[] = $filters['price_max'];
        }
        
        // ترتيب النتائج
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'price_asc':
                    $orderBy = "current_price ASC";
                    break;
                case 'price_desc':
                    $orderBy = "current_price DESC";
                    break;
                case 'name_asc':
                    $orderBy = "trade_name ASC";
                    break;
                case 'name_desc':
                    $orderBy = "trade_name DESC";
                    break;
                case 'visits_desc':
                    $orderBy = "visit_count DESC";
                    break;
                case 'date_desc':
                    $orderBy = "price_updated_date DESC";
                    break;
            }
        }
        
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
            'query_analysis' => [
                'original' => $query,
                'keywords' => $query,
                'special_tokens' => [],
                'suggestions' => []
            ]
        ];
    }
    
    /**
     * البحث باستخدام اللغة الطبيعية (بحث شبيه بالدردشة)
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
            
            // البحث في قاعدة البيانات بناءً على تحليل الاستعلام
            $searchParams = $this->buildSearchParams($queryAnalysis, $filters);
            $results = $this->executeSearch($searchParams, $page, $limit);
            
            // إضافة تحليل الاستعلام للنتائج
            $results['query_analysis'] = $queryAnalysis;
            
            // إعداد معلومات السياق للرد على الاستعلام بنمط الدردشة
            $context = $this->prepareResponseContext($results, $query, $userHistory);
            
            // توليد الرد المخصص بأسلوب الدردشة
            $chatResponse = $this->generateChatResponse($context);
            
            // إضافة الرد المخصص إلى النتائج
            $results['chat_response'] = $chatResponse;
            
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
     * إعداد سياق الاستجابة للرد على الاستعلام بنمط الدردشة
     * 
     * @param array $results نتائج البحث
     * @param string $query استعلام البحث
     * @param array $userHistory سجل المحادثة السابقة
     * @return string سياق الاستجابة
     */
    private function prepareResponseContext($results, $query, $userHistory) {
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
                
                if (!empty($med['description'])) {
                    $description = substr($med['description'], 0, 100);
                    if (strlen($med['description']) > 100) $description .= '...';
                    $medicationsContext .= "  الوصف: {$description}\n";
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
        
        // بناء السياق الكامل
        $context = "استعلام المستخدم: \"{$query}\"\n\n";
        $context .= $analysisContext;
        $context .= $medicationsContext;
        $context .= $historyContext;
        
        return $context;
    }
    
    /**
     * توليد رد الدردشة المخصص للاستعلام
     * 
     * @param string $context سياق الاستجابة
     * @return array الرد المولد
     */
    private function generateChatResponse($context) {
        $prompt = <<<PROMPT
أنت مساعد مختص بالأدوية في منصة بحث عن الأدوية. مهمتك هي الرد على استعلامات المستخدمين حول الأدوية بطريقة مفيدة وموجزة.

استنادًا إلى السياق التالي، قدم ردًا موجزًا ومفيدًا على استعلام المستخدم. الرد يجب أن يكون قصيرًا (3-5 جمل كحد أقصى) وملخصًا للمعلومات الأكثر أهمية. تجنب تكرار صيغ البحث أو ذكر أنك تقوم بالبحث، وركز على المعلومات المفيدة فقط.

السياق:
{$context}

التنسيق المطلوب للرد:
- قدم ملخصًا مباشرًا للنتائج
- ركز على الإجابة عن سؤال المستخدم
- اذكر أهم المعلومات ذات الصلة بالأدوية المذكورة
- اقترح إجراءات محتملة للمستخدم إذا كان مناسبًا
- كن موجزًا ومفيدًا
- لا تذكر أنك تقوم بعملية بحث أو تدرس المعلومات
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
            'max_tokens' => 200
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
            'text' => 'إليك نتائج البحث عن الأدوية التي طلبتها. يمكنك تصفية النتائج باستخدام خيارات التصفية المتاحة أو تعديل استعلام البحث للحصول على نتائج أكثر دقة.',
            'error' => true
        ];
    }
    
    /**
     * استخراج المادة الفعالة (الاسم العلمي) من اسم دواء
     * 
     * @param string $drugName اسم الدواء
     * @return string|null المادة الفعالة أو null إذا لم يتم العثور عليها
     */
    public function extractActiveIngredient($drugName) {
        // محاولة العثور على الدواء في قاعدة البيانات
        $medication = $this->db->fetchOne(
            "SELECT scientific_name FROM medications WHERE trade_name LIKE ? LIMIT 1",
            ["%" . $drugName . "%"]
        );
        
        if ($medication && !empty($medication['scientific_name'])) {
            return $medication['scientific_name'];
        }
        
        // استخدام الذكاء الاصطناعي لاستخراج المادة الفعالة
        $prompt = "استخرج المادة الفعالة (الاسم العلمي) من اسم الدواء التالي: \"{$drugName}\". أعد المادة الفعالة فقط بدون أي تعليقات أو نص إضافي.";
        
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'أنت مساعد مختص بالأدوية. مهمتك استخراج المادة الفعالة من اسم الدواء. استخدم معرفتك بالصيدلة والكيمياء الدوائية.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 50
        ];

        try {
            $response = $this->callDeepSeekAPI($data);
            
            if (isset($response['choices'][0]['message']['content'])) {
                return trim($response['choices'][0]['message']['content']);
            }
        } catch (Exception $e) {
            error_log("Failed to extract active ingredient: " . $e->getMessage());
        }
        
        return null;
    }
}