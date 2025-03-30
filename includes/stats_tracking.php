<?php
// includes/stats_tracking.php - ملف تتبع الإحصائيات

class StatsTracker {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * تسجيل زيارة لدواء
     * 
     * @param int $medicationId معرف الدواء
     * @return bool نجاح العملية
     */
    public function trackMedicationVisit($medicationId) {
        if (!is_numeric($medicationId)) return false;
        
        try {
            // تحديث عداد الزيارات في جدول الأدوية
            $this->db->query(
                "UPDATE medications SET visit_count = visit_count + 1 WHERE id = ?",
                [$medicationId]
            );
            
            // تسجيل تفاصيل الزيارة
            $visitData = [
                'medication_id' => $medicationId,
                'visit_date' => date('Y-m-d H:i:s'),
                'ip_address' => $this->getClientIP(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
                'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null
            ];
            
            // إدراج بيانات الزيارة
            $this->db->insert('medication_visits', $visitData);
            
            return true;
        } catch (Exception $e) {
            // يمكن تسجيل الخطأ هنا
            error_log("Error tracking medication visit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تسجيل عملية بحث
     * 
     * @param string $searchTerm مصطلح البحث
     * @return bool نجاح العملية
     */
    public function trackSearch($searchTerm) {
        if (empty($searchTerm)) return false;
        
        try {
            // تنظيف مصطلح البحث
            $searchTerm = trim($searchTerm);
            
            // التحقق من وجود سجل بحث سابق
            $existingSearch = $this->db->fetchOne(
                "SELECT id, search_count FROM search_stats WHERE search_term = ?",
                [$searchTerm]
            );
            
            if ($existingSearch) {
                // تحديث عداد البحث
                $this->db->query(
                    "UPDATE search_stats SET search_count = search_count + 1, last_search_date = NOW() WHERE id = ?",
                    [$existingSearch['id']]
                );
            } else {
                // إضافة سجل بحث جديد
                $this->db->insert('search_stats', [
                    'search_term' => $searchTerm,
                    'search_count' => 1,
                    'last_search_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error tracking search: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تسجيل عملية بحث عن دواء محدد
     * 
     * @param int $medicationId معرف الدواء
     * @return bool نجاح العملية
     */
    public function trackMedicationSearch($medicationId) {
        if (!is_numeric($medicationId)) return false;
        
        try {
            // تحديث عداد البحث في جدول الأدوية
            $this->db->query(
                "UPDATE medications SET search_count = search_count + 1 WHERE id = ?",
                [$medicationId]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error tracking medication search: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على الأدوية الأكثر زيارة
     * 
     * @param int $limit عدد النتائج
     * @return array الأدوية الأكثر زيارة
     */
    public function getMostVisitedMedications($limit = 10) {
        return $this->db->fetchAll(
            "SELECT id, trade_name, scientific_name, company, current_price, visit_count 
             FROM medications 
             WHERE visit_count > 0 
             ORDER BY visit_count DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * الحصول على الأدوية الأكثر بحثاً
     * 
     * @param int $limit عدد النتائج
     * @return array الأدوية الأكثر بحثاً
     */
    public function getMostSearchedMedications($limit = 10) {
        return $this->db->fetchAll(
            "SELECT id, trade_name, scientific_name, company, current_price, search_count 
             FROM medications 
             WHERE search_count > 0 
             ORDER BY search_count DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * الحصول على مصطلحات البحث الأكثر شيوعاً
     * 
     * @param int $limit عدد النتائج
     * @return array مصطلحات البحث الأكثر شيوعاً
     */
    public function getTopSearchTerms($limit = 10) {
        return $this->db->fetchAll(
            "SELECT search_term, search_count, last_search_date 
             FROM search_stats 
             ORDER BY search_count DESC 
             LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * الحصول على إحصائيات زيارات الأدوية حسب الفترة الزمنية
     * 
     * @param string $period الفترة الزمنية (day, week, month, year)
     * @param int $limit عدد النتائج
     * @return array إحصائيات الزيارات
     */
    public function getVisitStats($period = 'day', $limit = 10) {
        $dateFormat = '';
        $groupBy = '';
        
        switch ($period) {
            case 'day':
                $dateFormat = '%Y-%m-%d';
                $groupBy = 'DATE(visit_date)';
                break;
            case 'week':
                $dateFormat = '%Y-%u';
                $groupBy = 'YEARWEEK(visit_date)';
                break;
            case 'month':
                $dateFormat = '%Y-%m';
                $groupBy = 'YEAR(visit_date), MONTH(visit_date)';
                break;
            case 'year':
                $dateFormat = '%Y';
                $groupBy = 'YEAR(visit_date)';
                break;
            default:
                $dateFormat = '%Y-%m-%d';
                $groupBy = 'DATE(visit_date)';
        }
        
        return $this->db->fetchAll(
            "SELECT 
                DATE_FORMAT(visit_date, ?) AS period,
                COUNT(*) AS visit_count
             FROM medication_visits
             GROUP BY $groupBy
             ORDER BY MIN(visit_date) DESC
             LIMIT ?",
            [$dateFormat, $limit]
        );
    }
    
    /**
     * الحصول على عنوان IP للمستخدم
     * 
     * @return string عنوان IP
     */
    private function getClientIP() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }
}