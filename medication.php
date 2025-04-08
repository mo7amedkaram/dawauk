<?php
// medication.php - صفحة تفاصيل الدواء مع تحسينات SEO
require_once 'config/database.php';
require_once 'includes/stats_tracking.php';
require_once __DIR__ . '/includes/seo.php';

/**
 * إنشاء slug من النص
 *
 * @param string $text النص المراد تحويله
 * @return string النص بصيغة slug
 */
function createSlug($text) {
    // إزالة الأحرف الخاصة والمسافات واستبدالها بشرطة
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text);
    // تحويل إلى أحرف صغيرة
    $text = mb_strtolower($text, 'UTF-8');
    // إزالة الشرطات المتكررة
    $text = preg_replace('~-+~', '-', $text);
    // إزالة الشرطات من البداية والنهاية
    $text = trim($text, '-');
    
    return $text;
}

/**
 * الحصول على الرابط الأساسي للموقع
 * 
 * @return string الرابط الأساسي
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

/**
 * إنشاء رابط صفحة الدواء
 * 
 * @param int $medicationId معرف الدواء
 * @param string $tradeName اسم الدواء التجاري
 * @return string رابط صفحة الدواء
 */
function getMedicationUrl($medicationId, $tradeName = '') {
    $baseUrl = getBaseUrl();
    if (!empty($tradeName)) {
        $slug = createSlug($tradeName);
        return $baseUrl . '/medicine/' . $slug . '/' . $medicationId;
    }
    return $baseUrl . '/medicine/' . $medicationId;
}

/**
 * إنشاء رابط صفحة التصنيف
 * 
 * @param string $category اسم التصنيف
 * @return string رابط صفحة التصنيف
 */
function getCategoryUrl($category) {
    $baseUrl = getBaseUrl();
    $slug = createSlug($category);
    return $baseUrl . '/category/' . $slug;
}

// التحقق من وجود معرف الدواء
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$medicationId = (int) $_GET['id'];

// الحصول على بيانات الدواء
$db = Database::getInstance();
$statsTracker = new StatsTracker($db);

$medication = $db->fetchOne("SELECT * FROM medications WHERE id = ?", [$medicationId]);
if (!$medication) {
    header('Location: index.php');
    exit;
}

// تسجيل زيارة الدواء
$statsTracker->trackMedicationVisit($medicationId);

// الحصول على تفاصيل الدواء الإضافية
$medicationDetails = $db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$medicationId]);

// جلب الأدوية المثيلة (نفس المادة الفعالة، نفس الشركة)
$equivalentMeds = $db->fetchAll("
    SELECT * FROM medications 
    WHERE scientific_name = ? 
    AND company = ? 
    AND id != ? 
    ORDER BY current_price ASC
", [$medication['scientific_name'], $medication['company'], $medicationId]);

// جلب البدائل (نفس المادة الفعالة، شركات مختلفة)
$alternatives = $db->fetchAll("
    SELECT * FROM medications 
    WHERE scientific_name = ? 
    AND company != ? 
    ORDER BY current_price ASC
", [$medication['scientific_name'], $medication['company']]);

// جلب البدائل العلاجية (مواد فعالة مختلفة ولكن لها نفس الاستخدام)
$therapeuticAlternatives = $db->fetchAll("
    SELECT m.*, ma.similarity_score 
    FROM medications m
    JOIN medication_alternatives ma ON m.id = ma.alternative_id
    WHERE ma.medication_id = ? AND m.scientific_name != ?
    ORDER BY ma.similarity_score DESC, m.current_price ASC
    LIMIT 5
", [$medicationId, $medication['scientific_name']]);

// جلب الأدوية المشابهة الأكثر زيارة
$similarPopularMeds = $db->fetchAll(
    "SELECT * FROM medications 
     WHERE scientific_name = ? AND id != ? 
     ORDER BY visit_count DESC 
     LIMIT 5",
    [$medication['scientific_name'], $medicationId]
);

// إنشاء وصف مناسب للصفحة
$currentYear = date('Y');
$description = "سعر دواء {$medication['trade_name']} في مصر اليوم {$medication['current_price']} جنيه";
if (!empty($medication['scientific_name'])) {
    $description .= " - المادة الفعالة: {$medication['scientific_name']}";
}
if (!empty($medication['company'])) {
    $description .= " - منتج شركة {$medication['company']}";
}
if (!empty($medicationDetails) && !empty($medicationDetails['indications'])) {
    // اقتصاص النص للوصف
    $shortIndications = substr(strip_tags($medicationDetails['indications']), 0, 100);
    $description .= " - {$shortIndications}...";
}

// إنشاء كلمات مفتاحية للصفحة
$keywords = [
    $medication['trade_name'],
    'سعر ' . $medication['trade_name'],
    'دواء ' . $medication['trade_name'],
    'سعر ' . $medication['trade_name'] . ' ' . $currentYear,
    'بديل ' . $medication['trade_name']
];

if (!empty($medication['scientific_name'])) {
    $keywords[] = $medication['scientific_name'];
    $keywords[] = 'أدوية تحتوي على ' . $medication['scientific_name'];
}

if (!empty($medication['company'])) {
    $keywords[] = 'أدوية شركة ' . $medication['company'];
}

if (!empty($medication['category'])) {
    $keywords[] = 'أدوية ' . $medication['category'];
}

// إعداد الأسئلة الشائعة
$faqs = [
    [
        'question' => "ما هو دواء {$medication['trade_name']}؟",
        'answer' => "دواء {$medication['trade_name']} يحتوي على المادة الفعالة {$medication['scientific_name']} وهو من إنتاج شركة {$medication['company']}." . 
                    (!empty($medicationDetails['indications']) ? " يستخدم لـ" . $medicationDetails['indications'] : "")
    ],
    [
        'question' => "ما هو سعر دواء {$medication['trade_name']} في مصر؟",
        'answer' => "سعر دواء {$medication['trade_name']} في مصر هو {$medication['current_price']} جنيه مصري حسب آخر تحديث بتاريخ " . 
                    (!empty($medication['price_updated_date']) ? date('d/m/Y', strtotime($medication['price_updated_date'])) : date('d/m/Y')) . "."
    ],
    [
        'question' => "ما هي بدائل دواء {$medication['trade_name']}؟",
        'answer' => !empty($alternatives) ? "من بدائل دواء {$medication['trade_name']} المتوفرة في مصر: " . 
                    implode("، ", array_map(function($alt) { return $alt['trade_name'] . " (سعره " . $alt['current_price'] . " جنيه)"; }, 
                    array_slice($alternatives, 0, 3))) : "يمكنك البحث عن بدائل تحتوي على نفس المادة الفعالة {$medication['scientific_name']}."
    ],
    [
        'question' => "ما هي دواعي استعمال {$medication['trade_name']}؟",
        'answer' => !empty($medicationDetails['indications']) ? $medicationDetails['indications'] : 
                    "يرجى استشارة الطبيب أو الصيدلي لمعرفة دواعي استعمال دواء {$medication['trade_name']} بالتفصيل."
    ],
    [
        'question' => "ما هي الآثار الجانبية لدواء {$medication['trade_name']}؟",
        'answer' => !empty($medicationDetails['side_effects']) ? $medicationDetails['side_effects'] : 
                    "يرجى قراءة النشرة الداخلية لدواء {$medication['trade_name']} أو استشارة الطبيب أو الصيدلي لمعرفة الآثار الجانبية المحتملة."
    ]
];

// إعداد معلومات الصفحة
$scientificName = $medication['scientific_name'] ?? '';
$pageTitle = "سعر {$medication['trade_name']} {$currentYear} - {$scientificName}";
$currentPage = '';

// التحقق من وجود تكوين الذكاء الاصطناعي - المتغيرات المطلوبة في header.php
$siteName = 'دواؤك - دليلك الشامل للأدوية';
$headerUseAiSearch = false;

// رابط الصفحة الحالي المحسن للSEO
$canonicalUrl = getMedicationUrl($medicationId, $medication['trade_name']);

// إنشاء كائن SEO وتكوينه
$seo = new SEO($db);
$seo->setTitle($pageTitle)
    ->setDescription($description)
    ->setKeywords($keywords)
    ->setCanonicalUrl($canonicalUrl)
    ->setOgType('product');

// إذا كانت هناك صورة للدواء
if (!empty($medication['image_url'])) {
    $seo->setOgImage($medication['image_url']);
}

// إضافة مخطط بيانات Product
$seo->addMedicationSchema($medication);

// إضافة مخطط Drug
$seo->addMedicalSchema($medication);

// إضافة مخطط FAQ
$seo->addFAQSchema($faqs);

// إضافة مخطط Breadcrumb
$breadcrumbs = [
    'الرئيسية' => getBaseUrl(),
];
if (!empty($medication['category'])) {
    $breadcrumbs[$medication['category']] = getCategoryUrl($medication['category']);
}
$breadcrumbs[$medication['trade_name']] = $canonicalUrl;
$seo->addBreadcrumbSchema($breadcrumbs);

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <!-- شريط التنقل -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo getBaseUrl(); ?>">الرئيسية</a></li>
            <?php if (!empty($medication['category'])): ?>
                <li class="breadcrumb-item">
                    <a href="<?php echo getCategoryUrl($medication['category']); ?>">
                        <?php echo $medication['category']; ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $medication['trade_name']; ?></li>
        </ol>
    </nav>

    <div class="row">
        <!-- صورة وتفاصيل الدواء الأساسية -->
        <div class="col-lg-4 mb-4">
            <div class="text-center mb-4">
                <?php if (!empty($medication['image_url'])): ?>
                    <img src="<?php echo $medication['image_url']; ?>" class="med-detail-img img-fluid mb-3" alt="<?php echo $medication['trade_name']; ?>">
                <?php else: ?>
                    <div class="med-detail-img d-flex align-items-center justify-content-center bg-light">
                        <i class="fas fa-prescription-bottle-alt fa-5x text-secondary"></i>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="fetchImageFromGoogle(<?php echo $medicationId; ?>)">
                                <i class="fas fa-image me-1"></i> جلب صورة من جوجل
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> معلومات أساسية</h5>
                </div>
                <div class="card-body">
                    <ul class="med-info-list">
                        <?php if (!empty($medication['scientific_name'])): ?>
                            <li>
                                <span class="label">المادة الفعالة:</span>
                                <span>
                                    <?php echo $medication['scientific_name']; ?>
                                    <a href="search.php?scientific_name=<?php echo urlencode($medication['scientific_name']); ?>" class="badge bg-primary ms-2" title="بحث عن نفس المادة الفعالة">
                                        <i class="fas fa-search"></i>
                                    </a>
                                </span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($medication['company'])): ?>
                            <li>
                                <span class="label">الشركة المنتجة:</span>
                                <span>
                                    <?php echo $medication['company']; ?>
                                    <a href="search.php?company=<?php echo urlencode($medication['company']); ?>" class="badge bg-primary ms-2" title="بحث عن أدوية الشركة">
                                        <i class="fas fa-search"></i>
                                    </a>
                                </span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($medication['category'])): ?>
                            <li>
                                <span class="label">التصنيف:</span>
                                <span>
                                    <?php echo $medication['category']; ?>
                                    <a href="<?php echo getCategoryUrl($medication['category']); ?>" class="badge bg-primary ms-2" title="بحث في نفس التصنيف">
                                        <i class="fas fa-search"></i>
                                    </a>
                                </span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($medication['units_per_package'])): ?>
                            <li>
                                <span class="label">عدد الوحدات:</span>
                                <span><?php echo $medication['units_per_package']; ?> وحدة</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($medication['barcode'])): ?>
                            <li>
                                <span class="label">الباركود:</span>
                                <span>
                                    <?php echo $medication['barcode']; ?>
                                    <a href="search.php?barcode=<?php echo urlencode($medication['barcode']); ?>" class="badge bg-primary ms-2" title="بحث بالباركود">
                                        <i class="fas fa-barcode"></i>
                                    </a>
                                </span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($medication['price_updated_date'])): ?>
                            <li>
                                <span class="label">تاريخ تحديث السعر:</span>
                                <span><?php echo date('d/m/Y', strtotime($medication['price_updated_date'])); ?></span>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($medication['visit_count'])): ?>
                            <li>
                                <span class="label">عدد الزيارات:</span>
                                <span>
                                    <span class="badge bg-secondary"><?php echo number_format($medication['visit_count']); ?></span>
                                </span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="price-section rounded shadow-sm p-3">
                <div class="price-header mb-3 d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">السعر</h4>
                    <?php if ($alternatives && count($alternatives) > 0): ?>
                        <?php
                        $cheapestAlternative = $alternatives[0];
                        $priceDifference = $medication['current_price'] - $cheapestAlternative['current_price'];
                        $percentageDiff = ($priceDifference / $medication['current_price']) * 100;
                        
                        if ($priceDifference > 0):
                        ?>
                            <div class="cheaper-alternative-badge" title="يوجد بديل أرخص">
                                <i class="fas fa-tags"></i> توفير <?php echo round($percentageDiff); ?>%
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-center align-items-center">
                    <h3 class="price-tag mb-0"><?php echo number_format($medication['current_price'], 2); ?> ج.م</h3>
                    <?php if ($medication['old_price'] > 0 && $medication['old_price'] > $medication['current_price']): ?>
                        <span class="old-price ms-3"><?php echo number_format($medication['old_price'], 2); ?> ج.م</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($medication['units_per_package'] > 1): ?>
                    <div class="mt-2 text-muted text-center">
                        سعر الوحدة: <?php echo number_format($medication['unit_price'], 2); ?> ج.م
                    </div>
                <?php endif; ?>

                <?php if ($alternatives && count($alternatives) > 0 && $alternatives[0]['current_price'] < $medication['current_price']): ?>
                <div class="cheapest-alternative mt-3 text-center">
                    <a href="#alternatives" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-tags me-1"></i> عرض البدائل الأرخص
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- المعلومات التفصيلية -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title mb-4"><?php echo $medication['trade_name']; ?></h2>
                    
                    <?php if (!empty($medication['arabic_name'])): ?>
                        <p class="text-muted mb-4"><?php echo $medication['arabic_name']; ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($medication['description'])): ?>
                        <div class="mb-4">
                            <h5>وصف الدواء</h5>
                            <p><?php echo nl2br($medication['description']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- علامات التبويب -->
                    <ul class="nav nav-tabs detail-tabs" id="medicationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="usage-tab" data-bs-toggle="tab" data-bs-target="#usage" type="button" role="tab">
                                <i class="fas fa-clipboard-check me-1"></i> الاستخدامات والجرعات
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="side-effects-tab" data-bs-toggle="tab" data-bs-target="#side-effects" type="button" role="tab">
                                <i class="fas fa-exclamation-triangle me-1"></i> الآثار الجانبية
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="warnings-tab" data-bs-toggle="tab" data-bs-target="#warnings" type="button" role="tab">
                                <i class="fas fa-shield-alt me-1"></i> تحذيرات واحتياطات
                            </button>
                        </li>
                    </ul>
                    
                    <!-- محتوى علامات التبويب -->
                    <div class="tab-content" id="medicationTabsContent">
                        <!-- علامة تبويب الاستخدامات والجرعات -->
                        <div class="tab-pane fade show active" id="usage" role="tabpanel" aria-labelledby="usage-tab">
                            <?php if (!empty($medicationDetails['indications']) || !empty($medicationDetails['dosage'])): ?>
                                <?php if (!empty($medicationDetails['indications'])): ?>
                                    <h5 class="mb-3"><i class="fas fa-clipboard-list text-primary me-2"></i> دواعي الاستعمال</h5>
                                    <p><?php echo nl2br($medicationDetails['indications']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($medicationDetails['dosage'])): ?>
                                    <h5 class="mb-3 mt-4"><i class="fas fa-prescription text-primary me-2"></i> الجرعات</h5>
                                    <p><?php echo nl2br($medicationDetails['dosage']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($medicationDetails['usage_instructions'])): ?>
                                    <h5 class="mb-3 mt-4"><i class="fas fa-tasks text-primary me-2"></i> إرشادات الاستخدام</h5>
                                    <p><?php echo nl2br($medicationDetails['usage_instructions']); ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> 
                                    لا تتوفر حالياً معلومات تفصيلية عن استخدامات وجرعات هذا الدواء. يرجى استشارة الطبيب أو الصيدلي.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- علامة تبويب الآثار الجانبية -->
                        <div class="tab-pane fade" id="side-effects" role="tabpanel" aria-labelledby="side-effects-tab">
                            <?php if (!empty($medicationDetails['side_effects'])): ?>
                                <h5 class="mb-3"><i class="fas fa-exclamation-circle text-warning me-2"></i> الآثار الجانبية المحتملة</h5>
                                <p><?php echo nl2br($medicationDetails['side_effects']); ?></p>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> 
                                    لا تتوفر حالياً معلومات تفصيلية عن الآثار الجانبية لهذا الدواء. يرجى استشارة الطبيب أو الصيدلي.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- علامة تبويب التحذيرات والاحتياطات -->
                        <div class="tab-pane fade" id="warnings" role="tabpanel" aria-labelledby="warnings-tab">
                            <div class="row">
                                <div class="col-md-6">
                                    <?php if (!empty($medicationDetails['contraindications'])): ?>
                                        <h5 class="mb-3"><i class="fas fa-ban text-danger me-2"></i> موانع الاستعمال</h5>
                                        <p><?php echo nl2br($medicationDetails['contraindications']); ?></p>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> 
                                            لا تتوفر حالياً معلومات عن موانع استعمال هذا الدواء.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <?php if (!empty($medicationDetails['interactions'])): ?>
                                        <h5 class="mb-3"><i class="fas fa-random text-warning me-2"></i> التفاعلات الدوائية</h5>
                                        <p><?php echo nl2br($medicationDetails['interactions']); ?></p>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> 
                                            لا تتوفر حالياً معلومات عن التفاعلات الدوائية لهذا الدواء.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($medicationDetails['storage_info'])): ?>
                                <h5 class="mb-3 mt-4"><i class="fas fa-temperature-low text-primary me-2"></i> ظروف التخزين</h5>
                                <p><?php echo nl2br($medicationDetails['storage_info']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الأدوية المشابهة الأكثر زيارة -->
            <?php if (!empty($similarPopularMeds)): ?>
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> الأدوية المشابهة الأكثر زيارة</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الاسم التجاري</th>
                                    <th>الشركة</th>
                                    <th>السعر</th>
                                    <th>عدد الزيارات</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($similarPopularMeds as $med): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo getMedicationUrl($med['id'], $med['trade_name']); ?>" class="text-decoration-none">
                                                <strong><?php echo $med['trade_name']; ?></strong>
                                            </a>
                                        </td>
                                        <td><?php echo $med['company']; ?></td>
                                        <td><?php echo number_format($med['current_price'], 2); ?> ج.م</td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo number_format($med['visit_count']); ?></span>
                                        </td>
                                        <td>
                                            <a href="<?php echo getMedicationUrl($med['id'], $med['trade_name']); ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Resto del código... -->
            
            <!-- الأدوية المثيلة (نفس المادة الفعالة ونفس الشركة) -->
            <?php if (!empty($equivalentMeds)): ?>
            <div class="card mb-4" id="equivalents">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-clone me-2"></i> الأدوية المثيلة (نفس الشركة)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <i class="fas fa-info-circle me-1"></i> 
                        هذه الأدوية من نفس الشركة المصنعة وتحتوي على نفس المادة الفعالة، لكن قد تختلف في التركيز أو الشكل الصيدلاني.
                    </p>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الاسم التجاري</th>
                                    <th>الشكل الصيدلاني</th>
                                    <th>التركيز</th>
                                    <th>العبوة</th>
                                    <th>السعر</th>
                                    <th>الفرق</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equivalentMeds as $eq): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo getMedicationUrl($eq['id'], $eq['trade_name']); ?>" class="text-decoration-none fw-bold">
                                                <?php echo $eq['trade_name']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo $eq['form'] ?? "-"; ?></td>
                                        <td><?php echo $eq['strength'] ?? "-"; ?></td>
                                        <td><?php echo $eq['units_per_package']; ?> وحدة</td>
                                        <td class="fw-bold">
                                            <?php echo number_format($eq['current_price'], 2); ?> ج.م
                                        </td>
                                        <td>
                                            <?php 
                                            $priceDiff = $medication['current_price'] - $eq['current_price']; 
                                            if ($priceDiff > 0):
                                            ?>
                                                <span class="badge bg-success">أرخص بـ <?php echo number_format($priceDiff, 2); ?> ج.م</span>
                                            <?php elseif ($priceDiff < 0): ?>
                                                <span class="badge bg-danger">أغلى بـ <?php echo number_format(abs($priceDiff), 2); ?> ج.م</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">نفس السعر</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="compare.php?ids=<?php echo $medicationId . ',' . $eq['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                مقارنة
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- البدائل المتاحة (نفس المادة الفعالة لكن شركات مختلفة) -->
            <div class="card mb-4" id="alternatives">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i> البدائل المتاحة (نفس المادة الفعالة)</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($alternatives)): ?>
                        <p class="text-muted mb-3">
                            <i class="fas fa-info-circle me-1"></i> 
                            هذه الأدوية تحتوي على نفس المادة الفعالة (<?php echo $medication['scientific_name']; ?>) ولكن من شركات مصنعة مختلفة. مرتبة من الأرخص إلى الأغلى.
                        </p>
                        
                        <!-- خيارات عرض وتصفية البدائل -->
                        <div class="alternatives-filters d-flex flex-wrap align-items-center gap-2 mb-3">
                            <div class="me-3">
                                <label class="form-label mb-0 ms-2">تصفية حسب:</label>
                            </div>
                            <div class="filter-buttons">
                                <button class="btn btn-sm btn-primary active" data-filter="all">الكل</button>
                                <button class="btn btn-sm btn-outline-primary" data-filter="cheaper">البدائل الأرخص</button>
                                <button class="btn btn-sm btn-outline-primary" data-filter="similar">بنفس السعر تقريباً</button>
                                <button class="btn btn-sm btn-outline-primary" data-filter="expensive">البدائل الأغلى</button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover alternatives-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">الاسم التجاري</th>
                                        <th>الشركة</th>
                                        <th>العبوة</th>
                                        <th>السعر الحالي</th>
                                        <th>فرق السعر</th>
                                        <th>نسبة التوفير</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alternatives as $alt): 
                                        $priceDiff = $medication['current_price'] - $alt['current_price'];
                                        $percentageDiff = ($medication['current_price'] > 0) ? ($priceDiff / $medication['current_price']) * 100 : 0;
                                        $priceCategory = $priceDiff > 5 ? 'cheaper' : ($priceDiff < -5 ? 'expensive' : 'similar');
                                    ?>
                                        <tr data-category="<?php echo $priceCategory; ?>">
                                            <td>
                                                <a href="<?php echo getMedicationUrl($alt['id'], $alt['trade_name']); ?>" class="text-decoration-none">
                                                    <strong><?php echo $alt['trade_name']; ?></strong>
                                                </a>
                                                <?php if ($alt === reset($alternatives) && $priceCategory === 'cheaper'): ?>
                                                    <span class="badge bg-success ms-2">الأرخص</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="search.php?company=<?php echo urlencode($alt['company']); ?>" class="text-decoration-none">
                                                    <?php echo $alt['company']; ?>
                                                </a>
                                            </td>
                                            <td><?php echo $alt['units_per_package']; ?> وحدة</td>
                                            <td class="fw-bold"><?php echo number_format($alt['current_price'], 2); ?> ج.م</td>
                                            <td>
                                                <?php if ($priceDiff > 0): ?>
                                                    <span class="text-success fw-bold">
                                                        <i class="fas fa-arrow-down"></i> 
                                                        <?php echo number_format($priceDiff, 2); ?> ج.م
                                                    </span>
                                                <?php elseif ($priceDiff < 0): ?>
                                                    <span class="text-danger fw-bold">
                                                        <i class="fas fa-arrow-up"></i> 
                                                        <?php echo number_format(abs($priceDiff), 2); ?> ج.م
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">0.00 ج.م</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($priceDiff > 0): ?>
                                                    <div class="saving-percentage">
                                                        <span class="percentage"><?php echo round($percentageDiff); ?>%</span>
                                                        <div class="progress" style="height: 5px;">
                                                            <div class="progress-bar bg-success" style="width: <?php echo min(round($percentageDiff), 100); ?>%;"></div>
                                                        </div>
                                                    </div>
                                                <?php elseif ($priceDiff < 0): ?>
                                                    <span class="badge bg-danger">أغلى</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">متساوي</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="<?php echo getMedicationUrl($alt['id'], $alt['trade_name']); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="compare.php?ids=<?php echo $medicationId . ',' . $alt['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> 
                            لا توجد حالياً بدائل لهذا الدواء تحتوي على نفس المادة الفعالة (<?php echo $medication['scientific_name']; ?>) من شركات أخرى.
                        </div>
                    <?php endif; ?>
                    
                    <!-- زر المقارنة -->
                    <div class="text-center mt-3">
                        <a href="compare.php?ids=<?php echo $medicationId; ?>" class="btn btn-primary">
                            <i class="fas fa-balance-scale me-2"></i> المقارنة المتقدمة بين الأدوية
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- البدائل العلاجية (مواد فعالة مختلفة) -->
            <?php if (!empty($therapeuticAlternatives)): ?>
            <div class="card mb-4" id="therapeutic-alternatives">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-first-aid me-2"></i> البدائل العلاجية (مواد فعالة مختلفة)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <i class="fas fa-info-circle me-1"></i> 
                        هذه الأدوية تحتوي على مواد فعالة مختلفة ولكن لها تأثيرات علاجية مشابهة. استشر الطبيب قبل استبدال الدواء.
                    </p>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الاسم التجاري</th>
                                    <th>المادة الفعالة</th>
                                    <th>الشركة</th>
                                    <th>السعر</th>
                                    <th>مدى التشابه</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($therapeuticAlternatives as $alt): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo getMedicationUrl($alt['id'], $alt['trade_name']); ?>" class="text-decoration-none fw-bold">
                                                <?php echo $alt['trade_name']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="search.php?scientific_name=<?php echo urlencode($alt['scientific_name']); ?>" class="text-decoration-none">
                                                <?php echo $alt['scientific_name']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="search.php?company=<?php echo urlencode($alt['company']); ?>" class="text-decoration-none">
                                                <?php echo $alt['company']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($alt['current_price'], 2); ?> ج.م</strong>
                                            <?php 
                                            $priceDiff = $medication['current_price'] - $alt['current_price'];
                                            if ($priceDiff > 0):
                                            ?>
                                                <span class="badge bg-success ms-2">أوفر <?php echo number_format($priceDiff, 2); ?> ج.م</span>
                                            <?php elseif ($priceDiff < 0): ?>
                                                <span class="badge bg-danger ms-2">أغلى <?php echo number_format(abs($priceDiff), 2); ?> ج.م</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="progress">
                                                <div 
                                                    class="progress-bar bg-success" 
                                                    role="progressbar" 
                                                    style="width: <?php echo ($alt['similarity_score'] * 100); ?>%"
                                                    aria-valuenow="<?php echo ($alt['similarity_score'] * 100); ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="100"
                                                >
                                                    <?php echo ($alt['similarity_score'] * 100); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="<?php echo getMedicationUrl($alt['id'], $alt['trade_name']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="compare.php?ids=<?php echo $medicationId . ',' . $alt['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i> 
                        <strong>تنبيه هام:</strong> البدائل العلاجية تحتوي على مواد فعالة مختلفة وقد تختلف في فعاليتها وآثارها الجانبية. يجب استشارة الطبيب أو الصيدلي قبل استبدال الدواء.
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- إضافة قسم الأسئلة الشائعة -->
            <div class="card mb-4" id="faq">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i> الأسئلة الشائعة حول <?php echo $medication['trade_name']; ?></h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="faqAccordion">
                        <?php foreach ($faqs as $index => $faq): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqHeading<?php echo $index; ?>">
                                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#faqCollapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                            aria-controls="faqCollapse<?php echo $index; ?>">
                                        <?php echo $faq['question']; ?>
                                    </button>
                                </h2>
                                <div id="faqCollapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                     aria-labelledby="faqHeading<?php echo $index; ?>" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <?php echo nl2br($faq['answer']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- جافاسكريبت لتصفية البدائل وجلب الصور -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // أزرار تصفية البدائل
    const filterButtons = document.querySelectorAll('.filter-buttons .btn');
    const alternativeRows = document.querySelectorAll('.alternatives-table tbody tr');
    
    // تفعيل زر التصفية المحدد وإظهار/إخفاء الصفوف المناسبة
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // إزالة الكلاس النشط من جميع الأزرار
            filterButtons.forEach(btn => btn.classList.remove('active', 'btn-primary'));
            filterButtons.forEach(btn => btn.classList.add('btn-outline-primary'));
            
            // إضافة الكلاس النشط للزر المضغوط
            this.classList.add('active', 'btn-primary');
            this.classList.remove('btn-outline-primary');
            
            // الحصول على فلتر البيانات
            const filter = this.getAttribute('data-filter');
            
            // تصفية الصفوف
            alternativeRows.forEach(row => {
                if (filter === 'all' || row.getAttribute('data-category') === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    
    // جلب صورة من جوجل (للمسؤولين فقط)
    window.fetchImageFromGoogle = function(medicationId) {
        if (!confirm('هل تريد جلب صورة لهذا الدواء من جوجل؟')) {
            return;
        }
        
        // عرض مؤشر التحميل
        const imageContainer = document.querySelector('.med-detail-img');
        imageContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x text-primary"></i><p class="mt-2">جاري جلب الصورة...</p></div>';
        
        // إرسال طلب لجلب الصورة
        fetch('fetch_image.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + medicationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // تحديث الصورة
                imageContainer.innerHTML = '<img src="' + data.image_url + '" class="img-fluid mb-3" alt="صورة الدواء">';
                alert('تم جلب الصورة بنجاح!');
            } else {
                // عرض رسالة الخطأ
                imageContainer.innerHTML = '<div class="d-flex align-items-center justify-content-center flex-column"><i class="fas fa-prescription-bottle-alt fa-5x text-secondary"></i><p class="text-danger mt-2">' + data.message + '</p></div>';
                alert('فشل في جلب الصورة: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching image:', error);
            imageContainer.innerHTML = '<div class="d-flex align-items-center justify-content-center flex-column"><i class="fas fa-prescription-bottle-alt fa-5x text-secondary"></i><p class="text-danger mt-2">حدث خطأ أثناء جلب الصورة</p></div>';
            alert('حدث خطأ أثناء جلب الصورة');
        });
    };
});
</script>

<style>
/* تنسيقات خاصة بصفحة تفاصيل الدواء */
.med-detail-img {
    max-height: 300px;
    width: auto;
    margin: 0 auto;
    object-fit: contain;
}

.med-info-list {
    list-style: none;
    padding: 0;
}

.med-info-list li {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
}

.med-info-list .label {
    font-weight: 600;
    margin-bottom: 4px;
    color: #666;
}

.price-tag {
    font-size: 2rem;
    font-weight: 700;
    color: #0d6efd;
}

.old-price {
    text-decoration: line-through;
    color: #6c757d;
    font-size: 1.2rem;
}

.cheaper-alternative-badge {
    background-color: #198754;
    color: white;
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 4px;
}

.detail-tabs .nav-link {
    padding: 10px 15px;
    border-radius: 0;
    font-weight: 500;
}

.saving-percentage {
    width: 100%;
}

.percentage {
    font-weight: 600;
    color: #198754;
    margin-bottom: 4px;
    display: block;
}

.alternatives-filters {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.filter-buttons .btn {
    margin-right: 5px;
}

.search-highlight {
    background-color: rgba(255, 193, 7, 0.3);
    padding: 0 2px;
    border-radius: 2px;
}

/* تنسيقات قسم الأسئلة الشائعة */
.accordion-button:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}

.accordion-button:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.accordion-body {
    padding: 1rem 1.25rem;
    background-color: #f8f9fa;
}
</style>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>