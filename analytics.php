<?php
/**
 * analytics.php - ملف إدارة Google Analytics
 * يقوم هذا الملف بإدارة تتبع الإحصائيات باستخدام Google Analytics 4
 */

class Analytics {
    private $measurementId;  // معرف قياس Google Analytics 4 (G-XXXXXXXX)
    private $debug;          // وضع التصحيح لاختبار التتبع
    
    /**
     * إنشاء كائن Analytics جديد
     * 
     * @param string $measurementId معرف قياس Google Analytics 4
     * @param bool $debug وضع التصحيح
     */
    public function __construct($measurementId, $debug = false) {
        $this->measurementId = $measurementId;
        $this->debug = $debug;
    }
    
    /**
     * الحصول على شفرة التتبع لتضمينها في رأس الصفحة
     * 
     * @return string شفرة JavaScript لتتبع Google Analytics
     */
    public function getTrackingCode() {
        $debugMode = $this->debug ? ", { debug_mode: true }" : "";
        
        $code = <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$this->measurementId}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$this->measurementId}'{$debugMode});
  
  // تهيئة وظائف تتبع مخصصة
  document.addEventListener('DOMContentLoaded', function() {
    // تتبع النقرات على أزرار المقارنة
    document.querySelectorAll('.btn-compare').forEach(function(btn) {
      btn.addEventListener('click', function() {
        gtag('event', 'compare_click', {
          'event_category': 'engagement',
          'event_label': this.getAttribute('data-med-name') || 'unknown'
        });
      });
    });
    
    // تتبع عمليات البحث
    const searchForms = document.querySelectorAll('form[action*="search"]');
    searchForms.forEach(function(form) {
      form.addEventListener('submit', function(e) {
        const searchInput = this.querySelector('input[name="q"]');
        if (searchInput && searchInput.value.trim()) {
          gtag('event', 'search', {
            'search_term': searchInput.value.trim()
          });
        }
      });
    });
    
    // تتبع مشاهدة صفحات تفاصيل الدواء
    if (window.location.pathname.includes('medication.php')) {
      const medName = document.querySelector('h2.card-title')?.textContent || 'unknown';
      gtag('event', 'view_item', {
        'event_category': 'content',
        'event_label': medName,
        'items': [{
          'id': new URLSearchParams(window.location.search).get('id'),
          'name': medName
        }]
      });
    }
  });
</script>
HTML;
        
        return $code;
    }
    
    /**
     * إرسال حدث مخصص إلى Google Analytics
     * 
     * @param string $eventName اسم الحدث
     * @param array $params معلمات الحدث
     * @return string شفرة JavaScript لإرسال الحدث
     */
    public function sendEvent($eventName, $params = []) {
        $paramsJson = !empty($params) ? json_encode($params) : '{}';
        
        $code = <<<HTML
<script>
  gtag('event', '$eventName', $paramsJson);
</script>
HTML;
        
        return $code;
    }
    
    /**
     * تعيين معلومات المستخدم لتحليلات أفضل
     * 
     * @param string $userId معرف المستخدم (اختياري)
     * @return string شفرة JavaScript لتعيين معلومات المستخدم
     */
    public function setUserProperties($userId = null) {
        $code = "<script>";
        
        if ($userId) {
            $code .= "gtag('set', 'user_id', '$userId');\n";
        }
        
        $code .= "gtag('set', 'user_properties', {\n";
        $code .= "  site_language: 'ar',\n";
        $code .= "  site_theme: 'light',\n";
        $code .= "  site_version: '1.0'\n";
        $code .= "});\n";
        $code .= "</script>";
        
        return $code;
    }
}

/**
 * فئة تتبع التحويلات للصفحة
 */
class ConversionTracker {
    private $analytics;
    
    /**
     * إنشاء كائن لتتبع التحويلات
     * 
     * @param Analytics $analytics كائن التحليلات
     */
    public function __construct($analytics) {
        $this->analytics = $analytics;
    }
    
    /**
     * تتبع المقارنة بين الأدوية
     * 
     * @param array $medications قائمة الأدوية المقارنة
     * @return string شفرة JavaScript للتتبع
     */
    public function trackComparison($medications) {
        $medicationIds = [];
        $medicationNames = [];
        
        foreach ($medications as $med) {
            $medicationIds[] = $med['id'];
            $medicationNames[] = $med['trade_name'];
        }
        
        $params = [
            'event_category' => 'conversion',
            'event_label' => implode(', ', $medicationNames),
            'medication_ids' => implode(',', $medicationIds),
            'medication_count' => count($medications)
        ];
        
        return $this->analytics->sendEvent('compare_medications', $params);
    }
    
    /**
     * تتبع عملية بحث
     * 
     * @param string $query استعلام البحث
     * @param int $resultsCount عدد النتائج
     * @return string شفرة JavaScript للتتبع
     */
    public function trackSearch($query, $resultsCount) {
        $params = [
            'search_term' => $query,
            'results_count' => $resultsCount
        ];
        
        return $this->analytics->sendEvent('search', $params);
    }
    
    /**
     * تتبع مشاهدة تفاصيل الدواء
     * 
     * @param array $medication بيانات الدواء
     * @return string شفرة JavaScript للتتبع
     */
    public function trackMedicationView($medication) {
        $params = [
            'event_category' => 'content',
            'event_label' => $medication['trade_name'],
            'items' => [
                [
                    'id' => $medication['id'],
                    'name' => $medication['trade_name'],
                    'price' => $medication['current_price'],
                    'brand' => $medication['company'],
                    'category' => $medication['category'] ?? 'Unknown'
                ]
            ]
        ];
        
        return $this->analytics->sendEvent('view_item', $params);
    }
    
    /**
     * تتبع النقر على بديل دواء
     * 
     * @param int $originalId معرف الدواء الأصلي
     * @param int $alternativeId معرف الدواء البديل
     * @param string $originalName اسم الدواء الأصلي
     * @param string $alternativeName اسم الدواء البديل
     * @return string شفرة JavaScript للتتبع
     */
    public function trackAlternativeClick($originalId, $alternativeId, $originalName, $alternativeName) {
        $params = [
            'event_category' => 'engagement',
            'event_label' => "من $originalName إلى $alternativeName",
            'original_id' => $originalId,
            'alternative_id' => $alternativeId
        ];
        
        return $this->analytics->sendEvent('alternative_click', $params);
    }
}

// مثال على استخدام الفئة:
// $analytics = new Analytics('G-XXXXXXXXXX');
// echo $analytics->getTrackingCode();
// 
// $conversionTracker = new ConversionTracker($analytics);
// echo $conversionTracker->trackSearch('panadol', 15);
?>