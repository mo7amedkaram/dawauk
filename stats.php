<?php
// stats.php - صفحة إحصائيات الأدوية
require_once 'config/database.php';
require_once 'includes/stats_tracking.php';

// إعداد معلومات الصفحة
$pageTitle = 'إحصائيات الأدوية';
$currentPage = 'stats';

$db = Database::getInstance();
$statsTracker = new StatsTracker($db);

// الحصول على الأدوية الأكثر زيارة
$mostVisited = $statsTracker->getMostVisitedMedications(10);

// الحصول على الأدوية الأكثر بحثاً
$mostSearched = $statsTracker->getMostSearchedMedications(10);

// الحصول على مصطلحات البحث الأكثر شيوعاً
$topSearchTerms = $statsTracker->getTopSearchTerms(10);

// تضمين ملف الهيدر
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">إحصائيات الأدوية في مصر</h1>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-eye me-2"></i> الأدوية الأكثر زيارة</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($mostVisited)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الدواء</th>
                                        <th>الشركة</th>
                                        <th>عدد الزيارات</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mostVisited as $index => $med): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <a href="medication.php?id=<?php echo $med['id']; ?>" class="text-decoration-none">
                                                    <strong><?php echo $med['trade_name']; ?></strong>
                                                    <div class="small text-muted"><?php echo $med['scientific_name']; ?></div>
                                                </a>
                                            </td>
                                            <td><?php echo $med['company']; ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo number_format($med['visit_count']); ?></span>
                                            </td>
                                            <td>
                                                <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> لا تتوفر بيانات كافية حتى الآن.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-search me-2"></i> الأدوية الأكثر بحثاً</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($mostSearched)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الدواء</th>
                                        <th>الشركة</th>
                                        <th>عدد عمليات البحث</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mostSearched as $index => $med): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <a href="medication.php?id=<?php echo $med['id']; ?>" class="text-decoration-none">
                                                    <strong><?php echo $med['trade_name']; ?></strong>
                                                    <div class="small text-muted"><?php echo $med['scientific_name']; ?></div>
                                                </a>
                                            </td>
                                            <td><?php echo $med['company']; ?></td>
                                            <td>
                                                <span class="badge bg-success"><?php echo number_format($med['search_count']); ?></span>
                                            </td>
                                            <td>
                                                <a href="medication.php?id=<?php echo $med['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> لا تتوفر بيانات كافية حتى الآن.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8 offset-lg-2 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-keyboard me-2"></i> مصطلحات البحث الأكثر شيوعاً</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($topSearchTerms)): ?>
                        <div class="search-terms-cloud">
                            <?php foreach ($topSearchTerms as $term): ?>
                                <a href="search.php?q=<?php echo urlencode($term['search_term']); ?>" class="search-term-tag" style="font-size: <?php echo min(100, max(80, 80 + ($term['search_count'] * 5))); ?>%;">
                                    <?php echo $term['search_term']; ?>
                                    <span class="search-count"><?php echo $term['search_count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> لا تتوفر بيانات كافية حتى الآن.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> إحصائيات عامة</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- إجمالي عدد الأدوية -->
                        <?php 
                        $totalMeds = $db->fetchOne("SELECT COUNT(*) as total FROM medications")['total'];
                        $totalVisits = $db->fetchOne("SELECT SUM(visit_count) as total FROM medications")['total'] ?? 0;
                        $totalSearches = $db->fetchOne("SELECT SUM(search_count) as total FROM medications")['total'] ?? 0;
                        $avgPrice = $db->fetchOne("SELECT AVG(current_price) as avg FROM medications")['avg'] ?? 0;
                        ?>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card text-center p-3 border rounded">
                                <div class="stats-icon"><i class="fas fa-pills fa-2x text-primary"></i></div>
                                <div class="stats-number"><?php echo number_format($totalMeds); ?></div>
                                <div class="stats-title">إجمالي عدد الأدوية</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card text-center p-3 border rounded">
                                <div class="stats-icon"><i class="fas fa-eye fa-2x text-success"></i></div>
                                <div class="stats-number"><?php echo number_format($totalVisits); ?></div>
                                <div class="stats-title">إجمالي الزيارات</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card text-center p-3 border rounded">
                                <div class="stats-icon"><i class="fas fa-search fa-2x text-info"></i></div>
                                <div class="stats-number"><?php echo number_format($totalSearches); ?></div>
                                <div class="stats-title">إجمالي عمليات البحث</div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="stats-card text-center p-3 border rounded">
                                <div class="stats-icon"><i class="fas fa-tag fa-2x text-warning"></i></div>
                                <div class="stats-number"><?php echo number_format($avgPrice, 2); ?> ج.م</div>
                                <div class="stats-title">متوسط سعر الدواء</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- يمكن إضافة رسوم بيانية هنا -->
                    <div class="mt-4">
                        <h5 class="mb-3">تحليل الزيارات</h5>
                        <div id="visitsChartContainer" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- تنسيقات خاصة بصفحة الإحصائيات -->
<style>
.search-terms-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    padding: 20px 0;
}

.search-term-tag {
    background-color: #f0f8ff;
    border: 1px solid #d7e9fc;
    border-radius: 20px;
    padding: 5px 15px;
    text-decoration: none;
    color: #0d6efd;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
}

.search-term-tag:hover {
    background-color: #0d6efd;
    color: white;
}

.search-count {
    background-color: rgba(13, 110, 253, 0.2);
    color: #0d6efd;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 5px;
    margin-left: 5px;
}

.search-term-tag:hover .search-count {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
}

.stats-card {
    background-color: white;
    border-radius: 10px;
    transition: all 0.3s;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.stats-icon {
    margin-bottom: 10px;
}

.stats-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.stats-title {
    color: #6c757d;
    font-size: 0.9rem;
}
</style>

<!-- تضمين مكتبة Chart.js للرسوم البيانية -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // رسم بياني للزيارات
    const ctx = document.getElementById('visitsChartContainer').getContext('2d');
    
    // بيانات افتراضية للرسم البياني
    // في التطبيق الحقيقي، يمكن جلب هذه البيانات من الخادم
    const labels = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'];
    const visitData = [1200, 1900, 3000, 5000, 2000, 3000];
    const searchData = [900, 1200, 1700, 3900, 1800, 2500];
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'الزيارات',
                    data: visitData,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: 'عمليات البحث',
                    data: searchData,
                    backgroundColor: 'rgba(25, 135, 84, 0.2)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php
// تضمين ملف الفوتر
include 'includes/footer.php';
?>