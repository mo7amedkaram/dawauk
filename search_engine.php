<?php
// search_engine.php - محرك البحث المتقدم المعزز

class SearchEngine {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * البحث المتقدم بدعم طرق بحث متعددة
     * 
     * @param string $query كلمة البحث
     * @param array $filters فلاتر البحث
     * @param int $page رقم الصفحة
     * @param int $limit عدد النتائج لكل صفحة
     * @param string $searchMethod طريقة البحث (trade_name, trade_anypos, google_style)
     * @return array نتائج البحث
     */
    public function search($query, $filters = [], $page = 1, $limit = 12, $searchMethod = 'trade_name') {
        $whereClauses = [];
        $params = [];
        $orderBy = "id DESC";
        $offset = ($page - 1) * $limit;
        
        // معالجة طرق البحث المختلفة
        switch ($searchMethod) {
            case 'trade_anypos':
                $this->buildAnyPosTradeNameSearch($query, $whereClauses, $params);
                break;
                
            case 'google_style':
                $this->buildGoogleStyleSearch($query, $whereClauses, $params);
                break;
                
            case 'combined':
                $this->buildCombinedSearch($query, $whereClauses, $params);
                break;
                
            case 'trade_name':
            default:
                $this->buildTradeNameSearch($query, $whereClauses, $params);
                break;
        }
        
        // إضافة فلاتر البحث
        $this->applyFilters($filters, $whereClauses, $params);
        
        // تعيين ترتيب النتائج
        $this->setResultsOrder($filters, $orderBy);
        
        // إنشاء استعلام البحث
        $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
        
        // عدد النتائج الكلي
        $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
        $totalResultsData = $this->db->fetchOne($countSql, $params);
        $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
        
        // استعلام البحث
        $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
        $searchResults = $this->db->fetchAll($searchSql, $params);
        
        // إعداد اقتراحات البحث
        $suggestions = $this->generateSuggestions($query);
        
        // إعداد النتائج
        return [
            'results' => $searchResults,
            'total' => $totalResults,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalResults / $limit),
            'query_analysis' => [
                'original' => $query,
                'method' => $searchMethod,
                'suggestions' => $suggestions
            ]
        ];
    }
    
    /**
     * بناء استعلام البحث باسم الدواء التجاري (من البداية)
     * 
     * @param string $query استعلام البحث
     * @param array &$whereClauses شروط البحث (مرجع)
     * @param array &$params معلمات البحث (مرجع)
     */
    private function buildTradeNameSearch($query, &$whereClauses, &$params) {
        // تحليل الاستعلام للتعامل مع المسافات بشكل خاص
        $searchTerms = explode(' ', trim($query));
        $hasLeadingSpace = substr($query, 0, 1) === ' ';
        
        if ($hasLeadingSpace) {
            // إذا بدأ الاستعلام بمسافة، ابحث في أي موضع
            $conditions = [];
            foreach ($searchTerms as $term) {
                if (!empty($term)) {
                    $conditions[] = "trade_name LIKE ?";
                    $params[] = "%" . $term . "%";
                }
            }
            
            if (!empty($conditions)) {
                $whereClauses[] = "(" . implode(" AND ", $conditions) . ")";
            }
        } else {
            // البحث من بداية الاسم
            $likePattern = "";
            foreach ($searchTerms as $index => $term) {
                if (!empty($term)) {
                    if ($index === 0) {
                        $likePattern .= $term;
                    } else {
                        $likePattern .= "%" . $term;
                    }
                } else {
                    $likePattern .= "%";
                }
            }
            
            if (!empty($likePattern)) {
                $whereClauses[] = "trade_name LIKE ?";
                $params[] = $likePattern . "%";
            }
        }
        
        // إضافة بحث بالباركود إذا كان الاستعلام رقميًا
        if (is_numeric($query)) {
            $whereClauses[count($whereClauses) - 1] = "(" . $whereClauses[count($whereClauses) - 1] . " OR barcode = ?)";
            $params[] = $query;
        }
    }
    
    /**
     * بناء استعلام البحث باسم الدواء التجاري (في أي موضع)
     * 
     * @param string $query استعلام البحث
     * @param array &$whereClauses شروط البحث (مرجع)
     * @param array &$params معلمات البحث (مرجع)
     */
    private function buildAnyPosTradeNameSearch($query, &$whereClauses, &$params) {
        // تجزئة الاستعلام إلى كلمات
        $searchTerms = explode(' ', trim($query));
        $conditions = [];
        
        foreach ($searchTerms as $term) {
            if (!empty($term)) {
                $conditions[] = "trade_name LIKE ?";
                $params[] = "%" . $term . "%";
            }
        }
        
        if (!empty($conditions)) {
            $whereClauses[] = "(" . implode(" AND ", $conditions) . ")";
        }
        
        // إضافة بحث بالباركود إذا كان الاستعلام رقميًا
        if (is_numeric($query)) {
            $whereClauses[count($whereClauses) - 1] = "(" . $whereClauses[count($whereClauses) - 1] . " OR barcode = ?)";
            $params[] = $query;
        }
    }
    
    /**
     * بناء استعلام البحث بطريقة جوجل (الأحرف المتشابهة/البحث الفونيتيكي)
     * 
     * @param string $query استعلام البحث
     * @param array &$whereClauses شروط البحث (مرجع)
     * @param array &$params معلمات البحث (مرجع)
     */
    private function buildGoogleStyleSearch($query, &$whereClauses, &$params) {
        // التحقق إذا كان الاستعلام بالعربية
        $isArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $query);
        
        if ($isArabic) {
            // تحويل الأحرف العربية إلى ما يقابلها بالإنجليزية (فونيتيكي)
            $englishQuery = $this->arabicToEnglishPhonetic($query);
            
            // البحث في الحقول المختلفة
            $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ?)";
            $params[] = "%" . $query . "%";
            $params[] = "%" . $englishQuery . "%";
            $params[] = "%" . $query . "%";
        } else {
            // إنشاء قائمة بالاختلافات المحتملة للأحرف المتشابهة
            $variations = $this->generatePhoneticVariations($query);
            
            // بناء شروط البحث للاختلافات
            $variationConditions = [];
            foreach ($variations as $variation) {
                $variationConditions[] = "trade_name LIKE ?";
                $params[] = "%" . $variation . "%";
                
                $variationConditions[] = "scientific_name LIKE ?";
                $params[] = "%" . $variation . "%";
            }
            
            if (!empty($variationConditions)) {
                $whereClauses[] = "(" . implode(" OR ", $variationConditions) . ")";
            }
        }
    }
    
    /**
     * بناء استعلام البحث المجمع (اسم + سعر + معايير أخرى)
     * 
     * @param string $query استعلام البحث
     * @param array &$whereClauses شروط البحث (مرجع)
     * @param array &$params معلمات البحث (مرجع)
     */
    private function buildCombinedSearch($query, &$whereClauses, &$params) {
        // البحث في حقول متعددة
        $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR company LIKE ? OR category LIKE ?)";
        $params[] = "%" . $query . "%";
        $params[] = "%" . $query . "%";
        $params[] = "%" . $query . "%";
        $params[] = "%" . $query . "%";
        $params[] = "%" . $query . "%";
        
        // إذا كان الاستعلام عبارة عن رقم، افترض أنه قد يكون سعرًا أو باركود
        if (is_numeric($query)) {
            $whereClauses[count($whereClauses) - 1] = "(" . $whereClauses[count($whereClauses) - 1] . " OR barcode = ? OR (current_price >= ? AND current_price <= ?))";
            $params[] = $query; // الباركود
            $params[] = (float)$query - 5; // نطاق سعري أقل
            $params[] = (float)$query + 5; // نطاق سعري أعلى
        }
    }
    
    /**
     * تطبيق فلاتر البحث
     * 
     * @param array $filters فلاتر البحث
     * @param array &$whereClauses شروط البحث (مرجع)
     * @param array &$params معلمات البحث (مرجع)
     */
    private function applyFilters($filters, &$whereClauses, &$params) {
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
        
        // يمكن إضافة فلاتر إضافية هنا مثل التركيز أو الشكل الصيدلاني
    }
    
    /**
     * تعيين ترتيب نتائج البحث
     * 
     * @param array $filters فلاتر البحث
     * @param string &$orderBy ترتيب النتائج (مرجع)
     */
    private function setResultsOrder($filters, &$orderBy) {
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
    }
    
    /**
     * إنشاء اقتراحات بديلة للبحث
     * 
     * @param string $query استعلام البحث
     * @return array اقتراحات البحث
     */
    private function generateSuggestions($query) {
        $suggestions = [];
        
        // استعلام بسيط لاقتراح أدوية مشابهة
        if (strlen($query) >= 2) {
            // اقتراحات مشابهة من بداية الاسم
            $sql = "
                SELECT DISTINCT trade_name 
                FROM medications 
                WHERE trade_name LIKE ? 
                LIMIT 5
            ";
            
            $results = $this->db->fetchAll($sql, [$query . "%"]);
            
            if (!empty($results)) {
                foreach ($results as $result) {
                    if (strtolower($result['trade_name']) !== strtolower($query)) {
                        $suggestions[] = $result['trade_name'];
                    }
                }
            }
            
            // اقتراحات مشابهة من أي موضع
            if (count($suggestions) < 5) {
                $sql = "
                    SELECT DISTINCT trade_name 
                    FROM medications 
                    WHERE trade_name LIKE ? AND trade_name NOT LIKE ?
                    LIMIT " . (5 - count($suggestions));
                
                $results = $this->db->fetchAll($sql, ["%" . $query . "%", $query . "%"]);
                
                if (!empty($results)) {
                    foreach ($results as $result) {
                        if (strtolower($result['trade_name']) !== strtolower($query)) {
                            $suggestions[] = $result['trade_name'];
                        }
                    }
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * إنشاء اختلافات فونيتيكية للاستعلام
     * 
     * @param string $query استعلام البحث
     * @return array الاختلافات الفونيتيكية
     */
    private function generatePhoneticVariations($query) {
        $variations = [$query]; // إضافة الاستعلام الأصلي
        
        // قواعد الاستبدال للأحرف المتشابهة
        $replacementRules = [
            // الحروف المتشابهة في النطق
            'f' => ['ph'],
            'ph' => ['f'],
            'c' => ['k', 's'],
            'k' => ['c', 'q'],
            'q' => ['k'],
            's' => ['c', 'z'],
            'z' => ['s'],
            'x' => ['ks', 'cs', 'cks'],
            'i' => ['y', 'ee'],
            'y' => ['i', 'ee'],
            'j' => ['g'],
            'g' => ['j'],
            'v' => ['f'],
            'o' => ['u'],
            'u' => ['o'],
            'ai' => ['ay', 'ei', 'ey'],
            'ay' => ['ai', 'ei', 'ey'],
            'ei' => ['ai', 'ay', 'ey'],
            'ey' => ['ai', 'ay', 'ei'],
            'ck' => ['k', 'c'],
            'sc' => ['s'],
            'ch' => ['tsh', 'sh', 'tch'],
            'sh' => ['ch'],
            'th' => ['t'],
            'a' => ['e'],
            'e' => ['a'],
        ];
        
        // استبدال الأحرف المتشابهة
        foreach ($replacementRules as $original => $replacements) {
            foreach ($variations as $variation) {
                if (stripos($variation, $original) !== false) {
                    foreach ($replacements as $replacement) {
                        $newVariation = str_ireplace($original, $replacement, $variation);
                        if (!in_array($newVariation, $variations)) {
                            $variations[] = $newVariation;
                        }
                    }
                }
            }
        }
        
        // التعامل مع المسافات
        $termsWithoutSpaces = str_replace(' ', '', $query);
        if (!in_array($termsWithoutSpaces, $variations) && $termsWithoutSpaces !== $query) {
            $variations[] = $termsWithoutSpaces;
        }
        
        return $variations;
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