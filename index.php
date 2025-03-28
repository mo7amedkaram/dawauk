<?php
// index.php - تحديث الصفحة الرئيسية لتضمين البحث المحسن
require_once 'config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'الرئيسية';
$currentPage = 'home';

// الحصول على الأدوية الشائعة
$db = Database::getInstance();
$popularMeds = $db->fetchAll("SELECT * FROM medications ORDER BY id DESC LIMIT 8");

// الحصول على التصنيفات
$categories = $db->fetchAll("SELECT * FROM categories WHERE parent_id IS NULL LIMIT 8");

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">دليلك الشامل للأدوية</h1>
            <p class="hero-subtitle">ابحث عن الأدوية، قارن الأسعار، واطلع على المعلومات التفصيلية لأكثر من 10,000 دواء</p>
            <div class="search-big">
                <form action="search.php" method="GET">
                    <div class="input-group position-relative">
                        <input 
                            type="search" 
                            class="form-control form-control-lg search-autocomplete" 
                            placeholder="ابحث عن اسم دواء، مادة فعالة، باركود أو استخدم * بدل الحروف غير المعروفة..." 
                            name="q"
                            autocomplete="off"
                        >
                        <button class="btn btn-light" type="submit">
                            <i class="fas fa-search me-2"></i> بحث
                        </button>
                        <!-- هنا سيتم إضافة نتائج البحث التلقائي بالجافاسكريبت -->
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- الميزات الرئيسية -->
<section class="container mb-5">
    <h2 class="section-title text-center mb-4">ما يميز منصة دواؤك</h2>
    <div class="features-flex">
        <div class="feature-item shadow-hover">
            <div class="feature-icon">
                <i class="fas fa-search"></i>
            </div>
            <h3>بحث متقدم</h3>
            <p>ابحث عن الأدوية باستخدام الاسم التجاري، المادة الفعالة، الشركة المصنعة أو التصنيف</p>
        </div>
        <div class="feature-item shadow-hover">
            <div class="feature-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <h3>مقارنة الأدوية</h3>
            <p>قارن بين الأدوية البديلة من حيث السعر، المادة الفعالة والعديد من المميزات الأخرى</p>
        </div>
        <div class="feature-item shadow-hover">
            <div class="feature-icon">
                <i class="fas fa-book-medical"></i>
            </div>
            <h3>معلومات تفصيلية</h3>
            <p>احصل على معلومات شاملة حول الاستخدامات، الجرعات، الآثار الجانبية والتحذيرات</p>
        </div>
    </div>
</section>

<!-- أحدث الأدوية -->
<section class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="section-title mb-0">أحدث الأدوية</h2>
        <a href="search.php" class="btn btn-outline-primary">
            عرض الكل <i class="fas fa-arrow-left ms-2"></i>
        </a>
    </div>
    
    <div class="grid-cards">
        <?php foreach ($popularMeds as $med): ?>
            <div class="card med-card shadow-hover">
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
                    <div class="med-image-container">
                        <?php if (!empty($med['image_url'])): ?>
                            <img src="<?php echo $med['image_url']; ?>" class="med-img" alt="<?php echo $med['trade_name']; ?>">
                        <?php else: ?>
                            <i class="fas fa-prescription-bottle-alt med-icon"></i>
                        <?php endif; ?>
                    </div>
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
                        <div class="price-container">
                            <span class="price-tag"><?php echo number_format($med['current_price'], 2); ?> ج.م</span>
                            <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                                <br>
                                <span class="old-price"><?php echo number_format($med['old_price'], 2); ?> ج.م</span>
                                <span class="discount-badge">
                                    -<?php echo $discountPercent; ?>%
                                </span>
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
</section>

<!-- تصنيفات الأدوية -->
<section class="container mb-5">
    <h2 class="section-title text-center mb-4">تصنيفات الأدوية</h2>
    <div class="grid-cards">
        <?php foreach ($categories as $category): ?>
            <div class="card category-card shadow-hover">
                <div class="card-body">
                    <div class="category-icon">
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
                    <h4 class="card-title"><?php echo $category['arabic_name']; ?></h4>
                    <p class="card-text small text-muted"><?php echo $category['description']; ?></p>
                    <a href="categories.php?id=<?php echo $category['id']; ?>" class="btn btn-primary mt-3">
                        تصفح الأدوية
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- الدليل الدوائي -->
<section class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="section-title mb-0">الدليل الدوائي</h2>
        <a href="guide.php" class="btn btn-outline-primary">
            تصفح الدليل <i class="fas fa-arrow-left ms-2"></i>
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100 guide-card">
                <div class="card-body text-center">
                    <div class="guide-icon">
                        <i class="fas fa-book-medical"></i>
                    </div>
                    <h4>البحث الأبجدي</h4>
                    <p>تصفح الأدوية حسب الحرف الأول من اسمها، باللغتين العربية والإنجليزية</p>
                    <a href="guide.php" class="btn btn-primary mt-3">
                        البحث الأبجدي
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card h-100 guide-card">
                <div class="card-body text-center">
                    <div class="guide-icon">
                        <i class="fas fa-th-list"></i>
                    </div>
                    <h4>التصنيفات العلاجية</h4>
                    <p>تصفح الأدوية حسب التصنيف العلاجي مثل مضادات حيوية، مسكنات، أدوية القلب والأوعية الدموية</p>
                    <a href="categories.php" class="btn btn-primary mt-3">
                        تصفح التصنيفات
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- خدمات إضافية -->
<section class="container mb-5">
    <h2 class="section-title text-center mb-4">خدمات مميزة</h2>
    
    <div class="dashboard-grid">
        <div class="grid-span-6">
            <div class="card h-100 shadow-hover">
                <div class="card-body">
                    <h4><i class="fas fa-balance-scale text-primary me-2"></i> مقارنة الأدوية</h4>
                    <p>قارن بين الأدوية المختلفة من حيث السعر، المادة الفعالة، الآثار الجانبية، والاستخدامات المختلفة للوصول لأفضل خيار يناسب احتياجاتك.</p>
                    <a href="compare.php" class="btn btn-outline-primary">المقارنة الآن</a>
                </div>
            </div>
        </div>
        
        <div class="grid-span-6">
            <div class="card h-100 shadow-hover">
                <div class="card-body">
                    <h4><i class="fas fa-search-plus text-primary me-2"></i> البحث المتقدم</h4>
                    <p>ابحث عن الأدوية باستخدام معايير متعددة مثل الاسم، المادة الفعالة، الشركة المصنعة، التصنيف، ونطاق السعر.</p>
                    <a href="search.php" class="btn btn-outline-primary">بحث متقدم</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- نصائح صحية -->
<section class="container mb-5 bg-light p-4 rounded shadow-sm">
    <h2 class="section-title text-center mb-4">نصائح صحية</h2>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-pills text-primary me-2"></i> استخدام الأدوية بأمان</h5>
                    <p class="card-text">تأكد دائمًا من اتباع تعليمات الطبيب أو الصيدلي بدقة. لا تتوقف عن استخدام الدواء قبل انتهاء المدة المقررة حتى لو شعرت بالتحسن.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-clipboard-list text-primary me-2"></i> تخزين الأدوية</h5>
                    <p class="card-text">احفظ الأدوية في مكان بارد وجاف بعيدًا عن أشعة الشمس المباشرة. تأكد من إبقاء الأدوية بعيدًا عن متناول الأطفال وتحقق دوريًا من تواريخ انتهاء الصلاحية.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-allergies text-primary me-2"></i> الحساسية الدوائية</h5>
                    <p class="card-text">أخبر طبيبك دائمًا عن أي حساسية دوائية سابقة قبل وصف أي علاج جديد. إذا لاحظت أي أعراض حساسية بعد تناول دواء جديد، توقف عن استخدامه واستشر الطبيب فورًا.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>