<?php
// api/index.php - نقطة الدخول الرئيسية للـ API

// السماح بـ CORS لتمكين الوصول من خارج النطاق
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// إذا كان الطلب هو OPTIONS (للتحقق المسبق)، قم بإنهاء الطلب هنا
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// استيراد الملفات اللازمة
require_once '../config/database.php';
require_once 'api_utils.php';

// التحقق من وجود ملفات الذكاء الاصطناعي والتكوين (اختياري)
$useAiSearch = false;
if (file_exists('../ai_config.php') && file_exists('../ai_search_engine.php')) {
    require_once '../ai_config.php';
    require_once '../ai_search_engine.php';
    $useAiSearch = function_exists('isAISearchEnabled') ? isAISearchEnabled() : false;
}

// التحقق من وجود ملف محرك البحث
if (file_exists('../search_engine.php')) {
    require_once '../search_engine.php';
}

// التحقق من وجود ملف دوال البحث
if (file_exists('../search_function.php')) {
    require_once '../search_function.php';
}

// التحقق من وجود ملف تتبع الإحصائيات
if (file_exists('../includes/stats_tracking.php')) {
    require_once '../includes/stats_tracking.php';
}

// الحصول على مثيل قاعدة البيانات
$db = Database::getInstance();

// إنشاء كائن تتبع الإحصائيات إذا كان متاحًا
$statsTracker = null;
if (class_exists('StatsTracker')) {
    $statsTracker = new StatsTracker($db);
}

// تهيئة محرك البحث المناسب
$searchEngine = null;
$aiSearchEngine = null;

// تهيئة محرك البحث المعزز بالذكاء الاصطناعي إذا كان متاحًا
if ($useAiSearch && defined('sk-5446781b804f451d971e021178d13c85')) {
    $aiSearchEngine = new AISearchEngine($db, DEEPSEEK_API_KEY, DEEPSEEK_API_ENDPOINT);
}

// إنشاء محرك البحث التقليدي
if (class_exists('SearchEngine')) {
    $searchEngine = new SearchEngine($db);
}

// تحليل مسار URL للتعرف على المورد المطلوب
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/api/';
$endpoint = substr($requestUri, strpos($requestUri, $basePath) + strlen($basePath));
$endpoint = trim($endpoint, '/');
$parts = explode('/', $endpoint);

// الفئة الرئيسية للمورد (مثل medications, categories, إلخ)
$resourceType = $parts[0] ?? '';

// المعرف أو الإجراء الثانوي (مثل search, المعرف)
$resourceAction = $parts[1] ?? '';

// الحصول على مدخلات الطلب
$requestData = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $requestData = $_GET;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true) ?? [];
    // دمج أي معلمات من POST
    $requestData = array_merge($requestData, $_POST);
}

// تنفيذ الإجراء المناسب بناءً على المورد والإجراء
switch ($resourceType) {
    case 'medications':
        handleMedicationsEndpoint($resourceAction, $requestData, $db, $searchEngine, $aiSearchEngine, $statsTracker);
        break;
        
    case 'categories':
        handleCategoriesEndpoint($resourceAction, $requestData, $db);
        break;
        
    case 'statistics':
        handleStatisticsEndpoint($resourceAction, $requestData, $db, $statsTracker);
        break;
        
    case 'suggestions':
        handleSuggestionsEndpoint($resourceAction, $requestData, $db, $searchEngine);
        break;
        
    case 'companies':
        handleCompaniesEndpoint($resourceAction, $requestData, $db);
        break;
        
    case 'scientific-names':
        handleScientificNamesEndpoint($resourceAction, $requestData, $db);
        break;
        
    default:
        // إذا لم يتم العثور على المورد المطلوب
        sendResponse(['error' => 'Resource not found'], 404);
        break;
}

/**
 * التعامل مع نقاط النهاية الخاصة بالأدوية
 */
function handleMedicationsEndpoint($action, $requestData, $db, $searchEngine, $aiSearchEngine, $statsTracker) {
    switch ($action) {
        case 'search':
            // البحث العام عن الأدوية
            handleMedicationSearch($requestData, $db, $searchEngine, $statsTracker);
            break;
            
        case 'ai-search':
            // البحث المعزز بالذكاء الاصطناعي
            if ($aiSearchEngine) {
                handleAiMedicationSearch($requestData, $aiSearchEngine, $statsTracker);
            } else {
                // إذا كان البحث المعزز بالذكاء الاصطناعي غير متاح
                sendResponse(['error' => 'AI search is not available'], 501);
            }
            break;
            
        case 'chat-search':
            // البحث بطريقة الدردشة
            if ($aiSearchEngine) {
                handleChatMedicationSearch($requestData, $aiSearchEngine, $statsTracker);
            } else {
                // إذا كان البحث بالدردشة غير متاح
                sendResponse(['error' => 'Chat search is not available'], 501);
            }
            break;
            
        case 'compare':
            // مقارنة بين الأدوية
            handleMedicationCompare($requestData, $db, $statsTracker);
            break;
            
        default:
            // إذا كان الإجراء هو معرف دواء
            if (is_numeric($action)) {
                handleGetMedicationDetails((int)$action, $db, $statsTracker);
            } else {
                // إذا لم يتم العثور على الإجراء المطلوب
                sendResponse(['error' => 'Action not found'], 404);
            }
            break;
    }
}

/**
 * التعامل مع نقاط النهاية الخاصة بالتصنيفات
 */
function handleCategoriesEndpoint($action, $requestData, $db) {
    if (empty($action)) {
        // الحصول على قائمة التصنيفات
        $categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");
        sendResponse(['categories' => $categories]);
    } else if (is_numeric($action)) {
        // الحصول على تفاصيل تصنيف محدد
        $categoryId = (int)$action;
        $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
        
        if ($category) {
            // الحصول على الأدوية في هذا التصنيف
            $medications = $db->fetchAll(
                "SELECT * FROM medications WHERE category LIKE ? ORDER BY trade_name LIMIT 24", 
                ['%' . $category['name'] . '%']
            );
            
            sendResponse([
                'category' => $category,
                'medications' => $medications,
                'total' => count($medications)
            ]);
        } else {
            sendResponse(['error' => 'Category not found'], 404);
        }
    } else {
        sendResponse(['error' => 'Invalid category ID'], 400);
    }
}

/**
 * التعامل مع نقاط النهاية الخاصة بالإحصائيات
 */
function handleStatisticsEndpoint($action, $requestData, $db, $statsTracker) {
    if (!$statsTracker) {
        sendResponse(['error' => 'Statistics tracking is not available'], 501);
        return;
    }
    
    // الحصول على الإحصائيات العامة
    $mostVisited = $statsTracker->getMostVisitedMedications(10);
    $mostSearched = $statsTracker->getMostSearchedMedications(10);
    $topSearchTerms = $statsTracker->getTopSearchTerms(10);
    
    // إحصائيات إضافية
    $totalMeds = $db->fetchOne("SELECT COUNT(*) as total FROM medications")['total'];
    $totalVisits = $db->fetchOne("SELECT SUM(visit_count) as total FROM medications")['total'] ?? 0;
    $totalSearches = $db->fetchOne("SELECT SUM(search_count) as total FROM medications")['total'] ?? 0;
    $avgPrice = $db->fetchOne("SELECT AVG(current_price) as avg FROM medications")['avg'] ?? 0;
    
    sendResponse([
        'most_visited' => $mostVisited,
        'most_searched' => $mostSearched,
        'top_search_terms' => $topSearchTerms,
        'general_stats' => [
            'total_medications' => (int)$totalMeds,
            'total_visits' => (int)$totalVisits,
            'total_searches' => (int)$totalSearches,
            'average_price' => (float)$avgPrice
        ]
    ]);
}

/**
 * التعامل مع نقاط النهاية الخاصة بالاقتراحات
 */
function handleSuggestionsEndpoint($action, $requestData, $db, $searchEngine) {
    $query = $requestData['q'] ?? '';
    $limit = (int)($requestData['limit'] ?? 10);
    
    if (empty($query)) {
        sendResponse(['error' => 'Query parameter is required'], 400);
        return;
    }
    
    if ($searchEngine) {
        // استخدام محرك البحث لتوليد الاقتراحات
        $suggestions = [];
        
        // التحقق من وجود الدالة المناسبة في محرك البحث
        if (method_exists($searchEngine, 'generateSuggestions')) {
            $suggestions = $searchEngine->generateSuggestions($query, $limit);
        } else {
            // استعلام بسيط لاقتراح أدوية مشابهة
            $sql = "
                SELECT DISTINCT trade_name 
                FROM medications 
                WHERE trade_name LIKE ? 
                LIMIT ?
            ";
            
            $results = $db->fetchAll($sql, [$query . "%", $limit]);
            
            foreach ($results as $result) {
                if (strtolower($result['trade_name']) !== strtolower($query)) {
                    $suggestions[] = $result['trade_name'];
                }
            }
        }
        
        sendResponse(['suggestions' => $suggestions]);
    } else {
        // استعلام بسيط لاقتراح أدوية مشابهة
        $sql = "
            SELECT DISTINCT trade_name 
            FROM medications 
            WHERE trade_name LIKE ? 
            LIMIT ?
        ";
        
        $results = $db->fetchAll($sql, [$query . "%", $limit]);
        
        $suggestions = [];
        foreach ($results as $result) {
            if (strtolower($result['trade_name']) !== strtolower($query)) {
                $suggestions[] = $result['trade_name'];
            }
        }
        
        sendResponse(['suggestions' => $suggestions]);
    }
}

/**
 * التعامل مع نقاط النهاية الخاصة بالشركات
 */
function handleCompaniesEndpoint($action, $requestData, $db) {
    // الحصول على قائمة الشركات
    $companies = $db->fetchAll("SELECT DISTINCT company FROM medications WHERE company != '' ORDER BY company");
    sendResponse(['companies' => $companies]);
}

/**
 * التعامل مع نقاط النهاية الخاصة بالمواد الفعالة
 */
function handleScientificNamesEndpoint($action, $requestData, $db) {
    $limit = (int)($requestData['limit'] ?? 100);
    
    // الحصول على قائمة المواد الفعالة
    $scientificNames = $db->fetchAll(
        "SELECT DISTINCT scientific_name FROM medications WHERE scientific_name != '' GROUP BY scientific_name ORDER BY COUNT(*) DESC LIMIT ?",
        [$limit]
    );
    
    sendResponse(['scientific_names' => $scientificNames]);
}

/**
 * التعامل مع البحث العام عن الأدوية
 */
function handleMedicationSearch($requestData, $db, $searchEngine, $statsTracker) {
    $query = $requestData['q'] ?? '';
    $page = (int)($requestData['page'] ?? 1);
    $limit = (int)($requestData['limit'] ?? 12);
    $searchMethod = $requestData['method'] ?? 'trade_name';
    
    // التحقق من صحة طريقة البحث
    $validMethods = ['trade_name', 'trade_anypos', 'google_style', 'combined'];
    if (!in_array($searchMethod, $validMethods)) {
        $searchMethod = 'trade_name';
    }
    
    // إعداد فلاتر البحث
    $filters = [
        'category' => $requestData['category'] ?? '',
        'company' => $requestData['company'] ?? '',
        'scientific_name' => $requestData['scientific_name'] ?? '',
        'price_min' => isset($requestData['price_min']) && is_numeric($requestData['price_min']) ? (float)$requestData['price_min'] : null,
        'price_max' => isset($requestData['price_max']) && is_numeric($requestData['price_max']) ? (float)$requestData['price_max'] : null,
        'sort' => $requestData['sort'] ?? 'relevance'
    ];
    
    // تسجيل عملية البحث إذا كان متاحًا
    if ($statsTracker && !empty($query)) {
        $statsTracker->trackSearch($query);
    }
    
    // تنفيذ البحث
    if ($searchEngine) {
        // استخدام محرك البحث المتقدم
        $searchData = $searchEngine->search($query, $filters, $page, $limit, $searchMethod);
    } else if (function_exists('advancedSearch')) {
        // استخدام دالة البحث المتقدم
        $searchData = advancedSearch($db, $query, $searchMethod, $filters, $page, $limit);
    } else {
        // تنفيذ بحث بسيط
        $searchData = performSimpleSearch($db, $query, $filters, $page, $limit);
    }
    
    sendResponse($searchData);
}

/**
 * التعامل مع البحث المعزز بالذكاء الاصطناعي
 */
function handleAiMedicationSearch($requestData, $aiSearchEngine, $statsTracker) {
    $query = $requestData['q'] ?? '';
    $page = (int)($requestData['page'] ?? 1);
    $limit = (int)($requestData['limit'] ?? 12);
    
    // إعداد فلاتر البحث
    $filters = [
        'category' => $requestData['category'] ?? '',
        'company' => $requestData['company'] ?? '',
        'scientific_name' => $requestData['scientific_name'] ?? '',
        'price_min' => isset($requestData['price_min']) && is_numeric($requestData['price_min']) ? (float)$requestData['price_min'] : null,
        'price_max' => isset($requestData['price_max']) && is_numeric($requestData['price_max']) ? (float)$requestData['price_max'] : null,
        'sort' => $requestData['sort'] ?? 'relevance'
    ];
    
    // تسجيل عملية البحث إذا كان متاحًا
    if ($statsTracker && !empty($query)) {
        $statsTracker->trackSearch($query);
    }
    
    // تنفيذ البحث المعزز بالذكاء الاصطناعي
    $searchData = $aiSearchEngine->search($query, $filters, $page, $limit);
    
    sendResponse($searchData);
}

/**
 * التعامل مع البحث بطريقة الدردشة
 */
function handleChatMedicationSearch($requestData, $aiSearchEngine, $statsTracker) {
    $query = $requestData['q'] ?? '';
    $page = (int)($requestData['page'] ?? 1);
    $limit = (int)($requestData['limit'] ?? 12);
    
    // تسجيل عملية البحث إذا كان متاحًا
    if ($statsTracker && !empty($query)) {
        $statsTracker->trackSearch($query);
    }
    
    // تحضير سجل المحادثة (إذا كان متاحًا)
    $chatHistory = [];
    if (isset($requestData['history']) && is_array($requestData['history'])) {
        $chatHistory = $requestData['history'];
    }
    
    // إعداد فلاتر البحث
    $filters = [
        'category' => $requestData['category'] ?? '',
        'company' => $requestData['company'] ?? '',
        'scientific_name' => $requestData['scientific_name'] ?? '',
        'price_min' => isset($requestData['price_min']) && is_numeric($requestData['price_min']) ? (float)$requestData['price_min'] : null,
        'price_max' => isset($requestData['price_max']) && is_numeric($requestData['price_max']) ? (float)$requestData['price_max'] : null,
        'sort' => $requestData['sort'] ?? 'relevance'
    ];
    
    // تنفيذ البحث بطريقة الدردشة
    $searchData = $aiSearchEngine->chatSearch($query, $chatHistory, $filters, $page, $limit);
    
    sendResponse($searchData);
}

/**
 * التعامل مع الحصول على تفاصيل دواء
 */
function handleGetMedicationDetails($medicationId, $db, $statsTracker) {
    // الحصول على بيانات الدواء
    $medication = $db->fetchOne("SELECT * FROM medications WHERE id = ?", [$medicationId]);
    
    if (!$medication) {
        sendResponse(['error' => 'Medication not found'], 404);
        return;
    }
    
    // تسجيل زيارة الدواء إذا كان متاحًا
    if ($statsTracker) {
        $statsTracker->trackMedicationVisit($medicationId);
    }
    
    // الحصول على تفاصيل الدواء الإضافية
    $medicationDetails = $db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$medicationId]);
    
    // إضافة التفاصيل إلى بيانات الدواء
    if ($medicationDetails) {
        $medication['details'] = $medicationDetails;
    }
    
    // الحصول على الأدوية المثيلة (نفس المادة الفعالة، نفس الشركة)
    $equivalentMeds = $db->fetchAll("
        SELECT * FROM medications 
        WHERE scientific_name = ? 
        AND company = ? 
        AND id != ? 
        ORDER BY current_price ASC
    ", [$medication['scientific_name'], $medication['company'], $medicationId]);
    
    // الحصول على البدائل (نفس المادة الفعالة، شركات مختلفة)
    $alternatives = $db->fetchAll("
        SELECT * FROM medications 
        WHERE scientific_name = ? 
        AND company != ? 
        ORDER BY current_price ASC
    ", [$medication['scientific_name'], $medication['company']]);
    
    // الحصول على البدائل العلاجية (مواد فعالة مختلفة ولكن لها نفس الاستخدام)
    $therapeuticAlternatives = $db->fetchAll("
        SELECT m.*, ma.similarity_score 
        FROM medications m
        JOIN medication_alternatives ma ON m.id = ma.alternative_id
        WHERE ma.medication_id = ? AND m.scientific_name != ?
        ORDER BY ma.similarity_score DESC, m.current_price ASC
        LIMIT 5
    ", [$medicationId, $medication['scientific_name']]);
    
    // إضافة معلومات إضافية
    $response = [
        'medication' => $medication,
        'equivalent_medications' => $equivalentMeds,
        'alternatives' => $alternatives,
        'therapeutic_alternatives' => $therapeuticAlternatives
    ];
    
    sendResponse($response);
}

/**
 * التعامل مع مقارنة الأدوية
 */
function handleMedicationCompare($requestData, $db, $statsTracker) {
    // الحصول على معرفات الأدوية من الطلب
    $medicationIds = [];
    
    if (isset($requestData['ids']) && !empty($requestData['ids'])) {
        // تقسيم سلسلة معرفات الأدوية المفصولة بفواصل
        $medicationIds = array_map('intval', explode(',', $requestData['ids']));
    } else {
        // فحص المعلمات الفردية
        for ($i = 1; $i <= 5; $i++) {
            if (isset($requestData['id'.$i]) && is_numeric($requestData['id'.$i])) {
                $medicationIds[] = (int)$requestData['id'.$i];
            }
        }
    }
    
    if (empty($medicationIds)) {
        sendResponse(['error' => 'No medication IDs provided'], 400);
        return;
    }
    
    // الحصول على بيانات الأدوية
    $medications = [];
    
    // إنشاء علامات الاستفهام للاستعلام المعد
    $placeholders = implode(',', array_fill(0, count($medicationIds), '?'));
    
    // استعلام للحصول على بيانات الأدوية المحددة
    $medicationsData = $db->fetchAll(
        "SELECT * FROM medications WHERE id IN ($placeholders) ORDER BY FIELD(id, ".implode(',', $medicationIds).")",
        $medicationIds
    );
    
    foreach ($medicationsData as $med) {
        // تسجيل زيارة الدواء إذا كان متاحًا
        if ($statsTracker) {
            $statsTracker->trackMedicationVisit($med['id']);
        }
        
        // جلب المعلومات الإضافية للدواء
        $details = $db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$med['id']]);
        if ($details) {
            $med['details'] = $details;
        }
        
        $medications[] = $med;
    }
    
    if (empty($medications)) {
        sendResponse(['error' => 'No medications found with the provided IDs'], 404);
        return;
    }
    
    sendResponse([
        'medications' => $medications,
        'total' => count($medications)
    ]);
}

/**
 * تنفيذ بحث بسيط في حال عدم توفر محرك البحث المتقدم
 */
function performSimpleSearch($db, $query, $filters, $page, $limit) {
    $whereClauses = [];
    $params = [];
    $orderBy = "id DESC";
    $offset = ($page - 1) * $limit;
    
    // إضافة شروط البحث
    if (!empty($query)) {
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
    
    // تعيين ترتيب النتائج
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
                // الافتراضي: الترتيب حسب المعرف التنازلي
                $orderBy = "id DESC";
                break;
        }
    }
    
    // إنشاء جملة WHERE إذا كانت هناك شروط بحث
    $whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // حساب إجمالي عدد النتائج
    $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
    $totalResultsData = $db->fetchOne($countSql, $params);
    $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
    
    // استعلام البحث
    $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
    $searchResults = $db->fetchAll($searchSql, $params);
    
    // حساب عدد الصفحات
    $totalPages = ceil($totalResults / $limit);
    
    return [
        'results' => $searchResults,
        'total' => $totalResults,
        'page' => $page,
        'limit' => $limit,
        'pages' => $totalPages,
        'query' => $query,
        'filters' => $filters,
        'success' => true
    ];
}