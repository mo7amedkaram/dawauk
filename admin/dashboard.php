<?php
// admin/dashboard.php - لوحة التحكم الرئيسية

// الاعدادات الافتراضية
$admin_username = "admin";
$admin_password = "dawa@2025";

// تضمين ملف قاعدة البيانات
require_once '../config/database.php';

// إعداد معلومات الصفحة
$pageTitle = 'لوحة التحكم';
$db = Database::getInstance();

// جلب الإحصائيات
// إجمالي عدد الأدوية
$totalMeds = $db->fetchOne("SELECT COUNT(*) as total FROM medications")['total'];
// إجمالي الزيارات
$totalVisits = $db->fetchOne("SELECT SUM(visit_count) as total FROM medications")['total'] ?? 0;
// إجمالي عمليات البحث
// إصلاح الخطأ: تغيير SUM(count) إلى SUM(search_count)
$totalSearches = $db->fetchOne("SELECT SUM(search_count) as total FROM search_stats")['total'] ?? 0;
// متوسط السعر
$avgPrice = $db->fetchOne("SELECT AVG(current_price) as avg FROM medications")['avg'] ?? 0;

// الأدوية الأكثر زيارة
$mostVisitedMeds = $db->fetchAll("SELECT id, trade_name, scientific_name, company, current_price, visit_count FROM medications ORDER BY visit_count DESC LIMIT 10");

// الأدوية المضافة مؤخراً
$recentMeds = $db->fetchAll("SELECT id, trade_name, scientific_name, company, current_price, price_updated_date FROM medications ORDER BY id DESC LIMIT 10");

// مصطلحات البحث الأكثر شيوعًا
$topSearchTerms = $db->fetchAll("SELECT search_term, search_count, last_search_date FROM search_stats ORDER BY search_count DESC LIMIT 10");

// الزيارات خلال الأسبوع الماضي
$lastWeekVisits = $db->fetchAll("
    SELECT DATE(visit_date) as date, COUNT(*) as count 
    FROM medication_visits 
    WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    GROUP BY DATE(visit_date) 
    ORDER BY date
");

// تحويل البيانات لتنسيق مناسب للرسم البياني
$visitDates = [];
$visitCounts = [];
foreach ($lastWeekVisits as $visit) {
    $visitDates[] = $visit['date'];
    $visitCounts[] = $visit['count'];
}

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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .card-stats {
            border-left: 4px solid;
        }
        .card-stats.border-primary {
            border-left-color: #4e73df;
        }
        .card-stats.border-success {
            border-left-color: #1cc88a;
        }
        .card-stats.border-info {
            border-left-color: #36b9cc;
        }
        .card-stats.border-warning {
            border-left-color: #f6c23e;
        }
        .admin-content {
            min-height: 100vh;
            padding: 1.5rem;
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="seo.php">
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
                    <h1 class="h3 mb-0 text-gray-800">لوحة التحكم</h1>
                    <div>
                        <button class="btn btn-primary" onclick="generateSitemap()">
                            <i class="fas fa-sitemap me-1"></i> توليد Sitemap
                        </button>
                        <a href="../" target="_blank" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-external-link-alt me-1"></i> عرض الموقع
                        </a>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow card-stats border-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            إجمالي الأدوية</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalMeds); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-pills fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow card-stats border-success h-100 py-2">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            إجمالي الزيارات</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalVisits); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-eye fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow card-stats border-info h-100 py-2">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            عمليات البحث</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalSearches); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-search fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-0 shadow card-stats border-warning h-100 py-2">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            متوسط السعر</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($avgPrice, 2); ?> ج.م</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Visits Chart -->
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">الزيارات خلال الأسبوع الماضي</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="visitsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Terms Pie Chart -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">مصطلحات البحث الشائعة</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie">
                                    <canvas id="searchTermsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Row -->
                <div class="row">
                    <!-- Most Visited Medications -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">الأدوية الأكثر زيارة</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>الاسم التجاري</th>
                                                <th>الشركة</th>
                                                <th>السعر</th>
                                                <th>الزيارات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mostVisitedMeds as $med): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../medication.php?id=<?php echo $med['id']; ?>" target="_blank">
                                                            <?php echo $med['trade_name']; ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo $med['company']; ?></td>
                                                    <td><?php echo number_format($med['current_price'], 2); ?> ج.م</td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo number_format($med['visit_count']); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Medications -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">الأدوية المضافة مؤخراً</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>الاسم التجاري</th>
                                                <th>المادة الفعالة</th>
                                                <th>السعر</th>
                                                <th>التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentMeds as $med): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../medication.php?id=<?php echo $med['id']; ?>" target="_blank">
                                                            <?php echo $med['trade_name']; ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo $med['scientific_name']; ?></td>
                                                    <td><?php echo number_format($med['current_price'], 2); ?> ج.م</td>
                                                    <td>
                                                        <?php echo !empty($med['price_updated_date']) ? date('Y-m-d', strtotime($med['price_updated_date'])) : '-'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SEO Status -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">حالة تحسين محركات البحث (SEO)</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h5>ملفات SEO</h5>
                                    <ul class="list-group">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-file-alt me-2"></i> Sitemap.xml</span>
                                            <?php if (file_exists('../sitemap.xml')): ?>
                                                <span class="badge bg-success">متوفر</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">غير متوفر</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span><i class="fas fa-file-alt me-2"></i> Robots.txt</span>
                                            <?php if (file_exists('../robots.txt')): ?>
                                                <span class="badge bg-success">متوفر</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">غير متوفر</span>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h5>فحص السيو</h5>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        آخر تحديث لملف Sitemap: 
                                        <?php echo file_exists('../sitemap.xml') ? date("Y-m-d H:i", filemtime('../sitemap.xml')) : 'غير متوفر'; ?>
                                    </div>
                                    <button class="btn btn-primary" onclick="generateSitemap()">
                                        <i class="fas fa-sync me-1"></i> تحديث Sitemap الآن
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart Initialization -->
    <script>
        // Visits Chart
        const visitsCtx = document.getElementById('visitsChart').getContext('2d');
        new Chart(visitsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($visitDates); ?>,
                datasets: [{
                    label: 'الزيارات',
                    data: <?php echo json_encode($visitCounts); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 2,
                    lineTension: 0.3,
                    pointBackgroundColor: '#4e73df',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointHitRadius: 10,
                    pointBorderWidth: 2
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Search Terms Chart
        const searchTermsCtx = document.getElementById('searchTermsChart').getContext('2d');
        new Chart(searchTermsCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    $terms = array_slice($topSearchTerms, 0, 5);
                    foreach ($terms as $term) {
                        echo "'" . $term['search_term'] . "', ";
                    }
                    ?>
                    'أخرى'
                ],
                datasets: [{
                    data: [
                        <?php 
                        foreach ($terms as $term) {
                            echo $term['search_count'] . ", ";
                        }
                        // Calculate "Others" count
                        $topFiveCount = 0;
                        foreach ($terms as $term) {
                            $topFiveCount += $term['search_count'];
                        }
                        $othersCount = $totalSearches - $topFiveCount;
                        echo max(0, $othersCount);
                        ?>
                    ],
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#60616f'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                cutout: '70%'
            }
        });
        
        // Function to generate sitemap
        function generateSitemap() {
            fetch('generate_sitemap.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('تم توليد ملف Sitemap بنجاح!');
                    } else {
                        alert('حدث خطأ أثناء توليد ملف Sitemap: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء العملية');
                });
        }
    </script>
</body>
</html>