<?php
// search_engine.php - محرك البحث المتقدم

class SearchEngine {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * البحث المتقدم بطريقة مشابهة لجوجل
     * 
     * @param string $query كلمة البحث
     * @param array $filters فلاتر البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @return array نتائج البحث
     */
    public function search($query, $filters = [], $page = 1, $limit = 12) {
        $whereClauses = [];
        $params = [];
        $orderBy = "id DESC";
        $offset = ($page - 1) * $limit;
        
        // معالجة استعلام البحث وتحسينه
        $processedQuery = $this->processQuery($query);
        
        // بناء استعلام للبحث بالكلمات المفتاحية
        if (!empty($processedQuery['keywords'])) {
            $keywords = $processedQuery['keywords'];
            
            // إنشاء مجموعة من الشروط للبحث في مختلف الحقول
            $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR barcode = ?)";
            
            // إضافة معامل البحث المناسب لكل حقل
            $params[] = "%" . $query . "%";  // البحث التقليدي
            $params[] = "%" . $query . "%";
            $params[] = "%" . $query . "%";
            $params[] = $query;      // البحث الدقيق للباركود
        } else {
            // البحث التقليدي إذا لم يكن هناك كلمات مفتاحية
            $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR barcode = ?)";
            $params[] = "%" . $query . "%";
            $params[] = "%" . $query . "%";
            $params[] = "%" . $query . "%";
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
            $params[] = (float)$filters['price_min'];
        }
        
        if (isset($filters['price_max']) && is_numeric($filters['price_max'])) {
            $whereClauses[] = "current_price <= ?";
            $params[] = (float)$filters['price_max'];
        }
        
        // ترتيب حسب الخيار المحدد
        if (isset($filters['sort']) && !empty($filters['sort'])) {
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
                default:
                    // الافتراضي: الترتيب حسب الأحدث
                    $orderBy = "id DESC";
                    break;
            }
        }
        
        // إنشاء استعلام البحث
        $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
        
        // عدد النتائج الكلي
        $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
        $totalResultsData = $this->db->fetchOne($countSql, $params);
        $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
        
        // استعلام البحث
        $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
        $searchResults = $this->db->fetchAll($searchSql, $params);
        
        // إعداد النتائج
        return [
            'results' => $searchResults,
            'total' => $totalResults,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalResults / $limit),
            'query_analysis' => $processedQuery
        ];
    }
    
    /**
     * معالجة استعلام البحث وتحسينه
     * 
     * @param string $query استعلام البحث
     * @return array معلومات معالجة الاستعلام
     */
    private function processQuery($query) {
        $result = [
            'original' => $query,
            'keywords' => $query,
            'special_tokens' => [],
            'suggestions' => []
        ];
        
        // حذف الحروف الخاصة
        $cleanQuery = preg_replace('/[^\p{L}\p{N}\s*"\'+-]/u', ' ', $query);
        
        // البحث عن العلامات الخاصة مثل "+" و "-" و "*"
        preg_match_all('/([+-]?\w+|\*\w+|\w+\*|\".+?\")/u', $cleanQuery, $matches);
        
        // تحليل الكلمات المفتاحية والرموز الخاصة
        if (!empty($matches[0])) {
            $keywords = [];
            
            foreach ($matches[0] as $term) {
                // علامة + تعني وجوب وجود الكلمة
                if (strpos($term, '+') === 0) {
                    $result['special_tokens'][] = [
                        'type' => 'required',
                        'term' => substr($term, 1)
                    ];
                    $keywords[] = $term;
                }
                // علامة - تعني استبعاد الكلمة
                else if (strpos($term, '-') === 0) {
                    $result['special_tokens'][] = [
                        'type' => 'excluded',
                        'term' => substr($term, 1)
                    ];
                    $keywords[] = $term;
                }
                // بحث بالعبارة الدقيقة داخل علامات الاقتباس
                else if (strpos($term, '"') === 0 && strrpos($term, '"') === strlen($term) - 1) {
                    $result['special_tokens'][] = [
                        'type' => 'exact_phrase',
                        'term' => substr($term, 1, -1)
                    ];
                    $keywords[] = $term;
                }
                // بحث باستخدام العلامة النجمية * للبحث الجزئي
                else if (strpos($term, '*') !== false) {
                    $result['special_tokens'][] = [
                        'type' => 'wildcard',
                        'term' => $term
                    ];
                    $keywords[] = $term;
                }
                // كلمة بحث عادية
                else {
                    $keywords[] = $term;
                }
            }
            
            $result['keywords'] = implode(' ', $keywords);
        }
        
        // إنشاء اقتراحات بديلة للبحث
        $result['suggestions'] = $this->generateSuggestions($query);
        
        return $result;
    }
    
    /**
     * إنشاء اقتراحات بديلة للبحث
     * 
     * @param string $query استعلام البحث
     * @return array اقتراحات البحث
     */
    private function generateSuggestions($query) {
        // يمكن تنفيذ خوارزمية أكثر تعقيدًا لاقتراح تصحيحات الإملاء والبدائل
        $suggestions = [];
        
        // استعلام بسيط لاقتراح أدوية مشابهة
        if (strlen($query) >= 3) {
            $sql = "
                SELECT DISTINCT trade_name 
                FROM medications 
                WHERE trade_name LIKE ? 
                LIMIT 5
            ";
            
            $results = $this->db->fetchAll($sql, ["%" . substr($query, 0, 3) . "%"]);
            
            if (!empty($results)) {
                foreach ($results as $result) {
                    // تأكد من أن الاقتراح ليس هو نفس استعلام البحث
                    if (strtolower($result['trade_name']) !== strtolower($query)) {
                        $suggestions[] = $result['trade_name'];
                    }
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * البحث عن أدوية متشابهة بناءً على الاسم التجاري
     * 
     * @param string $tradeName اسم الدواء التجاري
     * @param int $limit عدد النتائج
     * @return array الأدوية المتشابهة
     */
    public function findSimilarMedicationsByName($tradeName, $limit = 5) {
        if (empty($tradeName)) return [];
        
        // استخدام مطابقة جزئية لإيجاد أدوية متشابهة
        $sql = "
            SELECT id, trade_name, scientific_name, company, current_price, image_url
            FROM medications 
            WHERE trade_name LIKE ? 
            ORDER BY CASE
                WHEN trade_name = ? THEN 1
                WHEN trade_name LIKE ? THEN 2
                WHEN trade_name LIKE ? THEN 3
                ELSE 4
            END, trade_name ASC
            LIMIT ?
        ";
        
        $params = [
            "%" . $tradeName . "%",
            $tradeName,
            $tradeName . "%",
            "%" . $tradeName,
            $limit
        ];
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * البحث عن أدوية باستخدام باركود
     * 
     * @param string $barcode الباركود
     * @return array|null الدواء المطابق أو null إذا لم يتم العثور عليه
     */
    public function findMedicationByBarcode($barcode) {
        if (empty($barcode)) return null;
        
        $sql = "SELECT * FROM medications WHERE barcode = ? LIMIT 1";
        return $this->db->fetchOne($sql, [$barcode]);
    }
    
    /**
     * تحويل كلمة البحث العربية إلى حروف إنجليزية مناسبة (بحث فونيتيكي)
     * 
     * @param string $arabicWord الكلمة العربية
     * @return string الكلمة بالحروف الإنجليزية المشابهة صوتياً
     */
    private function arabicToEnglishPhonetic($arabicWord) {
        $arabicToEnglish = [
            'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'آ' => 'a',
            'ب' => 'b', 'ت' => 't', 'ث' => 'th',
            'ج' => 'j', 'ح' => 'h', 'خ' => 'kh',
            'د' => 'd', 'ذ' => 'th', 'ر' => 'r',
            'ز' => 'z', 'س' => 's', 'ش' => 'sh',
            'ص' => 's', 'ض' => 'd', 'ط' => 't',
            'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh',
            'ف' => 'f', 'ق' => 'q', 'ك' => 'k',
            'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'ه' => 'h', 'و' => 'w', 'ي' => 'y',
            'ى' => 'a', 'ئ' => 'e', 'ء' => '\'',
            'ؤ' => 'o', 'ة' => 'a'
        ];
        
        $englishWord = '';
        $arabicChars = mb_str_split($arabicWord, 1, 'UTF-8');
        
        foreach ($arabicChars as $char) {
            if (isset($arabicToEnglish[$char])) {
                $englishWord .= $arabicToEnglish[$char];
            } else {
                $englishWord .= $char;
            }
        }
        
        return $englishWord;
    }
    
    /**
     * الحصول على الاقتراحات الساخنة (الأكثر بحثاً)
     * 
     * @param int $limit عدد النتائج
     * @return array الاقتراحات الساخنة
     */
    public function getHotSuggestions($limit = 10) {
        $sql = "
            SELECT search_term, COUNT(*) as count
            FROM search_stats
            GROUP BY search_term
            ORDER BY count DESC
            LIMIT ?
        ";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
}