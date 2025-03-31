<?php
// search_function.php - دالة البحث المتطورة متعددة الأساليب

/**
 * دالة البحث المتطورة عن الأدوية
 * 
 * @param object $db كائن قاعدة البيانات
 * @param string $query نص البحث
 * @param string $method طريقة البحث (trade, trade_any, google, price)
 * @param array $filters فلاتر إضافية للبحث
 * @param int $page رقم الصفحة
 * @param int $limit عدد النتائج لكل صفحة
 * @return array نتائج البحث
 */
function advancedSearch($db, $query, $method = 'trade', $filters = [], $page = 1, $limit = 12) {
    // تنظيف استعلام البحث
    $query = trim($query);
    
    // التعامل مع البحث الفارغ
    if (empty($query) && empty($filters)) {
        return [
            'results' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'pages' => 0,
            'method' => $method,
            'success' => false,
            'message' => 'الرجاء إدخال معايير البحث'
        ];
    }
    
    // إعداد متغيرات البحث
    $whereClauses = [];
    $params = [];
    $orderBy = "id DESC";
    $offset = ($page - 1) * $limit;
    
    // تحديد طريقة البحث المناسبة
    switch ($method) {
        // 1. البحث بالاسم التجاري (من بداية الاسم)
        case 'trade':
            $processedQuery = processTradeNameQuery($query);
            $searchPattern = $processedQuery['pattern'];
            
            // إذا كان البحث يبدأ بمسافة، فإنه يعني البحث في أي موضع
            if (strpos($query, ' ') === 0) {
                $whereClauses[] = "trade_name LIKE ?";
                $params[] = "%" . substr($searchPattern, 1) . "%";
            } else {
                $whereClauses[] = "trade_name LIKE ?";
                $params[] = $searchPattern . "%";
            }
            break;
            
        // 2. البحث بالاسم التجاري (من أي موضع)
        case 'trade_any':
            $processedQuery = processTradeNameQuery($query);
            $searchPattern = $processedQuery['pattern'];
            
            $whereClauses[] = "trade_name LIKE ?";
            $params[] = "%" . $searchPattern . "%";
            break;
            
        // 3. البحث بطريقة جوجل (البحث الصوتي/المتشابهات)
        case 'google':
            $whereClauses[] = buildGoogleStyleQuery($query, $params);
            break;
            
        // 4. البحث بالسعر والاسم
        case 'price':
            // استخراج السعر من الاستعلام (على افتراض أنها في الصيغة "اسم الدواء | السعر")
            $queryParts = explode('|', $query);
            
            if (count($queryParts) > 1) {
                $nameQuery = trim($queryParts[0]);
                $priceQuery = trim($queryParts[1]);
                
                // البحث بالاسم
                if (!empty($nameQuery)) {
                    $whereClauses[] = "trade_name LIKE ?";
                    $params[] = "%" . $nameQuery . "%";
                }
                
                // البحث بالسعر (افتراض أنه رقم أو مدى)
                if (!empty($priceQuery)) {
                    // التحقق مما إذا كان نطاق سعري (على سبيل المثال: 100-200)
                    if (strpos($priceQuery, '-') !== false) {
                        $priceParts = explode('-', $priceQuery);
                        $minPrice = trim($priceParts[0]);
                        $maxPrice = trim($priceParts[1]);
                        
                        if (is_numeric($minPrice)) {
                            $whereClauses[] = "current_price >= ?";
                            $params[] = (float)$minPrice;
                        }
                        
                        if (is_numeric($maxPrice)) {
                            $whereClauses[] = "current_price <= ?";
                            $params[] = (float)$maxPrice;
                        }
                    } 
                    // أو إذا كان يحتوي على عبارة "أقل من" أو "أكثر من"
                    else if (strpos($priceQuery, 'أقل من') !== false) {
                        $priceValue = trim(str_replace('أقل من', '', $priceQuery));
                        if (is_numeric($priceValue)) {
                            $whereClauses[] = "current_price < ?";
                            $params[] = (float)$priceValue;
                        }
                    } 
                    else if (strpos($priceQuery, 'أكثر من') !== false) {
                        $priceValue = trim(str_replace('أكثر من', '', $priceQuery));
                        if (is_numeric($priceValue)) {
                            $whereClauses[] = "current_price > ?";
                            $params[] = (float)$priceValue;
                        }
                    }
                    // أو إذا كان سعر محدد
                    else if (is_numeric($priceQuery)) {
                        $whereClauses[] = "current_price = ?";
                        $params[] = (float)$priceQuery;
                    }
                }
            } 
            // لو لم يتم إدخال تنسيق صحيح، نفترض أنه بحث عادي فقط
            else {
                $whereClauses[] = "trade_name LIKE ?";
                $params[] = "%" . $query . "%";
            }
            break;
            
        // بحث افتراضي في حال لم يتم تحديد طريقة البحث
        default:
            $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR barcode = ?)";
            $params[] = "%" . $query . "%";
            $params[] = "%" . $query . "%";
            $params[] = "%" . $query . "%";
            $params[] = $query; // البحث بالباركود يكون مطابقاً تماماً
            break;
    }
    
    // إضافة الفلاتر الإضافية للبحث
    if (!empty($filters)) {
        mergeFilters($whereClauses, $params, $filters);
    }
    
    // ترتيب النتائج
    $orderBy = determineOrder($filters);
    
    // إنشاء جملة WHERE إذا كانت هناك شروط بحث
    $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // حساب إجمالي عدد النتائج
    $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
    $totalResultsData = $db->fetchOne($countSql, $params);
    $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
    
    // لا توجد نتائج، لا حاجة للاستعلام الرئيسي
    if ($totalResults == 0) {
        return [
            'results' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit,
            'pages' => 0,
            'method' => $method,
            'success' => true,
            'message' => 'لم يتم العثور على نتائج تطابق معايير البحث'
        ];
    }
    
    // استعلام البحث الرئيسي
    $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
    $searchResults = $db->fetchAll($searchSql, $params);
    
    // حساب عدد الصفحات
    $totalPages = ceil($totalResults / $limit);
    
    // إعادة النتائج
    return [
        'results' => $searchResults,
        'total' => $totalResults,
        'page' => $page,
        'limit' => $limit,
        'pages' => $totalPages,
        'method' => $method,
        'success' => true
    ];
}

/**
 * معالجة استعلام البحث بالاسم التجاري
 * 
 * @param string $query استعلام البحث
 * @return array معلومات معالجة الاستعلام
 */
function processTradeNameQuery($query) {
    // تعامل مع المسافات كبدائل للأحرف
    $pattern = str_replace(' ', '_', $query);
    
    // تعامل مع علامة النجمة
    $pattern = str_replace('*', '%', $pattern);
    
    // تحويل العلامة X إلى علامة استفهام (حرف واحد)
    $pattern = str_replace(['x', 'X'], '_', $pattern);
    
    return [
        'original' => $query,
        'pattern' => $pattern
    ];
}

/**
 * بناء استعلام البحث بطريقة جوجل (البحث الصوتي)
 * 
 * @param string $query استعلام البحث
 * @param array &$params معلمات الاستعلام (مرجع)
 * @return string جزء WHERE من استعلام SQL
 */
function buildGoogleStyleQuery($query, &$params) {
    $searchTerms = [];
    
    // تحويل النص العربي لما يقابله في الإنجليزية
    $phoneticQuery = arabicToEnglishPhonetic($query);
    
    // إنشاء استعلام للبحث بالتشابه الصوتي
    $searchTerms[] = "SOUNDEX(trade_name) = SOUNDEX(?)";
    $params[] = $query;
    
    // استعلام لمطابقة النص الناتج عن التحويل الصوتي
    $searchTerms[] = "SOUNDEX(trade_name) = SOUNDEX(?)";
    $params[] = $phoneticQuery;
    
    // البحث النصي العادي أيضاً
    $searchTerms[] = "trade_name LIKE ?";
    $params[] = "%" . $query . "%";
    
    // بحث عكسي للتعامل مع الكتابة العكسية
    $searchTerms[] = "trade_name LIKE ?";
    $params[] = "%" . strrev($query) . "%";
    
    // البحث بالمادة الفعالة
    $searchTerms[] = "scientific_name LIKE ?";
    $params[] = "%" . $query . "%";
    
    // البحث بالأسماء العربية
    $searchTerms[] = "arabic_name LIKE ?";
    $params[] = "%" . $query . "%";
    
    // تجميع شروط البحث
    return "(" . implode(" OR ", $searchTerms) . ")";
}

/**
 * تحويل النص العربي إلى ما يقابله من أحرف إنجليزية للبحث الصوتي
 * 
 * @param string $arabicText النص العربي
 * @return string النص المقابل بالأحرف الإنجليزية
 */
function arabicToEnglishPhonetic($arabicText) {
    $arabicToEnglish = [
        'ا' => 'a', 'أ' => 'a', 'إ' => 'e', 'آ' => 'a',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th',
        'ج' => 'g', 'ح' => 'h', 'خ' => 'kh',
        'د' => 'd', 'ذ' => 'th', 'ر' => 'r',
        'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't',
        'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh',
        'ف' => 'f', 'ق' => 'k', 'ك' => 'k',
        'ل' => 'l', 'م' => 'm', 'ن' => 'n',
        'هـ' => 'h', 'ه' => 'h', 'و' => 'w', 'ي' => 'y',
        'ى' => 'a', 'ئ' => 'e', 'ء' => '\'',
        'ؤ' => 'o', 'ة' => 'a'
    ];
    
    // تحويل الأحرف من العربية إلى الإنجليزية
    $englishText = '';
    $arabicChars = mb_str_split($arabicText, 1, 'UTF-8');
    
    foreach ($arabicChars as $char) {
        if (isset($arabicToEnglish[$char])) {
            $englishText .= $arabicToEnglish[$char];
        } else {
            $englishText .= $char;
        }
    }
    
    // مطابقة للأحرف المتشابهة في النطق
    $phoneticsMap = [
        'ph' => 'f',
        'gh' => 'g',
        'ch' => 'k',
        'x' => 'ks',
        'q' => 'k',
        'c' => 'k',
        'ks' => 'x',
        'cs' => 'x',
        'ce' => 's',
        'ci' => 's',
        'ew' => 'oo',
        'ie' => 'ee',
        'oo' => 'u',
        'kc' => 'x',
        'sc' => 's',
        'xc' => 'x'
    ];
    
    // تطبيق التعديلات على الأحرف المتشابهة في النطق
    foreach ($phoneticsMap as $source => $target) {
        $englishText = str_ireplace($source, $target, $englishText);
    }
    
    return $englishText;
}

/**
 * دمج الفلاتر الإضافية مع شروط البحث
 * 
 * @param array &$whereClauses شروط استعلام WHERE (مرجع)
 * @param array &$params معلمات الاستعلام (مرجع)
 * @param array $filters الفلاتر الإضافية للبحث
 */
function mergeFilters(&$whereClauses, &$params, $filters) {
    // فلتر التصنيف
    if (!empty($filters['category'])) {
        $whereClauses[] = "category LIKE ?";
        $params[] = "%" . $filters['category'] . "%";
    }
    
    // فلتر الشركة المنتجة
    if (!empty($filters['company'])) {
        $whereClauses[] = "company = ?";
        $params[] = $filters['company'];
    }
    
    // فلتر المادة الفعالة
    if (!empty($filters['scientific_name'])) {
        $whereClauses[] = "scientific_name LIKE ?";
        $params[] = "%" . $filters['scientific_name'] . "%";
    }
    
    // فلتر الحد الأدنى للسعر
    if (isset($filters['price_min']) && is_numeric($filters['price_min'])) {
        $whereClauses[] = "current_price >= ?";
        $params[] = (float)$filters['price_min'];
    }
    
    // فلتر الحد الأقصى للسعر
    if (isset($filters['price_max']) && is_numeric($filters['price_max'])) {
        $whereClauses[] = "current_price <= ?";
        $params[] = (float)$filters['price_max'];
    }
    
    // فلتر التركيز/الشكل الصيدلاني
    if (!empty($filters['formulation'])) {
        $whereClauses[] = "(strength LIKE ? OR form LIKE ?)";
        $params[] = "%" . $filters['formulation'] . "%";
        $params[] = "%" . $filters['formulation'] . "%";
    }
    
    // فلتر عدد الوحدات
    if (!empty($filters['units']) && is_numeric($filters['units'])) {
        $whereClauses[] = "units_per_package = ?";
        $params[] = (int)$filters['units'];
    }
    
    // فلتر الحد الأدنى لعدد الوحدات
    if (!empty($filters['units_min']) && is_numeric($filters['units_min'])) {
        $whereClauses[] = "units_per_package >= ?";
        $params[] = (int)$filters['units_min'];
    }
    
    // فلتر الباركود
    if (!empty($filters['barcode'])) {
        $whereClauses[] = "barcode = ?";
        $params[] = $filters['barcode'];
    }
}

/**
 * تحديد ترتيب نتائج البحث
 * 
 * @param array $filters الفلاتر المستخدمة في البحث
 * @return string جملة ORDER BY للاستعلام
 */
function determineOrder($filters) {
    // ترتيب افتراضي
    $orderBy = "id DESC";
    
    // إذا تم تحديد ترتيب محدد
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
            case 'relevance':
                // يمكن إضافة منطق لترتيب النتائج حسب الأهمية
                $orderBy = "visit_count DESC, id DESC";
                break;
        }
    }
    
    return $orderBy;
}
?>