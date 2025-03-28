<?php
// categories.php - صفحة تصنيفات الأدوية
require_once 'config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'تصنيفات الأدوية';
$currentPage = 'categories';

$db = Database::getInstance();

// جلب التصنيفات
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");

// جلب أدوية تصنيف معين إذا تم تحديده
$selectedCategory = null;
$medications = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $categoryId = (int) $_GET['id'];
    $selectedCategory = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
    
    if ($selectedCategory) {
        $medications = $db->fetchAll("SELECT * FROM medications WHERE category LIKE ? ORDER BY trade_name LIMIT 24", ['%' . $selectedCategory['name'] . '%']);
    }
}

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4"><?php echo $selectedCategory ? $selectedCategory['arabic_name'] : 'تصنيفات الأدوية'; ?></h1>
    
    <?php if ($selectedCategory): ?>
        <!-- شريط التنقل -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="categories.php">التصنيفات</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $selectedCategory['arabic_name']; ?></li>
            </ol>
        </nav>
        
        <?php if (!empty($selectedCategory['description'])): ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i> 
                <?php echo $selectedCategory['description']; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($medications)): ?>
            <h4 class="mb-3">الأدوية في هذا التصنيف</h4>
            <div class="grid-cards">
                <?php foreach ($medications as $med): ?>
                    <div class="card med-card">
                        <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                            <div class="ribbon-container">
                                <div class="ribbon bg-danger">
                                    <?php 
                                    $discountPercent = round(100 - ($med['current_price'] / $med['old_price'] * 100));
                                    echo "خصم " . $discountPercent . "%"; 
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <a href="medication.php?id=<?php echo $med['id']; ?>">
                            <?php if (!empty($med['image_url'])): ?>
                                <img src="<?php echo $med['image_url']; ?>" class="card-img-top" alt="<?php echo $med['trade_name']; ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-prescription-bottle-alt fa-3x text-primary"></i>
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
        
            <?php if (count($medications) >= 24): ?>
                <div class="text-center mt-4">
                    <a href="search.php?category=<?php echo urlencode($selectedCategory['name']); ?>" class="btn btn-primary">
                        عرض المزيد من الأدوية <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> 
                لا توجد أدوية متاحة حالياً في هذا التصنيف.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- عرض كل التصنيفات -->
        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-4 col-lg-3 mb-4">
                    <div class="card category-card h-100">
                        <div class="card-body text-center">
                            <div class="category-icon mb-3">
                                <?php
                                // تعيين أيقونة مناسبة لكل تصنيف
                                $iconClass = 'fas fa-pills';
                                switch ($category['name']) {
                                    case 'antibiotic':
                                        $iconClass = 'fas fa-bacterium';
                                        break;
                                    case 'analgesic':
                                        $iconClass = 'fas fa-head-side-virus';
                                        break;
                                    case 'cardiovascular':
                                        $iconClass = 'fas fa-heartbeat';
                                        break;
                                    case 'antidiabetic':
                                        $iconClass = 'fas fa-syringe';
                                        break;
                                    case 'respiratory':
                                        $iconClass = 'fas fa-lungs';
                                        break;
                                    case 'gastrointestinal':
                                        $iconClass = 'fas fa-stomach';
                                        break;
                                    case 'psychiatric':
                                        $iconClass = 'fas fa-brain';
                                        break;
                                    case 'otc':
                                        $iconClass = 'fas fa-shopping-basket';
                                        break;
                                }
                                ?>
                                <i class="<?php echo $iconClass; ?>"></i>
                            </div>
                            <h4><?php echo $category['arabic_name']; ?></h4>
                            <p class="text-muted small">
                                <?php
                                // عرض وصف مختصر
                                echo !empty($category['description']) 
                                    ? (strlen($category['description']) > 100 
                                        ? substr($category['description'], 0, 100) . '...' 
                                        : $category['description'])
                                    : 'لا يوجد وصف متاح';
                                ?>
                            </p>
                            <a href="categories.php?id=<?php echo $category['id']; ?>" class="btn btn-outline-primary mt-2">
                                تصفح الأدوية <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>