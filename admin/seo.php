<?php
// admin/seo.php - صفحة إدارة تحسين محركات البحث (SEO)

// تضمين ملف قاعدة البيانات
require_once '../config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'تحسين محركات البحث';
$db = Database::getInstance();

// معالجة النموذج إذا تم تقديمه
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_meta'])) {
        // تحديث معلومات الميتا الافتراضية
        $siteTitle = $_POST['site_title'] ?? '';
        $siteDescription = $_POST['site_description'] ?? '';
        $siteKeywords = $_POST['site_keywords'] ?? '';
        
        // تحديث الإعدادات في قاعدة البيانات
        try {
            $db->update('settings', [
                'value' => $siteTitle
            ], 'name = ?', ['site_title']);
            
            $db->update('settings', [
                'value' => $siteDescription
            ], 'name = ?', ['site_description']);
            
            $db->update('settings', [
                'value' => $siteKeywords
            ], 'name = ?', ['site_keywords']);
            
            $message = 'تم تحديث معلومات الميتا بنجاح';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'حدث خطأ أثناء تحديث معلومات الميتا: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif (isset($_POST['generate_sitemap'])) {
        // توليد ملف Sitemap
        $sitemapResult = generateSitemap();
        
        if ($sitemapResult) {
            $message = 'تم توليد ملف Sitemap بنجاح';
            $messageType = 'success';
        } else {
            $message = 'حدث خطأ أثناء توليد ملف Sitemap';
            $messageType = 'danger';
        }
    }
}

// الحصول على إعدادات SEO الحالية
$siteTitle = $db->fetchOne("SELECT value FROM settings WHERE name = 'site_title'")['value'] ?? 'دواؤك - دليلك الشامل للأدوية';
$siteDescription = $db->fetchOne("SELECT value FROM settings WHERE name = 'site_description'")['value'] ?? 'منصة دواؤك توفر معلومات شاملة عن الأدوية، قارن الأسعار، ابحث عن البدائل، واطلع على المعلومات التفصيلية لأكثر من 25,000 دواء.';
$siteKeywords = $db->fetchOne("SELECT value FROM settings WHERE name = 'site_keywords'")['value'] ?? 'أدوية مصر، أسعار الأدوية، بدائل الأدوية، دليل الأدوية، معلومات دوائية، مقارنة أدوية';

// جمع إحصائيات SEO
$totalMedications = $db->fetchOne("SELECT COUNT(*) as count FROM medications")['count'] ?? 0;
$medicationsWithDescriptions = $db->fetchOne("SELECT COUNT(*) as count FROM medications WHERE description IS NOT NULL AND description != ''")['count'] ?? 0;
$medicationsWithImages = $db->fetchOne("SELECT COUNT(*) as count FROM medications WHERE image_url IS NOT NULL AND image_url != ''")['count'] ?? 0;

// حساب نسب اكتمال الوصف والصور
$descriptionCompletionRate = $totalMedications > 0 ? round(($medicationsWithDescriptions / $totalMedications) * 100) : 0;
$imagesCompletionRate = $totalMedications > 0 ? round(($medicationsWithImages / $totalMedications) * 100) : 0;

// التحقق من وجود ملفات SEO الأساسية
$sitemapExists = file_exists('../sitemap.xml');
$robotsExists = file_exists('../robots.txt');

// الحصول على معلومات الملفات
$sitemapLastUpdated = $sitemapExists ? date("Y-m-d H:i", filemtime('../sitemap.xml')) : 'غير متوفر';
$robotsLastUpdated = $robotsExists ? date("Y-m-d H:i", filemtime('../robots.txt')) : 'غير متوفر';

// دالة توليد ملف Sitemap
function generateSitemap() {
    // استدعاء ملف generate_sitemap.php باستخدام طلب HTTP داخلي
    $url = '../admin/generate_sitemap.php';
    
    // استخدام CURL إذا كان متوفرًا
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            return isset($result['success']) && $result['success'];
        }
    } else {
        // استخدام file_get_contents كبديل
        $response = @file_get_contents($url);
        if ($response !== false) {
            $result = json_decode($response, true);
            return isset($result['success']) && $result['success'];
        }
    }
    
    return false;
}

// الحصول على أكثر كلمات البحث شيوعًا
$topSearchTerms = $db->fetchAll("
    SELECT search_term, search_count
    FROM search_stats
    ORDER BY search_count DESC
    LIMIT 20
");

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $pageTitle; ?> - إدارة منصة دواؤك</title>
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Cairo Font -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8f9fc;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #4e73df;
            background-image: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            background-size: cover;
        }
        .sidebar-brand {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.8rem 1rem;
        }
        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .nav-link.active {
            color: white;
            font-weight: 600;
        }
        .admin-content {
            min-height: 100vh;
            padding: 1.5rem;
        }
        .progress-container {
            margin: 15px 0;
        }
        .progress {
            height: 12px;
            border-radius: 10px;
        }
        .url-list {
            max-height: 200px;
            overflow-y: auto;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .tags-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 15px 0;
        }
        .tag-item {
            background-color: #e9ecef;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        .tag-count {
            background-color: #4e73df;
            color: white;
            border-radius: 50%;
            margin-right: 5px;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
        }
        .seo-card {
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <div class="sidebar-brand">
                    <h3 class="m-0"><i class="fas fa-pills me-2"></i> دواؤك</h3>
                </div>
                <hr class="sidebar-divider my-0 bg-light opacity-25">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medications.php">
                            <i class="fas fa-pills me-2"></i>
                            إدارة الأدوية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-th-list me-2"></i>
                            التصنيفات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="stats.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            الإحصائيات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="seo.php">
                            <i class="fas fa-search me-2"></i>
                            تحسين محركات البحث
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>
                            الإعدادات
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            تسجيل الخروج
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 admin-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">تحسين محركات البحث (SEO)</h1>
                    <div>
                        <button class="btn btn-primary" onclick="generateSitemap()">
                            <i class="fas fa-sitemap me-1"></i> توليد Sitemap
                        </button>
                        <a href="../" target="_blank" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-external-link-alt me-1"></i> عرض الموقع
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="row mb-4">
                    <!-- بطاقة حالة ملفات SEO -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i> حالة ملفات SEO</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="mb-3">ملفات أساسية</h6>
                                        <ul class="list-group">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-sitemap me-2"></i> Sitemap.xml</span>
                                                <?php if ($sitemapExists): ?>
                                                    <span class="badge bg-success rounded-pill">متوفر</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill">غير متوفر</span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-robot me-2"></i> Robots.txt</span>
                                                <?php if ($robotsExists): ?>
                                                    <span class="badge bg-success rounded-pill">متوفر</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger rounded-pill">غير متوفر</span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-3">معلومات إضافية</h6>
                                        <ul class="list-group">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-clock me-2"></i> آخر تحديث Sitemap</span>
                                                <span class="badge bg-info rounded-pill"><?php echo $sitemapLastUpdated; ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-clock me-2"></i> آخر تحديث Robots</span>
                                                <span class="badge bg-info rounded-pill"><?php echo $robotsLastUpdated; ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <button class="btn btn-primary btn-sm" onclick="generateSitemap()">
                                            <i class="fas fa-sync me-1"></i> تحديث ملفات SEO
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- بطاقة معلومات الميتا -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-cog me-2"></i> معلومات الميتا الافتراضية</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="site_description" class="form-label">وصف الموقع</label>
                                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($siteDescription); ?></textarea>
                                        <div class="form-text text-muted">وصف الموقع الذي سيظهر في نتائج البحث (150-160 حرف)</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="site_keywords" class="form-label">الكلمات المفتاحية</label>
                                        <textarea class="form-control" id="site_keywords" name="site_keywords" rows="2"><?php echo htmlspecialchars($siteKeywords); ?></textarea>
                                        <div class="form-text text-muted">الكلمات المفتاحية مفصولة بفواصل (غير مهمة كثيرًا لكن يفضل إضافتها)</div>
                                    </div>
                                    <button type="submit" name="update_meta" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> حفظ المعلومات
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <!-- بطاقة إحصائيات المحتوى -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> إحصائيات المحتوى</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="mb-2">اكتمال الأوصاف</h6>
                                <div class="progress-container">
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $descriptionCompletionRate; ?>%;" aria-valuenow="<?php echo $descriptionCompletionRate; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $descriptionCompletionRate; ?>%</div>
                                    </div>
                                    <div class="text-muted mt-1">
                                        <small><?php echo $medicationsWithDescriptions; ?> من <?php echo $totalMedications; ?> دواء يحتوي على وصف</small>
                                    </div>
                                </div>
                                
                                <h6 class="mb-2 mt-4">اكتمال الصور</h6>
                                <div class="progress-container">
                                    <div class="progress">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $imagesCompletionRate; ?>%;" aria-valuenow="<?php echo $imagesCompletionRate; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $imagesCompletionRate; ?>%</div>
                                    </div>
                                    <div class="text-muted mt-1">
                                        <small><?php echo $medicationsWithImages; ?> من <?php echo $totalMedications; ?> دواء يحتوي على صورة</small>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>ملاحظة:</strong> للحصول على أفضل نتائج في محركات البحث، حاول إكمال أوصاف وصور الأدوية قدر الإمكان.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- بطاقة كلمات البحث الشائعة -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-search me-2"></i> كلمات البحث الشائعة</h5>
                            </div>
                            <div class="card-body">
                                <div class="tags-cloud">
                                    <?php foreach ($topSearchTerms as $term): ?>
                                    <div class="tag-item">
                                        <span class="tag-count"><?php echo $term['search_count']; ?></span>
                                        <?php echo htmlspecialchars($term['search_term']); ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    <strong>نصيحة:</strong> استخدم هذه الكلمات في أوصاف الأدوية ومحتوى الموقع لتحسين الظهور في نتائج البحث.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <!-- بطاقة نصائح تحسين محركات البحث -->
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i> نصائح لتحسين SEO</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card mb-3 border-0">
                                            <div class="card-body">
                                                <h5 class="text-primary"><i class="fas fa-file-alt me-2"></i> المحتوى</h5>
                                                <ul>
                                                    <li>أضف وصفًا تفصيليًا لكل دواء</li>
                                                    <li>استخدم الكلمات المفتاحية بشكل طبيعي</li>
                                                    <li>أضف صورًا للأدوية مع alt text مناسب</li>
                                                    <li>ضف معلومات تفصيلية عن استخدامات الدواء والآثار الجانبية</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card mb-3 border-0">
                                            <div class="card-body">
                                                <h5 class="text-success"><i class="fas fa-cogs me-2"></i> الجوانب التقنية</h5>
                                                <ul>
                                                    <li>حدّث ملف Sitemap باستمرار</li>
                                                    <li>تأكد من سرعة تحميل الصفحات</li>
                                                    <li>اختصر URLs وجعلها قابلة للقراءة</li>
                                                    <li>استخدم العلامات الهيكلية (Schema.org)</li>
                                                    <li>تأكد من توافق الموقع مع الأجهزة المحمولة</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card mb-3 border-0">
                                            <div class="card-body">
                                                <h5 class="text-info"><i class="fas fa-link me-2"></i> الروابط</h5>
                                                <ul>
                                                    <li>أضف روابط داخلية بين صفحات الموقع</li>
                                                    <li>استخدم نصوص روابط وصفية</li>
                                                    <li>ضع روابط للأدوية المشابهة والبدائل</li>
                                                    <li>اعمل على زيادة الروابط الخارجية من مواقع موثوقة</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <!-- محتوى Robots.txt -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-robot me-2"></i> محتوى Robots.txt</h5>
                            </div>
                            <div class="card-body">
                                <div class="url-list">
                                    <?php if ($robotsExists): ?>
                                        <pre><?php echo htmlspecialchars(file_get_contents('../robots.txt')); ?></pre>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            الملف غير موجود. اضغط على زر "تحديث ملفات SEO" لإنشاء الملف.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- عينة من Sitemap.xml -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i> عينة من Sitemap.xml</h5>
                            </div>
                            <div class="card-body">
                                <div class="url-list">
                                    <?php if ($sitemapExists): ?>
                                        <?php
                                        $sitemap_content = file_get_contents('../sitemap.xml');
                                        // عرض جزء فقط من الملف
                                        $sample = substr($sitemap_content, 0, 1000);
                                        if (strlen($sitemap_content) > 1000) {
                                            $sample .= "\n...\n[الملف كبير جدًا لعرضه بالكامل]";
                                        }
                                        echo '<pre>' . htmlspecialchars($sample) . '</pre>';
                                        ?>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            الملف غير موجود. اضغط على زر "تحديث ملفات SEO" لإنشاء الملف.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- أساسيات JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // دالة لتوليد ملف Sitemap
        function generateSitemap() {
            // عرض رسالة تحميل
            const statusMessage = document.createElement('div');
            statusMessage.className = 'alert alert-info position-fixed top-0 start-50 translate-middle-x mt-3';
            statusMessage.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> جاري توليد الملفات...';
            document.body.appendChild(statusMessage);
            
            // إرسال طلب AJAX
            fetch('generate_sitemap.php')
                .then(response => response.json())
                .then(data => {
                    // تحديث رسالة الحالة
                    statusMessage.className = data.success 
                        ? 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3'
                        : 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3';
                    
                    statusMessage.innerHTML = data.success
                        ? '<i class="fas fa-check-circle me-2"></i> ' + data.message
                        : '<i class="fas fa-exclamation-circle me-2"></i> ' + data.message;
                    
                    // إعادة تحميل الصفحة بعد ثانيتين
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusMessage.className = 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3';
                    statusMessage.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i> حدث خطأ أثناء العملية';
                    
                    // إخفاء الرسالة بعد 3 ثوان
                    setTimeout(() => {
                        statusMessage.remove();
                    }, 3000);
                });
        }
    </script>
</body>