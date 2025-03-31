<?php
// search_ajax.php - البحث الفوري بتقنية AJAX

// استيراد الملفات اللازمة
require_once 'config/database.php';
require_once 'search_function.php';

// التأكد من استدعاء الملف بطريقة AJAX
header('Content-Type: application/json');

try {
    // التحقق من وجود معلمات البحث
    if (!isset($_GET['q']) || empty($_GET['q'])) {
        // إرجاع مصفوفة فارغة إذا لم يتم توفير استعلام البحث
        echo json_encode([
            'success' => false,
            'message' => 'يرجى إدخال استعلام البحث',
            'results' => []
        ]);
        exit;
    }

    $db = Database::getInstance();
    $query = trim($_GET['q']);
    
    // الحصول على طريقة البحث المطلوبة
    $method = isset($_GET['method']) ? $_GET['method'] : 'trade';
    
    // التحقق من صحة طريقة البحث
    $validMethods = ['trade', 'trade_any', 'google', 'price'];
    if (!in_array($method, $validMethods)) {
        $method = 'trade';
    }
    
    // الحد الأقصى لعدد النتائج
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // تنفيذ البحث
    $searchData = advancedSearch($db, $query, $method, [], 1, $limit);
    
    // استخراج نتائج البحث
    $results = $searchData['results'] ?? [];
    $totalResults = $searchData['total'] ?? 0;
    
    // تنسيق النتائج لإرجاعها بصيغة JSON
    $formattedResults = [];
    foreach ($results as $med) {
        // حساب الخصم إذا وجد
        $discount = null;
        if ($med['old_price'] > 0 && $med['old_price'] > $med['current_price']) {
            $discount = round(100 - ($med['current_price'] / $med['old_price'] * 100));
        }
        
        $formattedResults[] = [
            'id' => $med['id'],
            'trade_name' => $med['trade_name'],
            'scientific_name' => $med['scientific_name'],
            'company' => $med['company'],
            'category' => $med['category'],
            'current_price' => $med['current_price'],
            'old_price' => $med['old_price'],
            'units_per_package' => $med['units_per_package'] ?? 1,
            'form' => $med['form'] ?? '',
            'strength' => $med['strength'] ?? '',
            'image_url' => $med['image_url'] ?? '',
            'discount' => $discount,
            'match_type' => 'trade_name' // نوع المطابقة الافتراضي
        ];
    }
    
    // إرجاع النتائج كـ JSON
    echo json_encode([
        'success' => true,
        'total' => $totalResults,
        'query' => $query,
        'method' => $method,
        'results' => $formattedResults
    ]);
    
} catch (Exception $e) {
    // إرجاع رسالة خطأ بتنسيق JSON
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage(),
        'results' => []
    ]);
}
?>