<?php
// search_ui.php - واجهة البحث المتطورة
require_once 'config/database.php';
require_once 'search_function.php'; // استدعاء ملف دوال البحث

// إعداد معلومات الصفحة
$pageTitle = 'البحث المتطور';
$currentPage = 'search';

$db = Database::getInstance();

// الحصول على طريقة البحث من الطلب
$searchMethod = isset($_GET['method']) ? $_GET['method'] : 'trade';

// التحقق من صحة طريقة البحث
$validMethods = ['trade', 'trade_any', 'google', 'price'];
if (!in_array($searchMethod, $validMethods)) {
    $searchMethod = 'trade';
}

// إعداد معلمات البحث
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;

// إعداد الفلاتر
$filters = [
    'category' => isset($_GET['category']) ? $_GET['category'] : '',
    'company' => isset($_GET['company']) ? $_GET['company'] : '',
    'scientific_name' => isset($_GET['scientific_name']) ? $_GET['scientific_name'] : '',
    'price_min' => isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? (float)$_GET['price_min'] : null,
    'price_max' => isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? (float)$_GET['price_max'] : null,
    'formulation' => isset($_GET['formulation']) ? $_GET['formulation'] : '',
    'units' => isset($_GET['units']) ? $_GET['units'] : '',
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'relevance'
];

// تنفيذ البحث فقط إذا تم تقديم معلمات البحث
$searchResults = [];
$totalResults = 0;
$totalPages = 1;

if (!empty($searchQuery) || !empty($filters['category']) || !empty($filters['company']) || 
    !empty($filters['scientific_name']) || isset($_GET['show_all'])) {
    
    // تنفيذ البحث
    $searchData = advancedSearch($db, $searchQuery, $searchMethod, $filters, $page, $limit);
    
    // استخراج نتائج البحث
    $searchResults = $searchData['results'] ?? [];
    $totalResults = $searchData['total'] ?? 0;
    $totalPages = $searchData['pages'] ?? 1;
}

// جلب جميع التصنيفات
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");

// جلب جميع الشركات المنتجة
$companies = $db->fetchAll("SELECT DISTINCT company FROM medications WHERE company != '' ORDER BY company");

// جلب قائمة المواد الفعالة الشائعة (أعلى 30 مادة)
$scientificNames = $db->fetchAll("
    SELECT DISTINCT scientific_name 
    FROM medications 
    WHERE scientific_name != '' 
    GROUP BY scientific_name 
    ORDER BY COUNT(*) DESC 
    LIMIT 30
");

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">البحث المتطور عن الأدوية</h1>
    
    <!-- قسم نصائح البحث -->
    <div class="card mb-4 search-tips-section">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> نصائح للبحث المتطور</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="search-tip-item">
                        <strong><i class="fas fa-font text-primary me-2"></i> البحث بالاسم التجاري (من البداية)</strong>
                        <p>يكفي كتابة حرفين من بداية اسم الدواء. يمكنك استخدام المسافة _ بدلاً من الأحرف غير المعروفة.</p>
                        <p>مثال: <code>au</code> للبحث عن Augmentin | <code>ce x</code> للبحث عن Cefax</p>
                    </div>
                    
                    <div class="search-tip-item">
                        <strong><i class="fas fa-search text-success me-2"></i> البحث بالاسم التجاري (من أي موضع)</strong>
                        <p>البحث في أي موضع من الاسم (البداية، الوسط، النهاية). استخدم " " قبل الاسم للبحث من أي موضع.</p>
                        <p>مثال: <code>ment</code> للبحث عن Augmentin | <code>icil</code> للبحث عن Amoxicillin</p>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="search-tip-item">
                        <strong><i class="fas fa-microphone-alt text-danger me-2"></i> البحث بطريقة جوجل (المتشابهات)</strong>
                        <p>البحث بالهجاء التقريبي، يعمل مع الكلمات العربية والإنجليزية. مناسب عندما لا تعرف الهجاء الصحيح.</p>
                        <p>مثال: <code>زنتاك</code> للبحث عن Zantac | <code>pholomoks</code> للبحث عن Flumox</p>
                    </div>
                    
                    <div class="search-tip-item">
                        <strong><i class="fas fa-tag text-info me-2"></i> البحث بالسعر والاسم</strong>
                        <p>يمكنك البحث بالاسم والسعر معًا بصيغة: "اسم الدواء | السعر"</p>
                        <p>مثال: <code>panadol | 20-50</code> للبحث عن باندول بسعر بين 20 و 50 جنيه</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- شريط البحث الجانبي -->
        <div class="col-lg-3 mb-4">
            <div class="search-sidebar shadow-sm">
                <form action="search_ui.php" method="GET">
                    <!-- البحث العام -->
                    <div class="filter-section">
                        <h5 class="filter-title">البحث</h5>
                        <div class="mb-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                placeholder="اسم الدواء أو المادة الفعالة..." 
                                name="q" 
                                value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                            >
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">طريقة البحث</label>
                            <select class="form-select" name="method">
                                <option value="trade" <?php echo $searchMethod == 'trade' ? 'selected' : ''; ?>>
                                    البحث من بداية الاسم
                                </option>
                                <option value="trade_any" <?php echo $searchMethod == 'trade_any' ? 'selected' : ''; ?>>
                                    البحث من أي موضع بالاسم
                                </option>
                                <option value="google" <?php echo $searchMethod == 'google' ? 'selected' : ''; ?>>
                                    البحث بطريقة جوجل (المتشابهات)
                                </option>
                                <option value="price" <?php echo $searchMethod == 'price' ? 'selected' : ''; ?>>
                                    البحث بالسعر والاسم
                                </option>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i> اختر طريقة البحث المناسبة لاحتياجاتك
                            </div>
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
                    
                    <!-- تصفية حسب المادة الفعالة -->
                    <div class="filter-section">
                        <h5 class="filter-title">المادة الفعالة</h5>
                        <div class="mb-3">
                            <select class="form-select" name="scientific_name">
                                <option value="">جميع المواد الفعالة</option>
                                <?php foreach ($scientificNames as $scientificName): ?>
                                    <option 
                                        value="<?php echo $scientificName['scientific_name']; ?>"
                                        <?php echo (isset($_GET['scientific_name']) && $_GET['scientific_name'] == $scientificName['scientific_name']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo $scientificName['scientific_name']; ?>
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

                    <!-- التركيز/الشكل الصيدلاني -->
                    <div class="filter-section">
                        <h5 class="filter-title">التركيز/الشكل الصيدلاني</h5>
                        <div class="mb-3">
                            <input 
                                type="text" 
                                class="form-control" 
                                placeholder="مثال: 500mg، أقراص، شراب..." 
                                name="formulation" 
                                value="<?php echo isset($_GET['formulation']) ? htmlspecialchars($_GET['formulation']) : ''; ?>"
                            >
                        </div>
                    </div>
                    
                    <!-- تصفية حسب عدد الوحدات -->
                    <div class="filter-section">
                        <h5 class="filter-title">عدد الوحدات</h5>
                        <div class="mb-3">
                            <input 
                                type="number" 
                                class="form-control" 
                                placeholder="مثال: 20 قرص، 60 مل..." 
                                name="units" 
                                min="0" 
                                value="<?php echo isset($_GET['units']) ? htmlspecialchars($_GET['units']) : ''; ?>"
                            >
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
                            </select>
                        </div>
                    </div>
                    
                    <!-- أزرار البحث -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> بحث
                        </button>
                        <a href="search_ui.php?show_all=1" class="btn btn-outline-secondary">
                            عرض كل الأدوية
                        </a>
                        <button type="reset" class="btn btn-outline-danger">
                            <i class="fas fa-eraser me-2"></i> مسح الفلاتر
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- نتائج البحث -->
        <div class="col-lg-9">
            <?php if (isset($_GET['q']) || isset($_GET['category']) || isset($_GET['company']) || isset($_GET['scientific_name']) || isset($_GET['show_all'])): ?>
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
                
                <!-- عرض معلومات طريقة البحث -->
                <div class="search-method-info mb-3">
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <?php
                                $methodIcons = [
                                    'trade' => '<i class="fas fa-font fa-2x"></i>',
                                    'trade_any' => '<i class="fas fa-search fa-2x"></i>',
                                    'google' => '<i class="fas fa-microphone-alt fa-2x"></i>',
                                    'price' => '<i class="fas fa-tag fa-2x"></i>'
                                ];
                                
                                $methodNames = [
                                    'trade' => 'البحث من بداية الاسم',
                                    'trade_any' => 'البحث من أي موضع بالاسم',
                                    'google' => 'البحث بطريقة جوجل (المتشابهات)',
                                    'price' => 'البحث بالسعر والاسم'
                                ];
                                
                                echo $methodIcons[$searchMethod];
                                ?>
                            </div>
                            <div>
                                <h6 class="mb-1">طريقة البحث: <?php echo $methodNames[$searchMethod]; ?></h6>
                                <p class="mb-0">استعلام البحث: 
                                    <strong class="text-primary"><?php echo htmlspecialchars($searchQuery); ?></strong>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الفلاتر النشطة -->
                <?php
                $activeFilters = [];
                if (!empty($_GET['category'])) $activeFilters['category'] = 'التصنيف: ' . $_GET['category'];
                if (!empty($_GET['company'])) $activeFilters['company'] = 'الشركة: ' . $_GET['company'];
                if (!empty($_GET['scientific_name'])) $activeFilters['scientific_name'] = 'المادة الفعالة: ' . $_GET['scientific_name'];
                if (isset($_GET['price_min'])) $activeFilters['price_min'] = 'السعر من: ' . $_GET['price_min'] . ' ج.م';
                if (isset($_GET['price_max'])) $activeFilters['price_max'] = 'السعر إلى: ' . $_GET['price_max'] . ' ج.م';
                if (!empty($_GET['formulation'])) $activeFilters['formulation'] = 'التركيز/الشكل: ' . $_GET['formulation'];
                if (!empty($_GET['units'])) $activeFilters['units'] = 'عدد الوحدات: ' . $_GET['units'];
                
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
                            <a href="search_ui.php<?php echo !empty($searchQuery) ? '?q=' . urlencode($searchQuery) . '&method=' . $searchMethod : ''; ?>" class="btn btn-sm btn-outline-danger">
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
                                            <?php if (!empty($med['form']) || !empty($med['strength'])): ?>
                                                <br>
                                                <span class="badge bg-light text-dark">
                                                    <?php 
                                                    echo (!empty($med['strength']) ? $med['strength'] : '');
                                                    echo (!empty($med['form']) ? ' ' . $med['form'] : '');
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                            <div>
                                                <span class="price-tag"><?php echo number_format($med['current_price'], 2); ?> ج.م</span>
                                                <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): 
                                                    $discountPercent = round(100 - ($med['current_price'] / $med['old_price'] * 100));
                                                ?>
                                                    <span class="old-price d-block"><?php echo number_format($med['old_price'], 2); ?> ج.م</span>
                                                    <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
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
                                        <th>السعر</th>
                                        <th>التركيز/الشكل</th>
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
                                            <td>
                                                <strong class="text-primary"><?php echo number_format($med['current_price'], 2); ?> ج.م</strong>
                                                <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): 
                                                    $discountPercent = round(100 - ($med['current_price'] / $med['old_price'] * 100));
                                                ?>
                                                    <br>
                                                    <small class="text-muted text-decoration-line-through">
                                                        <?php echo number_format($med['old_price'], 2); ?> ج.م
                                                    </small>
                                                    <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                echo (!empty($med['strength']) ? $med['strength'] : '');
                                                echo (!empty($med['form']) ? ' ' . $med['form'] : '');
                                                ?>
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
                    
                    <div class="card mt-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> نصائح للبحث</h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>تأكد من هجاء اسم الدواء بشكل صحيح أو جرب استخدام طريقة "البحث بطريقة جوجل"</li>
                                <li>استخدم علامة المسافة كبديل عن الأحرف غير المعروفة مثل <code>cef x</code> للبحث عن Cefax</li>
                                <li>قم بتجربة طريقة "البحث من أي موضع بالاسم" إذا كنت تعرف جزءاً فقط من اسم الدواء</li>
                                <li>جرب التصفية حسب المادة الفعالة أو الشركة المنتجة بدلاً من اسم الدواء</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center p-5 bg-light rounded">
                    <i class="fas fa-search fa-4x text-primary mb-3"></i>
                    <h4>ابحث عن الأدوية</h4>
                    <p class="text-muted">استخدم خيارات البحث المتطورة للعثور على الأدوية التي تحتاجها بسهولة</p>
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
    
    // تبديل طريقة البحث حسب الاختيار
    const methodSelect = document.querySelector('select[name="method"]');
    const searchInput = document.querySelector('input[name="q"]');
    
    if (methodSelect && searchInput) {
        methodSelect.addEventListener('change', function() {
            // تغيير placeholder حسب طريقة البحث
            switch (this.value) {
                case 'trade':
                    searchInput.placeholder = 'اكتب حرفين من بداية اسم الدواء...';
                    break;
                case 'trade_any':
                    searchInput.placeholder = 'اكتب أي جزء من اسم الدواء...';
                    break;
                case 'google':
                    searchInput.placeholder = 'ابحث بأي هجاء تقريبي، يدعم العربية والإنجليزية...';
                    break;
                case 'price':
                    searchInput.placeholder = 'اسم الدواء | السعر (مثال: panadol | 20-50)';
                    break;
                default:
                    searchInput.placeholder = 'اسم الدواء أو المادة الفعالة...';
            }
        });
    }
});
</script>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>