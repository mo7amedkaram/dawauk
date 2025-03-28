<?php
// guide.php - صفحة الدليل الدوائي
require_once 'config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'الدليل الدوائي';
$currentPage = 'guide';

$db = Database::getInstance();

// جلب التصنيفات لقائمة التصفية
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");

// معالجة البحث
$letter = isset($_GET['letter']) ? $_GET['letter'] : null;
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;

// الحرف العربي المقابل للحرف الانجليزي المحدد
$arabicLetters = [
    'A' => 'أ', 'B' => 'ب', 'C' => 'س', 'D' => 'د', 'E' => 'ي', 'F' => 'ف',
    'G' => 'ج', 'H' => 'هـ', 'I' => 'ي', 'J' => 'ج', 'K' => 'ك', 'L' => 'ل',
    'M' => 'م', 'N' => 'ن', 'O' => 'ع', 'P' => 'ب', 'Q' => 'ك', 'R' => 'ر',
    'S' => 'س', 'T' => 'ت', 'U' => 'ي', 'V' => 'ف', 'W' => 'و', 'X' => 'إكس',
    'Y' => 'ي', 'Z' => 'ز'
];

// إعداد استعلام البحث
$whereClauses = [];
$params = [];

if ($letter) {
    // البحث حسب الحرف الأول من اسم الدواء
    $whereClauses[] = "trade_name LIKE ?";
    $params[] = $letter . '%';
}

if ($categoryId) {
    // البحث حسب التصنيف
    $category = $db->fetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
    if ($category) {
        $whereClauses[] = "category LIKE ?";
        $params[] = '%' . $category['name'] . '%';
    }
}

// إنشاء استعلام البحث
$whereClause = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
$searchSql = "SELECT * FROM medications $whereClause ORDER BY trade_name LIMIT 100";

// تنفيذ البحث
$medications = [];
if ($letter || $categoryId) {
    $medications = $db->fetchAll($searchSql, $params);
}

// ترتيب الأدوية حسب الحرف الأول
$groupedMedications = [];
if (!empty($medications)) {
    foreach ($medications as $med) {
        $firstLetter = strtoupper(substr($med['trade_name'], 0, 1));
        if (!isset($groupedMedications[$firstLetter])) {
            $groupedMedications[$firstLetter] = [];
        }
        $groupedMedications[$firstLetter][] = $med;
    }
    ksort($groupedMedications);
}

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">الدليل الدوائي</h1>
    
    <div class="row mb-4">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">تصفح الأدوية بالحرف</h5>
                    
                    <div class="alphabet-filter text-center mb-3">
                        <?php foreach (range('A', 'Z') as $char): ?>
                            <a 
                                href="guide.php?letter=<?php echo $char; ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?>" 
                                class="btn btn-sm <?php echo $letter === $char ? 'btn-primary' : 'btn-outline-secondary'; ?> m-1"
                            >
                                <?php echo $char; ?>
                                <?php if (isset($arabicLetters[$char])): ?>
                                    <small class="d-block"><?php echo $arabicLetters[$char]; ?></small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <form action="guide.php" method="GET" class="mb-0">
                        <div class="row">
                            <?php if ($letter): ?>
                                <input type="hidden" name="letter" value="<?php echo $letter; ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-8 mb-3">
                                <select name="category" class="form-select">
                                    <option value="">جميع التصنيفات</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option 
                                            value="<?php echo $cat['id']; ?>"
                                            <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo $cat['arabic_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i> تصفية
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($groupedMedications)): ?>
        <?php foreach ($groupedMedications as $firstLetter => $medGroup): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <span class="me-2"><?php echo $firstLetter; ?></span>
                        <?php if (isset($arabicLetters[$firstLetter])): ?>
                            <small>(<?php echo $arabicLetters[$firstLetter]; ?>)</small>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>الاسم التجاري</th>
                                    <th>المادة الفعالة</th>
                                    <th>الشركة المنتجة</th>
                                    <th>التصنيف</th>
                                    <th>السعر</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medGroup as $med): ?>
                                    <tr>
                                        <td>
                                            <a href="medication.php?id=<?php echo $med['id']; ?>" class="text-decoration-none">
                                                <?php echo $med['trade_name']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo $med['scientific_name']; ?></td>
                                        <td><?php echo $med['company']; ?></td>
                                        <td><?php echo $med['category']; ?></td>
                                        <td>
                                            <strong class="text-primary"><?php echo number_format($med['current_price'], 2); ?> ج.م</strong>
                                            <?php if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']): ?>
                                                <br>
                                                <small class="text-muted text-decoration-line-through">
                                                    <?php echo number_format($med['old_price'], 2); ?> ج.م
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                التفاصيل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif ($letter || $categoryId): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> 
            لم يتم العثور على أدوية تطابق معايير البحث. يرجى تجربة حرف آخر أو تصنيف مختلف.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> 
            يرجى اختيار أحد الحروف أو التصنيفات لعرض الأدوية.
        </div>
        
        <!-- معلومات عن الدليل الدوائي -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-book-medical me-2"></i> عن الدليل الدوائي</h5>
            </div>
            <div class="card-body">
                <p>يوفر الدليل الدوائي معلومات شاملة عن الأدوية المتاحة، بما في ذلك:</p>
                <ul>
                    <li>الاسم التجاري والمادة الفعالة للدواء</li>
                    <li>الشركة المصنعة والتصنيف العلاجي</li>
                    <li>دواعي الاستعمال والجرعات الموصى بها</li>
                    <li>الآثار الجانبية المحتملة والتحذيرات</li>
                    <li>التفاعلات الدوائية وموانع الاستعمال</li>
                    <li>أسعار الأدوية والبدائل المتاحة</li>
                </ul>
                <p>يمكنك البحث عن أي دواء باستخدام الحرف الأول من اسمه أو تصفية النتائج حسب التصنيف العلاجي.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>