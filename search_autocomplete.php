<?php
// search_autocomplete.php - معالجة طلبات البحث التلقائي المحسن
require_once 'config/database.php';
require_once 'search_engine.php'; // تأكد من تضمين محرك البحث المحسن

// تضمين معالجة الأخطاء
try {
    // تأكد من استلام معيار البحث
    if (!isset($_GET['q']) || empty($_GET['q'])) {
        // إرجاع مصفوفة فارغة إذا لم يتم توفير معيار البحث
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $db = Database::getInstance();
    $searchEngine = new SearchEngine($db);
    $query = trim($_GET['q']);
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $searchMethod = isset($_GET['method']) ? $_GET['method'] : 'trade_name';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

    $results = [];

    // تنفيذ البحث حسب الطريقة المحددة
    switch ($searchMethod) {
        case 'trade_anypos':
            $results = performAnyPosTradeNameSearch($db, $query, $filter, $limit);
            break;
            
        case 'google_style':
            $results = performGoogleStyleSearch($db, $query, $filter, $limit);
            break;
            
        case 'combined':
            $results = performCombinedSearch($db, $query, $filter, $limit);
            break;
            
        case 'trade_name':
        default:
            $results = performTradeNameSearch($db, $query, $filter, $limit);
            break;
    }

    // إضافة معلومات عن طريقة البحث المستخدمة
    foreach ($results as &$item) {
        $item['search_method'] = $searchMethod;
    }

    // إرجاع النتائج بتنسيق JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($results);
    exit;
} catch (Exception $e) {
    // إرجاع رسالة خطأ بتنسيق JSON
    header('Content-Type: application/json');
    http_response_code(500); // هذا يعني "Internal Server Error"
    echo json_encode(['error' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()]);
    exit;
}

/**
 * البحث باسم الدواء التجاري (من البداية)
 */
function performTradeNameSearch($db, $query, $filter, $limit) {
    // تحليل الاستعلام للتعامل مع المسافات بشكل خاص
    $searchTerms = explode(' ', trim($query));
    $hasLeadingSpace = substr($query, 0, 1) === ' ';
    
    $whereClauses = [];
    $params = [];
    
    if ($hasLeadingSpace) {
        // إذا بدأ الاستعلام بمسافة، ابحث في أي موضع
        $conditions = [];
        foreach ($searchTerms as $term) {
            if (!empty($term)) {
                switch ($filter) {
                    case 'trade_name':
                        $conditions[] = "trade_name LIKE ?";
                        $params[] = "%" . $term . "%";
                        break;
                    case 'scientific_name':
                        $conditions[] = "scientific_name LIKE ?";
                        $params[] = "%" . $term . "%";
                        break;
                    case 'company':
                        $conditions[] = "company LIKE ?";
                        $params[] = "%" . $term . "%";
                        break;
                    default: // all
                        $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR company LIKE ?)";
                        $params[] = "%" . $term . "%";
                        $params[] = "%" . $term . "%";
                        $params[] = "%" . $term . "%";
                        break;
                }
            }
        }
        
        if (!empty($conditions) && $filter !== 'all') {
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
            switch ($filter) {
                case 'trade_name':
                    $whereClauses[] = "trade_name LIKE ?";
                    $params[] = $likePattern . "%";
                    break;
                case 'scientific_name':
                    $whereClauses[] = "scientific_name LIKE ?";
                    $params[] = $likePattern . "%";
                    break;
                case 'company':
                    $whereClauses[] = "company LIKE ?";
                    $params[] = $likePattern . "%";
                    break;
                default: // all
                    $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR company LIKE ?)";
                    $params[] = $likePattern . "%";
                    $params[] = $likePattern . "%";
                    $params[] = $likePattern . "%";
                    break;
            }
        }
    }
    
    // إضافة بحث بالباركود إذا كان الاستعلام رقميًا
    if (is_numeric($query)) {
        $index = count($whereClauses) - 1;
        if ($index >= 0) {
            $whereClauses[$index] = "(" . $whereClauses[$index] . " OR barcode = ?)";
            $params[] = $query;
        } else {
            $whereClauses[] = "barcode = ?";
            $params[] = $query;
        }
    }
    
    // إنشاء استعلام البحث
    $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    $baseFields = "id, trade_name, scientific_name, company, current_price, old_price, units_per_package, image_url";
    
    $sql = "SELECT $baseFields FROM medications $whereClause ORDER BY trade_name ASC LIMIT $limit";
    
    return $db->fetchAll($sql, $params);
}

/**
 * البحث باسم الدواء التجاري (في أي موضع)
 */
function performAnyPosTradeNameSearch($db, $query, $filter, $limit) {
    // تجزئة الاستعلام إلى كلمات
    $searchTerms = explode(' ', trim($query));
    $whereClauses = [];
    $params = [];
    
    $conditions = [];
    foreach ($searchTerms as $term) {
        if (!empty($term)) {
            switch ($filter) {
                case 'trade_name':
                    $conditions[] = "trade_name LIKE ?";
                    $params[] = "%" . $term . "%";
                    break;
                case 'scientific_name':
                    $conditions[] = "scientific_name LIKE ?";
                    $params[] = "%" . $term . "%";
                    break;
                case 'company':
                    $conditions[] = "company LIKE ?";
                    $params[] = "%" . $term . "%";
                    break;
                default: // all
                    $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR company LIKE ?)";
                    $params[] = "%" . $term . "%";
                    $params[] = "%" . $term . "%";
                    $params[] = "%" . $term . "%";
                    break;
            }
        }
    }
    
    if (!empty($conditions) && $filter !== 'all') {
        $whereClauses[] = "(" . implode(" AND ", $conditions) . ")";
    }
    
    // إضافة بحث بالباركود إذا كان الاستعلام رقميًا
    if (is_numeric($query)) {
        $whereClauses[] = "barcode = ?";
        $params[] = $query;
    }
    
    // إنشاء استعلام البحث
    $whereClause = !empty($whereClauses) ? "WHERE " . implode(" OR ", $whereClauses) : "";
    $baseFields = "id, trade_name, scientific_name, company, current_price, old_price, units_per_package, image_url";
    
    $sql = "SELECT $baseFields FROM medications $whereClause ORDER BY trade_name ASC LIMIT $limit";
    
    return $db->fetchAll($sql, $params);
}

/**
 * البحث بطريقة جوجل (الأحرف المتشابهة/البحث الفونيتيكي)
 */
function performGoogleStyleSearch($db, $query, $filter, $limit) {
    // التحقق إذا كان الاستعلام بالعربية
    $isArabic = preg_match('/[\x{0600}-\x{06FF}]/u', $query);
    
    $whereClauses = [];
    $params = [];
    
    if ($isArabic) {
        // تحويل الأحرف العربية إلى ما يقابلها بالإنجليزية (فونيتيكي)
        $englishQuery = arabicToEnglishPhonetic($query);
        
        // البحث في الحقول المختلفة
        $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ?)";
        $params[] = "%" . $query . "%";
        $params[] = "%" . $englishQuery . "%";
        $params[] = "%" . $query . "%";
    } else {
        // إنشاء قائمة بالاختلافات المحتملة للأحرف المتشابهة
        $variations = generatePhoneticVariations($query);
        
        // بناء شروط البحث للاختلافات
        $variationConditions = [];
        foreach ($variations as $variation) {
            switch ($filter) {
                case 'trade_name':
                    $variationConditions[] = "trade_name LIKE ?";
                    $params[] = "%" . $variation . "%";
                    break;
                case 'scientific_name':
                    $variationConditions[] = "scientific_name LIKE ?";
                    $params[] = "%" . $variation . "%";
                    break;
                case 'company':
                    $variationConditions[] = "company LIKE ?";
                    $params[] = "%" . $variation . "%";
                    break;
                default: // all
                    $variationConditions[] = "(trade_name LIKE ? OR scientific_name LIKE ?)";
                    $params[] = "%" . $variation . "%";
                    $params[] = "%" . $variation . "%";
                    break;
            }
        }
        
        if (!empty($variationConditions)) {
            $whereClauses[] = "(" . implode(" OR ", $variationConditions) . ")";
        }
    }
    
    // إنشاء استعلام البحث
    $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    $baseFields = "id, trade_name, scientific_name, company, current_price, old_price, units_per_package, image_url";
    
    $sql = "SELECT $baseFields FROM medications $whereClause ORDER BY trade_name ASC LIMIT $limit";
    
    $results = $db->fetchAll($sql, $params);
    
    // إضافة معلومات عن طريقة المطابقة
    foreach ($results as &$result) {
        $result['match_type'] = 'phonetic';
    }
    
    return $results;
}

/**
 * البحث المجمع (اسم + سعر + معايير أخرى)
 */
function performCombinedSearch($db, $query, $filter, $limit) {
    $whereClauses = [];
    $params = [];
    
    // البحث في حقول متعددة
    $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR company LIKE ? OR category LIKE ?)";
    $params[] = "%" . $query . "%";
    $params[] = "%" . $query . "%";
    $params[] = "%" . $query . "%";
    $params[] = "%" . $query . "%";
    $params[] = "%" . $query . "%";
    
    // إذا كان الاستعلام عبارة عن رقم، افترض أنه قد يكون سعرًا أو باركود
    if (is_numeric($query)) {
        $whereClauses[] = "(barcode = ? OR (current_price >= ? AND current_price <= ?))";
        $params[] = $query; // الباركود
        $params[] = (float)$query - 5; // نطاق سعري أقل
        $params[] = (float)$query + 5; // نطاق سعري أعلى
    }
    
    // إنشاء استعلام البحث
    $whereClause = !empty($whereClauses) ? "WHERE " . implode(" OR ", $whereClauses) : "";
    $baseFields = "id, trade_name, scientific_name, company, current_price, old_price, units_per_package, image_url";
    
    $sql = "SELECT $baseFields FROM medications $whereClause ORDER BY 
        CASE WHEN trade_name LIKE ? THEN 1
             WHEN scientific_name LIKE ? THEN 2
             WHEN barcode = ? THEN 3
             ELSE 4
        END
        LIMIT $limit";
    
    $params[] = $query . "%"; // للترتيب
    $params[] = $query . "%"; // للترتيب
    $params[] = $query;      // للترتيب
    
    return $db->fetchAll($sql, $params);
}

/**
 * إنشاء اختلافات فونيتيكية للاستعلام
 */
function generatePhoneticVariations($query) {
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
 */
function arabicToEnglishPhonetic($arabicWord) {
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