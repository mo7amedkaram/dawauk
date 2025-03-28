<?php
// compare.php - صفحة مقارنة الأدوية
require_once 'config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'مقارنة الأدوية';
$currentPage = 'compare';

$db = Database::getInstance();

// الأدوية للمقارنة (يمكن مقارنة حتى 3 أدوية)
$medications = [];
$medicationIds = [];

// جلب معرفات الأدوية من الطلب
for ($i = 1; $i <= 3; $i++) {
    if (isset($_GET['id' . $i]) && is_numeric($_GET['id' . $i])) {
        $medicationIds[$i] = (int) $_GET['id' . $i];
    }
}

// إذا تم تحديد معرف واحد على الأقل، قم بجلب بيانات الأدوية
if (!empty($medicationIds)) {
    foreach ($medicationIds as $index => $id) {
        $medication = $db->fetchOne("SELECT * FROM medications WHERE id = ?", [$id]);
        
        if ($medication) {
            $medications[$index] = $medication;
            
            // جلب المعلومات الإضافية للدواء
            $details = $db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$id]);
            if ($details) {
                $medications[$index]['details'] = $details;
            }
        }
    }
}

// جلب جميع الأدوية للبحث
$allMedications = $db->fetchAll("SELECT id, trade_name, scientific_name FROM medications ORDER BY trade_name LIMIT 100");

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">مقارنة الأدوية</h1>
    
    <div class="row mb-4">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">اختر الأدوية للمقارنة</h5>
                    
                    <form action="compare.php" method="GET" class="mb-0">
                        <div class="row">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <div class="col-md-4 mb-3">
                                    <select 
                                        name="id<?php echo $i; ?>" 
                                        class="form-select"
                                        <?php echo $i === 1 ? 'required' : ''; ?>
                                    >
                                        <option value="">اختر الدواء <?php echo $i; ?></option>
                                        <?php foreach ($allMedications as $med): ?>
                                            <option 
                                                value="<?php echo $med['id']; ?>"
                                                <?php echo (isset($medicationIds[$i]) && $medicationIds[$i] == $med['id']) ? 'selected' : ''; ?>
                                            >
                                                <?php echo $med['trade_name']; ?> (<?php echo $med['scientific_name']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-sync-alt me-2"></i> مقارنة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (count($medications) >= 1): ?>
        <div class="card shadow-sm mb-5">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-balance-scale me-2"></i> نتائج المقارنة</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="compare-table table mb-0">
                        <!-- رؤوس الجدول -->
                        <thead>
                            <tr>
                                <th class="text-start">المعلومات</th>
                                <?php foreach ($medications as $index => $med): ?>
                                    <th><?php echo $med['trade_name']; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        
                        <tbody>
                            <!-- صور المنتجات -->
                            <tr>
                                <td class="feature-name">الصورة</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php if (!empty($med['image_url'])): ?>
                                            <img src="<?php echo $med['image_url']; ?>" class="img-fluid" alt="<?php echo $med['trade_name']; ?>" style="max-height: 100px;">
                                        <?php else: ?>
                                            <div class="d-flex justify-content-center">
                                                <i class="fas fa-pills fa-3x text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- المادة الفعالة -->
                            <tr>
                                <td class="feature-name">المادة الفعالة</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td><?php echo !empty($med['scientific_name']) ? $med['scientific_name'] : '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- الشركة المنتجة -->
                            <tr>
                                <td class="feature-name">الشركة المنتجة</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td><?php echo !empty($med['company']) ? $med['company'] : '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- التصنيف -->
                            <tr>
                                <td class="feature-name">التصنيف</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td><?php echo !empty($med['category']) ? $med['category'] : '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- السعر -->
                            <tr>
                                <td class="feature-name">السعر</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <strong class="text-primary"><?php echo number_format($med['current_price'], 2); ?> ج.م</strong>
                                        <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                                            <br>
                                            <small class="text-muted text-decoration-line-through">
                                                <?php echo number_format($med['old_price'], 2); ?> ج.م
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- عدد الوحدات -->
                            <tr>
                                <td class="feature-name">عدد الوحدات</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td><?php echo !empty($med['units_per_package']) ? $med['units_per_package'] : '1'; ?> وحدة</td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- سعر الوحدة -->
                            <tr>
                                <td class="feature-name">سعر الوحدة</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td><?php echo number_format($med['unit_price'], 2); ?> ج.م</td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- دواعي الاستعمال -->
                            <tr>
                                <td class="feature-name">دواعي الاستعمال</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php 
                                        if (isset($med['details']) && !empty($med['details']['indications'])) {
                                            echo nl2br($med['details']['indications']);
                                        } else {
                                            echo '<span class="text-muted">غير متوفر</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- الجرعات -->
                            <tr>
                                <td class="feature-name">الجرعات</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php 
                                        if (isset($med['details']) && !empty($med['details']['dosage'])) {
                                            echo nl2br($med['details']['dosage']);
                                        } else {
                                            echo '<span class="text-muted">غير متوفر</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- الآثار الجانبية -->
                            <tr>
                                <td class="feature-name">الآثار الجانبية</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php 
                                        if (isset($med['details']) && !empty($med['details']['side_effects'])) {
                                            echo nl2br($med['details']['side_effects']);
                                        } else {
                                            echo '<span class="text-muted">غير متوفر</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- موانع الاستعمال -->
                            <tr>
                                <td class="feature-name">موانع الاستعمال</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php 
                                        if (isset($med['details']) && !empty($med['details']['contraindications'])) {
                                            echo nl2br($med['details']['contraindications']);
                                        } else {
                                            echo '<span class="text-muted">غير متوفر</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- التفاعلات الدوائية -->
                            <tr>
                                <td class="feature-name">التفاعلات الدوائية</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php 
                                        if (isset($med['details']) && !empty($med['details']['interactions'])) {
                                            echo nl2br($med['details']['interactions']);
                                        } else {
                                            echo '<span class="text-muted">غير متوفر</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- الباركود -->
                            <tr>
                                <td class="feature-name">الباركود</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td><?php echo !empty($med['barcode']) ? $med['barcode'] : '-'; ?></td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- تاريخ تحديث السعر -->
                            <tr>
                                <td class="feature-name">تحديث السعر</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <?php 
                                        if (!empty($med['price_updated_date'])) {
                                            echo date('d/m/Y', strtotime($med['price_updated_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <!-- روابط التفاصيل -->
                            <tr>
                                <td class="feature-name">التفاصيل</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-primary btn-sm">
                                            عرض التفاصيل
                                        </a>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- نصائح وملاحظات -->
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle me-2"></i> ملاحظات هامة</h5>
            <ul class="mb-0">
                <li>المقارنة الأعلى تعرض المعلومات المتاحة في قاعدة البيانات وقد لا تتضمن جميع الخصائص الكاملة للأدوية.</li>
                <li>يرجى دائمًا استشارة الطبيب أو الصيدلي قبل تغيير أو استبدال أي دواء.</li>
                <li>قد تختلف فعالية الدواء من شخص لآخر حتى مع نفس المادة الفعالة.</li>
            </ul>
        </div>
    <?php elseif (isset($_GET['id1'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i> 
            لم يتم العثور على الأدوية المحددة في قاعدة البيانات. يرجى التأكد من المعرفات وإعادة المحاولة.
        </div>
    <?php else: ?>
        <div class="text-center p-5 bg-light rounded">
            <i class="fas fa-balance-scale fa-4x text-primary mb-3"></i>
            <h4>اختر الأدوية للمقارنة</h4>
            <p class="text-muted">استخدم النموذج أعلاه لاختيار الأدوية التي ترغب في مقارنتها</p>
        </div>
    <?php endif; ?>
</div>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>