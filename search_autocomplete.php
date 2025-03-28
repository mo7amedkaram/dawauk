<?php
// search_autocomplete.php - التأكد من تعامله مع الأخطاء بشكل صحيح
require_once 'config/database.php';

// تضمين معالجة الأخطاء
try {
    // تأكد من استلام معيار البحث
    if (!isset($_GET['q']) || empty($_GET['q'])) {
        // إرجاع مصفوفة فارغة إذا لم يتم توفير معيار البحث
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $db = Database::getInstance();
    $query = trim($_GET['q']);
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

    // تحقق من وجود علامة النجمة (*)
    if (strpos($query, '*') !== false) {
        // استبدال علامة النجمة بأي حرف
        $searchPattern = str_replace('*', '%', $query);
    } else {
        // البحث العادي
        $searchPattern = '%' . $query . '%';
    }

    // بناء شرط البحث حسب الفلتر المحدد
    $whereClauses = [];
    $params = [];

    switch ($filter) {
        case 'trade_name':
            $whereClauses[] = "trade_name LIKE ?";
            $params[] = $searchPattern;
            break;
        case 'scientific_name':
            $whereClauses[] = "scientific_name LIKE ?";
            $params[] = $searchPattern;
            break;
        case 'company':
            $whereClauses[] = "company LIKE ?";
            $params[] = $searchPattern;
            break;
        default: // all
            $whereClauses[] = "(trade_name LIKE ? OR scientific_name LIKE ? OR arabic_name LIKE ?)";
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            break;
    }

    // إنشاء استعلام البحث
    $whereClause = implode(" AND ", $whereClauses);
    $baseFields = "id, trade_name, scientific_name, company, current_price, old_price, units_per_package, image_url";

    $sql = "SELECT $baseFields FROM medications WHERE $whereClause LIMIT 10";

    $results = $db->fetchAll($sql, $params);

    // إرجاع النتائج بتنسيق JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($results);
    exit;
} catch (Exception $e) {
    // إرجاع رسالة خطأ بتنسيق JSON
    header('Content-Type: application/json');
    http_response_code(500); // هذا يعني "Internal Server Error"
    echo json_encode(['error' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()]);
    exit;
}
?>