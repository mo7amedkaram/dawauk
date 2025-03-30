<?php
// compare.php - صفحة مقارنة الأدوية
require_once 'config/database.php';
require_once 'includes/stats_tracking.php';

// إعداد معلومات الصفحة
$pageTitle = 'مقارنة الأدوية';
$currentPage = 'compare';

$db = Database::getInstance();
$statsTracker = new StatsTracker($db);

// الأدوية للمقارنة (بدون حد أقصى)
$medications = [];
$medicationIds = [];

// جلب معرفات الأدوية من الطلب
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    // تقسيم سلسلة معرفات الأدوية المفصولة بفواصل
    $medicationIds = array_map('intval', explode(',', $_GET['ids']));
} elseif (isset($_GET['id1']) && is_numeric($_GET['id1'])) {
    // للتوافق مع النسخة القديمة
    for ($i = 1; $i <= 3; $i++) {
        if (isset($_GET['id'.$i]) && is_numeric($_GET['id'.$i])) {
            $medicationIds[] = (int)$_GET['id'.$i];
        }
    }
}

// إذا تم تحديد معرف واحد على الأقل، قم بجلب بيانات الأدوية
if (!empty($medicationIds)) {
    // إنشاء علامات الاستفهام للاستعلام المعد
    $placeholders = implode(',', array_fill(0, count($medicationIds), '?'));
    
    // استعلام للحصول على بيانات الأدوية المحددة
    $medicationsData = $db->fetchAll(
        "SELECT * FROM medications WHERE id IN ($placeholders) ORDER BY FIELD(id, ".implode(',', $medicationIds).")",
        $medicationIds
    );
    
    foreach ($medicationsData as $med) {
        $medications[] = $med;
        
        // جلب المعلومات الإضافية للدواء
        $details = $db->fetchOne("SELECT * FROM medication_details WHERE medication_id = ?", [$med['id']]);
        if ($details) {
            $medications[count($medications)-1]['details'] = $details;
        }
        
        // تحديث عدد مرات الزيارة
        $statsTracker->trackMedicationVisit($med['id']);
    }
}

// جلب أحدث الأدوية للبحث
$recentMedications = $db->fetchAll("SELECT id, trade_name, scientific_name, company FROM medications ORDER BY id DESC LIMIT 20");

// جلب الأدوية الأكثر زيارة
$popularMedications = $db->fetchAll("SELECT id, trade_name, scientific_name, company FROM medications ORDER BY visit_count DESC LIMIT 20");

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">مقارنة الأدوية</h1>
    
    <div class="compare-select-container mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">اختر الأدوية للمقارنة</h5>
                
                <!-- نموذج بحث ديناميكي -->
                <form id="compareForm" action="compare.php" method="GET" class="mb-0">
                    <input type="hidden" name="ids" id="medicationIds" value="<?php echo implode(',', $medicationIds); ?>">
                    
                    <div class="search-med-container mb-3">
                        <label for="searchMedication" class="form-label">البحث عن دواء لإضافته للمقارنة</label>
                        <div class="search-container position-relative">
                            <input type="text" id="searchMedication" class="form-control" placeholder="أدخل اسم الدواء للبحث...">
                            <div id="searchResults" class="search-results-dropdown"></div>
                        </div>
                    </div>
                    
                    <div class="selected-meds mb-3" id="selectedMedications">
                        <!-- هنا ستظهر الأدوية المحددة -->
                        <?php if (!empty($medications)): ?>
                            <?php foreach ($medications as $med): ?>
                                <div class="selected-med-item" data-id="<?php echo $med['id']; ?>">
                                    <span class="med-name"><?php echo $med['trade_name']; ?></span>
                                    <span class="med-info"><?php echo $med['scientific_name']; ?> | <?php echo $med['company']; ?></span>
                                    <button type="button" class="btn-remove-med" onclick="removeMedication(<?php echo $med['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- اقتراحات سريعة -->
                    <div class="quick-suggestions mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="suggestions-container">
                                    <h6 class="mb-2">أحدث الأدوية</h6>
                                    <div class="suggestions-list">
                                        <?php foreach ($recentMedications as $med): ?>
                                            <button type="button" class="suggestion-item" 
                                                    onclick="addMedicationToCompare(<?php echo $med['id']; ?>, '<?php echo addslashes($med['trade_name']); ?>', '<?php echo addslashes($med['scientific_name']); ?>', '<?php echo addslashes($med['company']); ?>')">
                                                <?php echo $med['trade_name']; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="suggestions-container">
                                    <h6 class="mb-2">الأكثر زيارة</h6>
                                    <div class="suggestions-list">
                                        <?php foreach ($popularMedications as $med): ?>
                                            <button type="button" class="suggestion-item" 
                                                    onclick="addMedicationToCompare(<?php echo $med['id']; ?>, '<?php echo addslashes($med['trade_name']); ?>', '<?php echo addslashes($med['scientific_name']); ?>', '<?php echo addslashes($med['company']); ?>')">
                                                <?php echo $med['trade_name']; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- أزرار التحكم -->
                    <div class="compare-actions">
                        <button type="submit" class="btn btn-primary" id="compareBtn" <?php echo empty($medications) ? 'disabled' : ''; ?>>
                            <i class="fas fa-balance-scale me-2"></i> مقارنة
                        </button>
                        <?php if (!empty($medications)): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="clearCompare()">
                                <i class="fas fa-trash me-2"></i> إلغاء
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php if (count($medications) >= 2): ?>
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
                            
                            <!-- عدد مرات الزيارة -->
                            <tr>
                                <td class="feature-name">عدد الزيارات</td>
                                <?php foreach ($medications as $index => $med): ?>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo !empty($med['visit_count']) ? number_format($med['visit_count']) : '0'; ?></span>
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
    <?php elseif (isset($_GET['ids']) || isset($_GET['id1'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i> 
            يرجى اختيار دواءين على الأقل للمقارنة.
        </div>
    <?php else: ?>
        <div class="text-center p-5 bg-light rounded">
            <i class="fas fa-balance-scale fa-4x text-primary mb-3"></i>
            <h4>اختر الأدوية للمقارنة</h4>
            <p class="text-muted">استخدم النموذج أعلاه لاختيار الأدوية التي ترغب في مقارنتها</p>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript لصفحة المقارنة -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // متغيرات عامة
    const searchInput = document.getElementById('searchMedication');
    const searchResults = document.getElementById('searchResults');
    const selectedMedications = document.getElementById('selectedMedications');
    const medicationIdsField = document.getElementById('medicationIds');
    const compareBtn = document.getElementById('compareBtn');
    
    // إضافة مستمع الحدث للبحث
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }
            
            searchMedications(query);
        }, 300));
    }
    
    // إخفاء نتائج البحث عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
    
    // البحث عن الأدوية
    function searchMedications(query) {
        searchResults.innerHTML = '<div class="loading-results">جاري البحث...</div>';
        searchResults.style.display = 'block';
        
        fetch(`search_autocomplete.php?q=${encodeURIComponent(query)}&limit=10`)
            .then(response => response.json())
            .then(data => {
                searchResults.innerHTML = '';
                
                if (data.length === 0) {
                    searchResults.innerHTML = '<div class="no-results">لم يتم العثور على نتائج</div>';
                    return;
                }
                
                data.forEach(med => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'result-item';
                    resultItem.innerHTML = `
                        <div class="result-item-info">
                            <div class="result-item-name">${med.trade_name}</div>
                            <div class="result-item-details">${med.scientific_name} | ${med.company}</div>
                        </div>
                    `;
                    
                    resultItem.addEventListener('click', function() {
                        addMedicationToCompare(med.id, med.trade_name, med.scientific_name, med.company);
                        searchInput.value = '';
                        searchResults.style.display = 'none';
                    });
                    
                    searchResults.appendChild(resultItem);
                });
            })
            .catch(error => {
                console.error('Error searching medications:', error);
                searchResults.innerHTML = '<div class="error-results">حدث خطأ أثناء البحث</div>';
            });
    }
    
    // إضافة دواء للمقارنة
    window.addMedicationToCompare = function(id, name, scientificName, company) {
        // التحقق من أن الدواء غير موجود بالفعل
        const existingMeds = document.querySelectorAll(`.selected-med-item[data-id="${id}"]`);
        if (existingMeds.length > 0) {
            alert("هذا الدواء موجود بالفعل في المقارنة");
            return;
        }
        
        // إنشاء عنصر الدواء المحدد
        const medItem = document.createElement('div');
        medItem.className = 'selected-med-item';
        medItem.setAttribute('data-id', id);
        medItem.innerHTML = `
            <span class="med-name">${name}</span>
            <span class="med-info">${scientificName} | ${company}</span>
            <button type="button" class="btn-remove-med" onclick="removeMedication(${id})">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        selectedMedications.appendChild(medItem);
        
        // تحديث حقل معرفات الأدوية
        updateMedicationIds();
        
        // تفعيل زر المقارنة
        compareBtn.disabled = document.querySelectorAll('.selected-med-item').length < 2;
    };
    
    // حذف دواء من المقارنة
    window.removeMedication = function(id) {
        const medItem = document.querySelector(`.selected-med-item[data-id="${id}"]`);
        if (medItem) {
            medItem.remove();
            updateMedicationIds();
            
            // تعطيل زر المقارنة إذا لم يتبق أدوية كافية
            compareBtn.disabled = document.querySelectorAll('.selected-med-item').length < 2;
        }
    };
    
    // إلغاء المقارنة وإفراغ القائمة
    window.clearCompare = function() {
        selectedMedications.innerHTML = '';
        updateMedicationIds();
        compareBtn.disabled = true;
    };
    
    // تحديث حقل معرفات الأدوية
    function updateMedicationIds() {
        const medItems = document.querySelectorAll('.selected-med-item');
        const ids = Array.from(medItems).map(item => item.getAttribute('data-id'));
        medicationIdsField.value = ids.join(',');
    }
    
    // دالة تأخير للحد من عدد طلبات البحث
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                func.apply(context, args);
            }, wait);
        };
    }
});
</script>

<style>
/* تنسيقات صفحة المقارنة المحسنة */
.search-container {
    position: relative;
    margin-bottom: 15px;
}

.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border: 1px solid #dee2e6;
    border-radius: 0 0 5px 5px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
    display: none;
}

.result-item {
    padding: 10px 15px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s;
}

.result-item:hover {
    background-color: #f8f9fa;
}

.result-item:last-child {
    border-bottom: none;
}

.result-item-name {
    font-weight: 600;
    margin-bottom: 3px;
}

.result-item-details {
    font-size: 0.85rem;
    color: #6c757d;
}

.loading-results, .no-results, .error-results {
    padding: 15px;
    text-align: center;
    color: #6c757d;
}

.error-results {
    color: #dc3545;
}

.selected-meds {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    min-height: 40px;
}

.selected-med-item {
    display: flex;
    align-items: center;
    background-color: #f0f8ff;
    border: 1px solid #d7e9fc;
    border-radius: 20px;
    padding: 5px 15px;
    font-size: 0.9rem;
}

.med-name {
    font-weight: 600;
    margin-right: 5px;
}

.med-info {
    color: #6c757d;
    font-size: 0.8rem;
    margin: 0 5px;
}

.btn-remove-med {
    background: none;
    border: none;
    color: #dc3545;
    font-size: 0.9rem;
    cursor: pointer;
    padding: 0 5px;
}

.compare-actions {
    display: flex;
    gap: 10px;
}

.suggestions-container {
    margin-bottom: 15px;
}

.suggestions-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.suggestion-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 0.85rem;
    transition: all 0.2s;
    cursor: pointer;
}

.suggestion-item:hover {
    background-color: #e9ecef;
    border-color: #ced4da;
}
</style>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>