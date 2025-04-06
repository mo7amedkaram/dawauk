<?php
// api/api_utils.php - وظائف مساعدة للـ API

/**
 * إرسال استجابة بتنسيق JSON
 * 
 * @param mixed $data البيانات المراد إرسالها
 * @param int $statusCode رمز الحالة HTTP
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * تنظيف وتحقق من معلمات الطلب
 * 
 * @param array $requestData بيانات الطلب
 * @param array $requiredParams المعلمات المطلوبة
 * @param array $optionalParams المعلمات الاختيارية مع قيمها الافتراضية
 * @return array البيانات المنظفة
 */
function sanitizeRequestParams($requestData, $requiredParams = [], $optionalParams = []) {
    $cleanData = [];
    
    // التحقق من المعلمات المطلوبة
    foreach ($requiredParams as $param) {
        if (!isset($requestData[$param]) || $requestData[$param] === '') {
            sendResponse(['error' => "Missing required parameter: $param"], 400);
        }
        $cleanData[$param] = $requestData[$param];
    }
    
    // إضافة المعلمات الاختيارية مع القيم الافتراضية
    foreach ($optionalParams as $param => $defaultValue) {
        $cleanData[$param] = isset($requestData[$param]) ? $requestData[$param] : $defaultValue;
    }
    
    return $cleanData;
}

/**
 * التحقق من صحة طريقة الطلب
 * 
 * @param string $method طريقة الطلب المتوقعة (GET, POST, PUT, DELETE)
 */
function validateRequestMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendResponse(['error' => "Method not allowed. Expected $method"], 405);
    }
}

/**
 * إضافة معلومات تتبع للدواء
 * 
 * @param array $medication بيانات الدواء
 * @return array بيانات الدواء مع معلومات التتبع
 */
function enrichMedicationWithTracking($medication) {
    // إضافة روابط إلى البدائل المشابهة والشركات المصنعة، إلخ.
    $medication['links'] = [
        'self' => "/api/medications/{$medication['id']}",
        'alternatives' => "/api/medications/search?scientific_name=" . urlencode($medication['scientific_name']),
        'company' => "/api/medications/search?company=" . urlencode($medication['company']),
        'category' => "/api/medications/search?category=" . urlencode($medication['category']),
        'compare' => "/api/medications/compare?ids={$medication['id']}"
    ];
    
    return $medication;
}

/**
 * معالجة الخطأ وإعادة رسالة خطأ مناسبة
 * 
 * @param Exception $e الاستثناء
 * @param int $statusCode رمز الحالة HTTP
 */
function handleError($e, $statusCode = 500) {
    $errorMessage = $e->getMessage();
    
    // تسجيل الخطأ في ملف السجل
    error_log("API Error: " . $errorMessage);
    
    // إرسال استجابة الخطأ
    sendResponse(['error' => $errorMessage], $statusCode);
}