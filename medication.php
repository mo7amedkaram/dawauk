<?php
// medication.php - صفحة تفاصيل الدواء مع تحسينات عرض البدائل والمثيل
require_once 'config/database.php';

// التحقق من وجود معرف الدواء
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$medicationId = (int) $_GET['id'];

// الحصول على بيانات الدواء
$db = Database::getInstance();
$medication = $db->fetchOne("SELECT * FROM medications WHERE id = ?", [$medicationId]);

// التحقق من وجود الدواء
if (!$medication) {
    header('Location: index.php');
    exit;
}

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

// إعداد معلومات الصفحة
$pageTitle = $medication['trade_name'];
$currentPage = '';

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <!-- شريط التنقل -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
            <?php if (!empty($medication['category'])): ?>
                <li class="breadcrumb-item">
                    <a href="search.php?category=<?php echo urlencode($medication['category']); ?>">
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
                    <div class="med-detail-img d-flex align-items-center justify-content-center">
                        <i class="fas fa-prescription-bottle-alt fa-5x text-secondary"></i>
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
                                    <a href="search.php?category=<?php echo urlencode($medication['category']); ?>" class="badge bg-primary ms-2" title="بحث في نفس التصنيف">
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
                                            <a href="medication.php?id=<?php echo $eq['id']; ?>" class="text-decoration-none fw-bold">
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
                                            <a href="compare.php?id1=<?php echo $medicationId; ?>&id2=<?php echo $eq['id']; ?>" class="btn btn-sm btn-outline-primary">
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
                                        $percentageDiff = ($priceDiff / $medication['current_price']) * 100;
                                        $priceCategory = $priceDiff > 5 ? 'cheaper' : ($priceDiff < -5 ? 'expensive' : 'similar');
                                    ?>
                                        <tr data-category="<?php echo $priceCategory; ?>">
                                            <td>
                                                <a href="medication.php?id=<?php echo $alt['id']; ?>" class="text-decoration-none">
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
                                                    <a href="medication.php?id=<?php echo $alt['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="compare.php?id1=<?php echo $medicationId; ?>&id2=<?php echo $alt['id']; ?>" class="btn btn-sm btn-outline-primary">
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
                        <a href="compare.php?id1=<?php echo $medicationId; ?>" class="btn btn-primary">
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
                                            <a href="medication.php?id=<?php echo $alt['id']; ?>" class="text-decoration-none fw-bold">
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
                                                <a href="medication.php?id=<?php echo $alt['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="compare.php?id1=<?php echo $medicationId; ?>&id2=<?php echo $alt['id']; ?>" class="btn btn-sm btn-outline-primary">
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
        </div>
    </div>
</div>

<!-- جافاسكريبت لتصفية البدائل -->
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
});
</script>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>