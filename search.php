<?php
// search.php - صفحة البحث المتقدم المطورة مع دعم الذكاء الاصطناعي
require_once 'config/database.php';

// تضمين ملفات الذكاء الاصطناعي والتكوين
require_once 'ai_config.php';
require_once 'ai_search_engine.php';

// تأكد من أن ملف stats_tracking.php موجود قبل استيراده
$statsTrackingPath = 'includes/stats_tracking.php';
if (file_exists($statsTrackingPath)) {
    require_once $statsTrackingPath;
}

// تأكد من أن ملف search_engine.php موجود قبل استيراده
$searchEnginePath = 'search_engine.php';
if (file_exists($searchEnginePath)) {
    require_once $searchEnginePath;
}

// إعداد معلومات الصفحة
$pageTitle = 'بحث متقدم';
$currentPage = 'search';

$db = Database::getInstance();

// إنشاء كائنات تتبع الإحصائيات ومحرك البحث
$statsTracker = null;
if (class_exists('StatsTracker')) {
    $statsTracker = new StatsTracker($db);
}

// تهيئة محرك البحث المناسب
$searchEngine = null;
$aiSearchEngine = null;

// التحقق مما إذا كان البحث المعزز بالذكاء الاصطناعي مفعل
$useAiSearch = isAISearchEnabled();

if ($useAiSearch) {
    // إنشاء محرك البحث المعزز بالذكاء الاصطناعي
    $aiSearchEngine = new AISearchEngine($db, DEEPSEEK_API_KEY, DEEPSEEK_API_ENDPOINT);
}

// إنشاء محرك البحث التقليدي كنسخة احتياطية
if (class_exists('SearchEngine')) {
    $searchEngine = new SearchEngine($db);
}

// معالجة البحث
$query = '';
$searchResults = [];
$totalResults = 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$totalPages = 1;
$aiResponse = null;
$queryAnalysis = null;
$isChatQuery = false;

// إعداد فلاتر البحث
$filters = [
    'category' => isset($_GET['category']) ? $_GET['category'] : '',
    'company' => isset($_GET['company']) ? $_GET['company'] : '',
    'scientific_name' => isset($_GET['scientific_name']) ? $_GET['scientific_name'] : '',
    'price_min' => isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? (float)$_GET['price_min'] : null,
    'price_max' => isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? (float)$_GET['price_max'] : null,
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'relevance',
];

// البحث العام
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $query = trim($_GET['q']);
    
    // تسجيل عملية البحث
    if ($statsTracker) {
        $statsTracker->trackSearch($query);
    }
    
    // التحقق مما إذا كان هذا استعلام دردشة
    $isChatQuery = $useAiSearch && isChatSearchQuery($query);
    
    // تنفيذ البحث باستخدام محرك البحث المناسب
    if ($useAiSearch) {
        if ($isChatQuery) {
            // استخدام بحث الدردشة
            $searchData = $aiSearchEngine->chatSearch($query, [], $filters, $page, $limit);
            $aiResponse = $searchData['chat_response'] ?? null;
        } else {
            // استخدام البحث العادي المعزز بالذكاء الاصطناعي
            $searchData = $aiSearchEngine->search($query, $filters, $page, $limit);
        }
        
        $searchResults = $searchData['results'] ?? [];
        $totalResults = $searchData['total'] ?? 0;
        $totalPages = $searchData['pages'] ?? 1;
        $queryAnalysis = $searchData['query_analysis'] ?? null;
    } else if ($searchEngine) {
        // استخدام محرك البحث التقليدي
        $searchData = $searchEngine->search($query, $filters, $page, $limit);
        $searchResults = $searchData['results'];
        $totalResults = $searchData['total'];
        $totalPages = $searchData['pages'];
        $queryAnalysis = $searchData['query_analysis'] ?? null;
    } else {
        // بحث بسيط إذا لم يكن أي من محركات البحث متاحة
        $whereClauses = [];
        $params = [];
        
        $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR barcode = ?)";
        $searchParam = "%$query%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $query;
        
        // إضافة فلاتر البحث
        if (!empty($filters['category'])) {
            $whereClauses[] = "category LIKE ?";
            $params[] = "%" . $filters['category'] . "%";
        }
        
        if (!empty($filters['company'])) {
            $whereClauses[] = "company = ?";
            $params[] = $filters['company'];
        }
        
        if (isset($filters['price_min'])) {
            $whereClauses[] = "current_price >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (isset($filters['price_max'])) {
            $whereClauses[] = "current_price <= ?";
            $params[] = $filters['price_max'];
        }
        
        // ترتيب النتائج
        $orderBy = "id DESC";
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
        $offset = ($page - 1) * $limit;
        
        // عدد النتائج الكلي
        $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
        $totalResultsData = $db->fetchOne($countSql, $params);
        $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
        
        // جلب نتائج البحث
        $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
        $searchResults = $db->fetchAll($searchSql, $params);
        
        // حساب عدد الصفحات
        $totalPages = ceil($totalResults / $limit);
    }
}
// البحث بالفلاتر فقط
elseif (!empty($filters['category']) || !empty($filters['company']) || 
        !empty($filters['scientific_name']) || isset($filters['price_min']) || 
        isset($filters['price_max']) || isset($_GET['show_all'])) {
    
    // بناء استعلام البحث
    $whereClauses = [];
    $params = [];
    
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
    
    if (isset($filters['price_min'])) {
        $whereClauses[] = "current_price >= ?";
        $params[] = $filters['price_min'];
    }
    
    if (isset($filters['price_max'])) {
        $whereClauses[] = "current_price <= ?";
        $params[] = $filters['price_max'];
    }
    
    // إعداد ترتيب النتائج
    $orderBy = "id DESC";
    
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
    $offset = ($page - 1) * $limit;
    
    // عدد النتائج الكلي
    $countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
    $totalResultsData = $db->fetchOne($countSql, $params);
    $totalResults = $totalResultsData ? $totalResultsData['total'] : 0;
    
    // جلب نتائج البحث
    $searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";
    $searchResults = $db->fetchAll($searchSql, $params);
    
    // حساب عدد الصفحات
    $totalPages = ceil($totalResults / $limit);
}

// جلب جميع التصنيفات
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");

// جلب جميع الشركات المنتجة
$companies = $db->fetchAll("SELECT DISTINCT company FROM medications WHERE company != '' ORDER BY company");

// جلب أكثر عمليات البحث شيوعاً
$topSearchTerms = [];
if ($statsTracker) {
    $topSearchTerms = $statsTracker->getTopSearchTerms(10);
}

// جلب الأدوية الأكثر زيارة
$mostVisitedMeds = [];
if ($statsTracker) {
    $mostVisitedMeds = $statsTracker->getMostVisitedMedications(5);
}

// جلب المعلومات الإضافية من تحليل الاستعلام
$suggestions = [];
$aiComment = null;
$alternativeSuggestions = [];
$isEnhancedSearch = false;

if ($queryAnalysis) {
    $suggestions = $queryAnalysis['suggestions'] ?? [];
    $aiComment = $searchData['ai_comment'] ?? null;
    $alternativeSuggestions = $searchData['alternative_suggestions'] ?? [];
    $isEnhancedSearch = isset($searchData['query_analysis']['enhanced']) && $searchData['query_analysis']['enhanced'];
}

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">بحث متقدم عن الأدوية</h1>
    
    <!-- قسم نصائح البحث والبحث المعزز بالذكاء الاصطناعي -->
    <?php if (empty($_GET['q']) && empty($_GET['category']) && empty($_GET['company']) && empty($_GET['show_all'])): ?>
    <div class="card mb-4 search-tips-section">
        <?php if ($useAiSearch): ?>
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> نصائح للبحث المتقدم</h5>
            <span class="badge bg-light text-primary"><i class="fas fa-robot me-1"></i> البحث الذكي مفعّل</span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="search-tip-item">
                        <strong><i class="fas fa-comment-dots text-primary me-2"></i> اطرح أسئلتك بلغة طبيعية</strong>
                        <p>يمكنك البحث بأسلوب السؤال مثل: <code>ما هو أفضل دواء لعلاج الصداع النصفي؟</code></p>
                    </div>
                    
                    <div class="search-tip-item">
                        <strong><i class="fas fa-exchange-alt text-success me-2"></i> ابحث عن البدائل</strong>
                        <p>يمكنك البحث عن البدائل مثل: <code>أريد بديل أرخص للكونكور</code> أو <code>بديل لباراسيتامول</code></p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="search-tip-item">
                        <strong><i class="fas fa-heartbeat text-danger me-2"></i> البحث بالأعراض</strong>
                        <p>يمكنك البحث حسب الأعراض مثل: <code>أعاني من صداع وحرارة</code> أو <code>دواء للسعال الجاف</code></p>
                    </div>
                    
                    <div class="search-tip-item">
                        <strong><i class="fas fa-info-circle text-info me-2"></i> البحث بمعلومات جزئية</strong>
                        <p>يمكنك البحث بمعلومات جزئية مثل: <code>دواء إيطالي للمعدة</code> أو <code>دواء يبدأ بحرف س</code></p>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> نصائح للبحث</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="search-tip-item">
                        <strong><i class="fas fa-plus-circle text-success me-2"></i> علامة + لتضمين كلمة</strong>
                        <p>استخدم علامة + قبل الكلمة لإلزام ظهورها في نتائج البحث. مثال: <code>+باراسيتامول حبوب</code></p>
                    </div>
                    
                    <div class="search-tip-item">
                        <strong><i class="fas fa-minus-circle text-danger me-2"></i> علامة - لاستبعاد كلمة</strong>
                        <p>استخدم علامة - قبل الكلمة لاستبعادها من نتائج البحث. مثال: <code>مسكن -حقن</code></p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="search-tip-item">
                        <strong><i class="fas fa-asterisk text-primary me-2"></i> علامة * للبحث الجزئي</strong>
                        <p>استخدم علامة * لاستبدال حرف أو أكثر. مثال: <code>برو*</code> ستظهر نتائج مثل بروفين، بروكسي، إلخ.</p>
                    </div>
                    
                    <div class="search-tip-item">
                        <strong><i class="fas fa-quote-right text-info me-2"></i> علامات الاقتباس " " للعبارات</strong>
                        <p>ضع العبارة بين علامتي اقتباس للبحث عنها كما هي بالضبط. مثال: <code>"فيتامين د"</code></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- عرض أكثر عمليات البحث شيوعاً والأدوية الأكثر زيارة -->
    <?php if (!isset($_GET['q']) && !isset($_GET['category']) && !isset($_GET['company']) && !isset($_GET['show_all'])): ?>
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i> أكثر عمليات البحث شيوعاً</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($topSearchTerms)): ?>
                        <div class="search-terms-cloud">
                            <?php foreach ($topSearchTerms as $term): ?>
                                <a href="search.php?q=<?php echo urlencode($term['search_term']); ?>" class="search-term-tag" style="font-size: <?php echo min(100, max(80, 80 + ($term['search_count'] * 5))); ?>%;">
                                    <?php echo $term['search_term']; ?>
                                    <span class="search-count"><?php echo $term['search_count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> لا تتوفر بيانات كافية حتى الآن.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> الأدوية الأكثر زيارة</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($mostVisitedMeds)): ?>
                        <ul class="list-group">
                            <?php foreach ($mostVisitedMeds as $index => $med): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                        <a href="medication.php?id=<?php echo $med['id']; ?>" class="text-decoration-none">
                                            <?php echo $med['trade_name']; ?>
                                        </a>
                                    </div>
                                    <span class="badge bg-secondary"><?php echo number_format($med['visit_count']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> لا تتوفر بيانات كافية حتى الآن.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- شريط البحث الجانبي -->
        <div class="col-lg-3 mb-4">
            <div class="search-sidebar shadow-sm">
                <form action="search.php" method="GET">
                    <!-- البحث العام -->
                    <div class="filter-section">
                        <h5 class="filter-title">البحث</h5>
                        <div class="mb-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                placeholder="اسم الدواء، المادة الفعالة، الباركود..." 
                                name="q" 
                                value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                            >
                            
                            <?php if ($useAiSearch): ?>
                            <div class="form-text">
                                <i class="fas fa-robot me-1"></i> يمكنك البحث بالأسئلة أو وصف المشكلة الصحية بلغتك الطبيعية
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- تصفية حسب التصنيف -->
                    <div class="filter-section">
                        <h5 class="filter-title">التصنيف</h5>
                        <div class="mb-3">
                            <select class="form-select" name="category">
                                <option value="">جميع التصنيفات</option>
                                <?php foreach ($categories as $category): ?>
                                    <option 
                                        value="<?php echo $category['name']; ?>"
                                        <?php echo (isset($_GET['category']) && $_GET['category'] == $category['name']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo $category['arabic_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- تصفية حسب الشركة -->
                    <div class="filter-section">
                        <h5 class="filter-title">الشركة المنتجة</h5>
                        <div class="mb-3">
                            <select class="form-select" name="company">
                                <option value="">جميع الشركات</option>
                                <?php foreach ($companies as $company): ?>
                                    <option 
                                        value="<?php echo $company['company']; ?>"
                                        <?php echo (isset($_GET['company']) && $_GET['company'] == $company['company']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo $company['company']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- تصفية حسب السعر -->
                    <div class="filter-section">
                        <h5 class="filter-title">نطاق السعر</h5>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <input 
                                        type="number" 
                                        class="form-control" 
                                        placeholder="من" 
                                        name="price_min" 
                                        min="0" 
                                        value="<?php echo isset($_GET['price_min']) ? htmlspecialchars($_GET['price_min']) : ''; ?>"
                                    >
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <input 
                                        type="number" 
                                        class="form-control" 
                                        placeholder="إلى" 
                                        name="price_max" 
                                        min="0" 
                                        value="<?php echo isset($_GET['price_max']) ? htmlspecialchars($_GET['price_max']) : ''; ?>"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ترتيب النتائج -->
                    <div class="filter-section">
                        <h5 class="filter-title">ترتيب حسب</h5>
                        <div class="mb-3">
                            <select class="form-select" name="sort">
                                <option value="relevance" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'relevance') ? 'selected' : ''; ?>>الأكثر صلة</option>
                                <option value="id_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'id_desc') ? 'selected' : ''; ?>>الأحدث</option>
                                <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>السعر: من الأقل للأعلى</option>
                                <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>السعر: من الأعلى للأقل</option>
                                <option value="name_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_asc') ? 'selected' : ''; ?>>الاسم: أ-ي</option>
                                <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_desc') ? 'selected' : ''; ?>>الاسم: ي-أ</option>
                                <option value="visits_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'visits_desc') ? 'selected' : ''; ?>>الأكثر زيارة</option>
                                <option value="date_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'date_desc') ? 'selected' : ''; ?>>تاريخ تحديث السعر</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- أزرار البحث -->
                    <div class="d-grid gap-2">
                        <?php if ($useAiSearch): ?>
                        <button type="submit" class="btn ai-search-btn">
                            <i class="fas fa-robot me-2"></i> بحث ذكي
                        </button>
                        <?php else: ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> بحث
                        </button>
                        <?php endif; ?>
                        <a href="search.php?show_all=1" class="btn btn-outline-secondary">
                            عرض كل الأدوية
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- نتائج البحث -->
        <div class="col-lg-9">
            <?php if (isset($_GET['q']) || isset($_GET['category']) || isset($_GET['company']) || isset($_GET['price_min']) || isset($_GET['price_max']) || isset($_GET['show_all'])): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4>نتائج البحث</h4>
                        <?php if ($totalResults > 0): ?>
                            <p class="text-muted">عدد النتائج: <?php echo $totalResults; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($totalResults > 0 && count($searchResults) > 0): ?>
                        <div class="d-flex">
                            <div class="btn-group me-2 view-toggle">
                                <button class="btn btn-outline-secondary active" id="gridViewBtn">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button class="btn btn-outline-secondary" id="listViewBtn">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($useAiSearch && $isChatQuery && $aiResponse): ?>
                <!-- عرض رد الدردشة من الذكاء الاصطناعي -->
                <div class="ai-chat-response mb-4">
                    <div class="card shadow-sm border-primary">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-robot me-2"></i> المساعد الذكي</h5>
                            <span class="badge bg-light text-primary">البحث المعزز بالذكاء الاصطناعي</span>
                        </div>
                        <div class="card-body">
                            <p class="lead"><?php echo $aiResponse['text']; ?></p>
                            
                            <?php if ($totalResults > 0): ?>
                            <div class="mt-3">
                                <p class="mb-1 text-muted">عدد النتائج المطابقة: <strong><?php echo $totalResults; ?></strong></p>
                                <small class="text-muted">النتائج مرتبة أدناه حسب صلتها باستعلامك</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php elseif ($useAiSearch && !empty($aiComment)): ?>
                <!-- عرض تعليق الذكاء الاصطناعي على نتائج البحث -->
                <div class="ai-search-comment mb-3">
                    <div class="alert alert-primary d-flex align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-robot me-2 fa-lg"></i>
                        </div>
                        <div>
                            <?php echo $aiComment; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($suggestions)): ?>
                <div class="search-suggestions mb-3">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i> هل تقصد: 
                        <?php foreach ($suggestions as $index => $suggestion): ?>
                            <a href="search.php?q=<?php echo urlencode($suggestion); ?>" class="me-2">
                                <?php echo $suggestion; ?>
                            </a><?php echo ($index < count($suggestions) - 1) ? '،' : ''; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- الفلاتر النشطة -->
                <?php
                $activeFilters = [];
                if (!empty($_GET['q'])) $activeFilters['q'] = 'البحث: ' . $_GET['q'];
                if (!empty($_GET['category'])) $activeFilters['category'] = 'التصنيف: ' . $_GET['category'];
                if (!empty($_GET['company'])) $activeFilters['company'] = 'الشركة: ' . $_GET['company'];
                if (isset($_GET['price_min'])) $activeFilters['price_min'] = 'السعر من: ' . $_GET['price_min'] . ' ج.م';
                if (isset($_GET['price_max'])) $activeFilters['price_max'] = 'السعر إلى: ' . $_GET['price_max'] . ' ج.م';
                
                if (!empty($activeFilters)):
                ?>
                <div class="active-filters mb-3">
                    <h6 class="mb-2">الفلاتر النشطة:</h6>
                    <div class="filter-tags">
                        <?php foreach ($activeFilters as $key => $value): ?>
                            <div class="filter-tag">
                                <?php echo $value; ?>
                                <a href="<?php 
                                    $params = $_GET;
                                    unset($params[$key]);
                                    echo '?' . http_build_query($params);
                                ?>" class="filter-remove" title="إزالة هذا الفلتر">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($activeFilters) > 1): ?>
                            <a href="search.php" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash-alt me-1"></i> إزالة كل الفلاتر
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($totalResults > 0 && count($searchResults) > 0): ?>
                    <!-- عرض النتائج بتنسيق الشبكة (الافتراضي) -->
                    <div class="search-results-grid" id="gridView">
                        <div class="grid-cards">
                            <?php foreach ($searchResults as $med): ?>
                                <div class="card med-card">
                                    <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                                        <span class="badge bg-danger">تخفيض</span>
                                    <?php endif; ?>
                                    
                                    <a href="medication.php?id=<?php echo $med['id']; ?>">
                                        <?php if (!empty($med['image_url'])): ?>
                                            <img src="<?php echo $med['image_url']; ?>" class="card-img-top" alt="<?php echo $med['trade_name']; ?>">
                                        <?php else: ?>
                                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-pills fa-3x text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <a href="medication.php?id=<?php echo $med['id']; ?>" class="text-decoration-none text-dark">
                                                <?php 
                                                if (isset($_GET['q'])) {
                                                    $highlightedName = preg_replace('/(' . preg_quote($_GET['q'], '/') . ')/i', '<span class="search-highlight">$1</span>', $med['trade_name']);
                                                    echo $highlightedName;
                                                } else {
                                                    echo $med['trade_name'];
                                                }
                                                ?>
                                            </a>
                                        </h5>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo $med['scientific_name']; ?> | 
                                            <span class="text-primary"><?php echo $med['company']; ?></span>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div>
                                                <span class="price-tag"><?php echo number_format($med['current_price'], 2); ?> ج.م</span>
                                                <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                                                    <span class="old-price d-block"><?php echo number_format($med['old_price'], 2); ?> ج.م</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="compare.php?ids=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-balance-scale"></i>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <?php if (isset($med['visit_count']) && !empty($med['visit_count'])): ?>
                                        <div class="mt-2 text-center">
                                            <span class="badge bg-light text-secondary">
                                                <i class="fas fa-eye me-1"></i> <?php echo number_format($med['visit_count']); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- عرض النتائج بتنسيق القائمة -->
                    <div class="search-results-list d-none" id="listView">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الدواء</th>
                                        <th>المادة الفعالة</th>
                                        <th>الشركة</th>
                                        <th>التصنيف</th>
                                        <th>السعر</th>
                                        <th>الزيارات</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($searchResults as $med): ?>
                                        <tr>
                                            <td>
                                                <a href="medication.php?id=<?php echo $med['id']; ?>" class="text-decoration-none">
                                                    <strong>
                                                        <?php 
                                                        if (isset($_GET['q'])) {
                                                            $highlightedName = preg_replace('/(' . preg_quote($_GET['q'], '/') . ')/i', '<span class="search-highlight">$1</span>', $med['trade_name']);
                                                            echo $highlightedName;
                                                        } else {
                                                            echo $med['trade_name'];
                                                        }
                                                        ?>
                                                    </strong>
                                                </a>
                                            </td>
                                            <td><?php echo $med['scientific_name']; ?></td>
                                            <td><?php echo $med['company']; ?></td>
                                            <td><?php echo $med['category']; ?></td>
                                            <td>
                                                <strong class="text-primary"><?php echo number_format($med['current_price'], 2); ?> ج.م</strong>
                                                <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                                                    <br>
                                                    <small class="text-muted text-decoration-line-through">
                                                        <?php echo number_format($med['old_price'], 2); ?> ج.م
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($med['visit_count']) && !empty($med['visit_count'])): ?>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format($med['visit_count']); ?>
                                                </span>
                                                <?php else: ?>
                                                <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="compare.php?ids=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-balance-scale"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- ترقيم الصفحات -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> 
                        لم يتم العثور على نتائج تطابق معايير البحث. يرجى تعديل معايير البحث والمحاولة مرة أخرى.
                    </div>
                    
                    <?php if ($useAiSearch && !empty($alternativeSuggestions)): ?>
                    <!-- عرض اقتراحات بديلة من الذكاء الاصطناعي عندما لا توجد نتائج -->
                    <div class="card mt-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> اقتراحات بديلة</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($alternativeSuggestions['similar_by_name'])): ?>
                            <h6 class="mb-3">أدوية ذات أسماء مشابهة:</h6>
                            <div class="row mb-4">
                                <?php foreach ($alternativeSuggestions['similar_by_name'] as $med): ?>
                                <div class="col-md-4 mb-2">
                                    <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-outline-primary btn-sm w-100 text-start">
                                        <?php echo $med['trade_name']; ?>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($alternativeSuggestions['alternatives'])): ?>
                            <h6 class="mb-3">أدوية بنفس المادة الفعالة (<?php echo $alternativeSuggestions['active_ingredient']; ?>):</h6>
                            <div class="row">
                                <?php foreach ($alternativeSuggestions['alternatives'] as $med): ?>
                                <div class="col-md-4 mb-2">
                                    <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-outline-success btn-sm w-100 text-start">
                                        <?php echo $med['trade_name']; ?> <small>(<?php echo $med['company']; ?>)</small>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <div class="alert alert-light mb-0">
                                    <p class="mb-0"><i class="fas fa-info-circle me-1"></i> <strong>نصائح للبحث:</strong></p>
                                    <ul class="mb-0 mt-2">
                                        <li>جرّب كتابة الاسم بطريقة مختلفة أو استخدم اسم المادة الفعالة</li>
                                        <li>استخدم علامة النجمة (*) للبحث عن جزء من الاسم</li>
                                        <li>اطرح سؤالاً مثل "ما هو بديل لـ [اسم الدواء]؟"</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center p-5 bg-light rounded">
                    <i class="fas fa-search fa-4x text-primary mb-3"></i>
                    <h4>ابحث عن الأدوية</h4>
                    <p class="text-muted">استخدم خيارات البحث للعثور على الأدوية التي تحتاجها</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript للتبديل بين عرض الشبكة والقائمة -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const gridViewBtn = document.getElementById('gridViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    
    if (gridViewBtn && listViewBtn) {
        gridViewBtn.addEventListener('click', function() {
            gridView.classList.remove('d-none');
            listView.classList.add('d-none');
            gridViewBtn.classList.add('active', 'btn-primary');
            gridViewBtn.classList.remove('btn-outline-secondary');
            listViewBtn.classList.remove('active', 'btn-primary');
            listViewBtn.classList.add('btn-outline-secondary');
        });
        
        listViewBtn.addEventListener('click', function() {
            gridView.classList.add('d-none');
            listView.classList.remove('d-none');
            gridViewBtn.classList.remove('active', 'btn-primary');
            gridViewBtn.classList.add('btn-outline-secondary');
            listViewBtn.classList.add('active', 'btn-primary');
            listViewBtn.classList.remove('btn-outline-secondary');
        });
    }
    
    // تهيئة مؤشر الكتابة للذكاء الاصطناعي
    const typingIndicator = document.querySelector('.typing-indicator');
    if (typingIndicator) {
        setTimeout(() => {
            const aiResponse = typingIndicator.closest('.card-body').querySelector('.ai-response');
            typingIndicator.style.display = 'none';
            aiResponse.style.display = 'block';
        }, 1500);
    }
});
</script>

<!-- تنسيقات إضافية للبحث المعزز بالذكاء الاصطناعي -->
<style>
.search-highlight {
    background-color: rgba(255, 193, 7, 0.3);
    padding: 0 2px;
    border-radius: 2px;
}

.search-terms-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    padding: 10px 0;
}

.search-term-tag {
    background-color: #f0f8ff;
    border: 1px solid #d7e9fc;
    border-radius: 20px;
    padding: 5px 15px;
    text-decoration: none;
    color: #0d6efd;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
}

.search-term-tag:hover {
    background-color: #0d6efd;
    color: white;
}

.search-count {
    background-color: rgba(13, 110, 253, 0.2);
    color: #0d6efd;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 5px;
    margin-left: 5px;
}

.search-term-tag:hover .search-count {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
}

.active-filters {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.filter-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.filter-tag {
    background-color: white;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.filter-remove {
    color: #dc3545;
    margin-right: 5px;
    text-decoration: none;
}

.filter-remove:hover {
    color: #bd2130;
}

/* تنسيقات البحث المعزز بالذكاء الاصطناعي */
.ai-chat-response {
    animation: fadeIn 0.5s ease-in-out;
}

.ai-chat-response .card {
    border-width: 2px;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
}

.ai-search-comment {
    animation: slideInFromTop 0.5s ease-in-out;
}

.ai-search-comment .alert {
    border-right: 4px solid #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

@keyframes slideInFromTop {
    0% {
        transform: translateY(-20px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

/* مؤشر الكتابة للذكاء الاصطناعي */
.typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-right: 6px;
}

.typing-indicator span {
    display: inline-block;
    width: 6px;
    height: 6px;
    background-color: #0d6efd;
    border-radius: 50%;
    opacity: 0.6;
    animation: typingAnimation 1s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typingAnimation {
    0%, 100% {
        transform: translateY(0);
        opacity: 0.6;
    }
    50% {
        transform: translateY(-4px);
        opacity: 1;
    }
}

/* زر البحث المعزز بالذكاء الاصطناعي */
.ai-search-btn {
    background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
    color: white;
    border: none;
    position: relative;
    overflow: hidden;
    transition: all 0.3s;
}

.ai-search-btn::before {
    content: '';
    position: absolute;
    top: 0;
    right: -50%;
    width: 150%;
    height: 100%;
    background: rgba(255, 255, 255, 0.2);
    transform: skewX(-25deg);
    transition: all 0.4s;
}

.ai-search-btn:hover::before {
    right: -180%;
}

.ai-search-btn:hover {
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    transform: translateY(-2px);
}

.ai-badge {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    border: 1px solid rgba(13, 110, 253, 0.2);
    transition: all 0.3s;
}

.ai-badge i {
    font-size: 0.8rem;
}

.ai-badge:hover {
    background-color: #0d6efd;
    color: white;
}
</style>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>