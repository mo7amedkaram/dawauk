<?php
// index.php - تحديث الصفحة الرئيسية لدعم طرق البحث المتعددة
require_once 'config/database.php';

require_once 'seo.php';

$db = Database::getInstance();
$seo = new SEO($db);

// إعداد SEO حسب نوع الصفحة
if ($page_type == 'home') {
    $seo->setupHomePage();
} elseif ($page_type == 'medication') {
    $seo->setupMedicationPage($medication);
}

// طباعة وسوم SEO في رأس الصفحة
echo $seo->generate();

// إعداد معلومات الصفحة
$pageTitle = 'الرئيسية | دواؤك';
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
            <p class="hero-subtitle">ابحث عن الأدوية، قارن الأسعار، واطلع على المعلومات التفصيلية لأكثر من 25,000 دواء</p>
            <div class="search-big">
                <form action="search.php" method="GET">
                    <div class="input-group position-relative">
                        <input 
                            type="search" 
                            class="form-control form-control-lg search-autocomplete" 
                            placeholder="ابحث بالاسم التجاري أو المادة الفعالة أو الباركود..." 
                            name="q"
                            autocomplete="off"
                        >
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-2"></i> بحث
                        </button>
                        <!-- هنا سيتم إضافة نتائج البحث التلقائي بالجافاسكريبت -->
                    </div>
                    <div class="mt-2 text-white text-center">
                        <small><i class="fas fa-lightbulb me-1"></i> تلميح: استخدم طرق البحث المختلفة بالنقر على حقل البحث</small>
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
            <h3>بحث متعدد الطرق</h3>
            <p>بحث ذكي يدعم أكثر من طريقة للبحث عن الدواء الذي تحتاجه بسهولة ودقة</p>
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
<!-- تحميل التطبيق والانضمام للمجموعة -->
<section class="container mb-5">
    <div class="row">
        <!-- تحميل التطبيق -->
        
        
        <!-- الانضمام لمجموعة الفيسبوك -->
        <div class="col-md-6-center mb-4">
            <div class="card facebook-card shadow">
                <div class="card-body text-center py-4">
                    <div class="facebook-icon mb-3">
                        <i class="fab fa-facebook fa-4x text-primary"></i>
                    </div>
                    <h3 class="card-title mb-3">انضم لمجموعتنا على فيسبوك</h3>
                    <p class="card-text mb-4">كن جزءًا من مجتمع دواؤك النشط. اطرح أسئلتك، واستفد من خبرات عدد كبير من الصيادلة.</p>
                    <a href="https://www.facebook.com/share/g/1HL2Vt5VSk/" class="btn btn-lg btn-primary facebook-btn" target="_blank">
                        <i class="fab fa-facebook-f me-2"></i> الانضمام للمجموعة
                    </a>
                    <div class="mt-3">
                        
                        </span>
                    </div>
                </div>
            </div>
        </div>
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
                    <p>استخدم خيارات البحث المتعددة للوصول للدواء المناسب بسرعة، سواء كنت تعرف الاسم بالكامل أو جزء منه أو كنت تبحث عن أدوية مشابهة في النطق.</p>
                    <a href="search.php" class="btn btn-outline-primary">بحث متقدم</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- قسم طرق البحث -->
<section class="container mb-5 bg-light p-4 rounded shadow-sm">
    <h2 class="section-title text-center mb-4">طرق البحث المتاحة</h2>
    
    <div class="row align-items-center">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="search-methods-features">
                <div class="search-feature-item mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="search-feature-icon bg-primary text-white me-3">
                            <i class="fas fa-search"></i>
                        </span>
                        <h5 class="mb-0">البحث من بداية الاسم</h5>
                    </div>
                    <p class="text-muted">أسرع طرق البحث حيث يكفي كتابة أول حرفين فقط من اسم الدواء، مع إمكانية استخدام المسافات بدل الأحرف غير المعروفة.</p>
                </div>
                
                <div class="search-feature-item mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <span class="search-feature-icon bg-success text-white me-3">
                            <i class="fas fa-font"></i>
                        </span>
                        <h5 class="mb-0">البحث في أي موضع</h5>
                    </div>
                    <p class="text-muted">ابحث عن أي جزء من اسم الدواء، سواء في بدايته أو وسطه أو نهايته، ويمكنك استخدام كلمات متعددة للبحث.</p>
                </div>
                
                <div class="search-feature-item">
                    <div class="d-flex align-items-center mb-2">
                        <span class="search-feature-icon bg-info text-white me-3">
                            <i class="fas fa-language"></i>
                        </span>
                        <h5 class="mb-0">البحث بالنطق المتشابه</h5>
                    </div>
                    <p class="text-muted">لا تعرف هجاء الدواء بالضبط؟ استخدم هذه الطريقة للبحث باستخدام الأحرف المتشابهة في النطق باللغتين العربية والإنجليزية.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="search-examples">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> أمثلة على طرق البحث</h5>
                    </div>
                    <div class="card-body">
                        <ul class="search-examples-list">
                            <li>
                                <strong>بداية الاسم:</strong> 
                                <span class="search-example">au</span> أو
                                <span class="search-example">au tab</span>
                                <small class="text-muted d-block mt-1">يبحث عن الأدوية التي تبدأ بهذه الأحرف</small>
                            </li>
                            <li>
                                <strong>أي موضع:</strong> 
                                <span class="search-example">tab</span> أو
                                <span class="search-example">vial</span>
                                <small class="text-muted d-block mt-1">يبحث عن الأدوية التي تحتوي على هذه الكلمات</small>
                            </li>
                            <li>
                                <strong>النطق المتشابه:</strong> 
                                <span class="search-example">pholomoks</span> أو
                                <span class="search-example">زنتاك</span>
                                <small class="text-muted d-block mt-1">يبحث عن الأدوية ذات النطق المتشابه</small>
                            </li>
                            <li>
                                <strong>بحث شامل:</strong> 
                                <span class="search-example">50</span> أو
                                <span class="search-example">painkiller</span>
                                <small class="text-muted d-block mt-1">يبحث في السعر والاسم والفئة وغيرها</small>
                            </li>
                        </ul>
                        
                        <div class="mt-3 text-center">
                            <a href="search.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i> جرب البحث المتقدم الآن
                            </a>
                        </div>
                    </div>
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

<!-- تنسيقات إضافية للصفحة الرئيسية -->
<style>
/* تنسيقات قسم طرق البحث */
.search-feature-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.search-examples-list {
    padding-left: 0;
    list-style: none;
}

.search-examples-list li {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.search-examples-list li:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.search-example {
    display: inline-block;
    background-color: #f0f8ff;
    color: #0d6efd;
    padding: 2px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-weight: 600;
}
</style>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>