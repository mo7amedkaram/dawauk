<?php
// search.php - صفحة البحث المتقدم
require_once 'config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'بحث متقدم';
$currentPage = 'search';

$db = Database::getInstance();

// جلب جميع التصنيفات
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");

// جلب جميع الشركات المنتجة
$companies = $db->fetchAll("SELECT DISTINCT company FROM medications WHERE company != '' ORDER BY company");

// معالجة البحث
$query = '';
$searchResults = [];
$totalResults = 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$whereClauses = [];
$params = [];
$orderBy = "id DESC";

// البحث العام
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $query = trim($_GET['q']);
    $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ? OR barcode = ?)";
    $searchParam = "%$query%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $query; // للباركود نريد تطابق كامل
}

// البحث بواسطة التصنيف
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $whereClauses[] = "category LIKE ?";
    $params[] = "%" . $_GET['category'] . "%";
}

// البحث بواسطة الشركة المنتجة
if (isset($_GET['company']) && !empty($_GET['company'])) {
    $whereClauses[] = "company = ?";
    $params[] = $_GET['company'];
}

// البحث بواسطة نطاق السعر
if (isset($_GET['price_min']) && is_numeric($_GET['price_min'])) {
    $whereClauses[] = "current_price >= ?";
    $params[] = (float)$_GET['price_min'];
}

if (isset($_GET['price_max']) && is_numeric($_GET['price_max'])) {
    $whereClauses[] = "current_price <= ?";
    $params[] = (float)$_GET['price_max'];
}

// الترتيب
if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    switch ($_GET['sort']) {
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
        case 'date_desc':
            $orderBy = "price_updated_date DESC";
            break;
        default:
            $orderBy = "id DESC";
    }
}

// إنشاء استعلام البحث
$whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
$countSql = "SELECT COUNT(*) as total FROM medications $whereClause";
$searchSql = "SELECT * FROM medications $whereClause ORDER BY $orderBy LIMIT $offset, $limit";

// تنفيذ البحث إذا كانت هناك معايير
if (!empty($whereClauses) || isset($_GET['show_all'])) {
    $totalResults = $db->fetchOne($countSql, $params)['total'];
    $searchResults = $db->fetchAll($searchSql, $params);
}

// حساب صفحات الترقيم
$totalPages = ceil($totalResults / $limit);

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">بحث متقدم عن الأدوية</h1>
    
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
                                <option value="id_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'id_desc') ? 'selected' : ''; ?>>الأحدث</option>
                                <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>السعر: من الأقل للأعلى</option>
                                <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>السعر: من الأعلى للأقل</option>
                                <option value="name_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_asc') ? 'selected' : ''; ?>>الاسم: أ-ي</option>
                                <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'name_desc') ? 'selected' : ''; ?>>الاسم: ي-أ</option>
                                <option value="date_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'date_desc') ? 'selected' : ''; ?>>تاريخ تحديث السعر</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- أزرار البحث -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> بحث
                        </button>
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
                            <div class="btn-group me-2">
                                <button class="btn btn-outline-secondary active">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button class="btn btn-outline-secondary">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($totalResults > 0 && count($searchResults) > 0): ?>
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
                                            <?php echo $med['trade_name']; ?>
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
                                        <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            التفاصيل
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>