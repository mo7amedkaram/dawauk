<?php
/**
 * sitemap_generator.php - مولد خريطة الموقع
 * يقوم هذا الملف بإنشاء ملف sitemap.xml للموقع لتحسين فهرسة محركات البحث
 */

require_once 'config/database.php';

class SitemapGenerator {
    private $db;
    private $baseUrl;
    private $sitemapPath;
    private $sitemapContent;
    
    /**
     * إنشاء كائن مولد خريطة الموقع
     * 
     * @param mixed $db كائن قاعدة البيانات
     * @param string $baseUrl العنوان URL الأساسي للموقع
     * @param string $sitemapPath مسار ملف خريطة الموقع
     */
    public function __construct($db, $baseUrl, $sitemapPath = 'sitemap.xml') {
        $this->db = $db;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->sitemapPath = $sitemapPath;
        
        // بداية ملف XML
        $this->sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $this->sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    }
    
    /**
     * تنفيذ عملية توليد ملف خريطة الموقع
     * 
     * @return bool نجاح العملية
     */
    public function generate() {
        try {
            // إضافة الصفحات الثابتة
            $this->addStaticPages();
            
            // إضافة صفحات الأدوية
            $this->addMedicationPages();
            
            // إضافة صفحات التصنيفات
            $this->addCategoryPages();
            
            // إغلاق ملف XML
            $this->sitemapContent .= '</urlset>';
            
            // كتابة الملف
            file_put_contents($this->sitemapPath, $this->sitemapContent);
            
            return true;
        } catch (Exception $e) {
            error_log("خطأ في توليد خريطة الموقع: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إضافة الصفحات الثابتة إلى خريطة الموقع
     */
    private function addStaticPages() {
        // الصفحة الرئيسية
        $this->addUrl($this->baseUrl . '/index.php', '1.0', 'daily');
        
        // صفحة البحث
        $this->addUrl($this->baseUrl . '/search.php', '0.8', 'daily');
        
        // صفحة البحث المتطور
        $this->addUrl($this->baseUrl . '/search_ui.php', '0.8', 'daily');
        
        // صفحة المقارنة
        $this->addUrl($this->baseUrl . '/compare.php', '0.7', 'weekly');
        
        // صفحة الدليل الدوائي
        $this->addUrl($this->baseUrl . '/guide.php', '0.8', 'weekly');
        
        // صفحة التصنيفات
        $this->addUrl($this->baseUrl . '/categories.php', '0.8', 'weekly');
        
        // صفحة الإحصائيات
        $this->addUrl($this->baseUrl . '/stats.php', '0.5', 'weekly');
    }
    
    /**
     * إضافة صفحات الأدوية إلى خريطة الموقع
     */
    private function addMedicationPages() {
        $sql = "SELECT id, trade_name, price_updated_date, visit_count FROM medications ORDER BY visit_count DESC";
        $medications = $this->db->fetchAll($sql);
        
        foreach ($medications as $medication) {
            $url = $this->baseUrl . '/medication.php?id=' . $medication['id'];
            
            // حساب الأولوية استنادًا إلى عدد الزيارات
            $priority = $this->calculatePriority($medication['visit_count']);
            
            // تحديد تكرار الزحف استنادًا إلى تاريخ تحديث السعر
            $changefreq = $this->calculateChangeFreq($medication['price_updated_date']);
            
            // تحديد تاريخ آخر تحديث
            $lastmod = !empty($medication['price_updated_date']) ? date('Y-m-d', strtotime($medication['price_updated_date'])) : null;
            
            $this->addUrl($url, $priority, $changefreq, $lastmod);
        }
    }
    
    /**
     * إضافة صفحات التصنيفات إلى خريطة الموقع
     */
    private function addCategoryPages() {
        $sql = "SELECT id, name, arabic_name FROM categories ORDER BY name";
        $categories = $this->db->fetchAll($sql);
        
        foreach ($categories as $category) {
            $url = $this->baseUrl . '/categories.php?id=' . $category['id'];
            $this->addUrl($url, '0.7', 'weekly');
        }
    }
    
    /**
     * إضافة عنوان URL إلى خريطة الموقع
     * 
     * @param string $url عنوان URL
     * @param string $priority الأولوية (0.0 إلى 1.0)
     * @param string $changefreq تكرار التغيير (always, hourly, daily, weekly, monthly, yearly, never)
     * @param string $lastmod تاريخ آخر تعديل (Y-m-d)
     */
    private function addUrl($url, $priority = '0.5', $changefreq = 'monthly', $lastmod = null) {
        $this->sitemapContent .= '  <url>' . PHP_EOL;
        $this->sitemapContent .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
        
        if ($lastmod) {
            $this->sitemapContent .= '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        }
        
        $this->sitemapContent .= '    <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
        $this->sitemapContent .= '    <priority>' . $priority . '</priority>' . PHP_EOL;
        $this->sitemapContent .= '  </url>' . PHP_EOL;
    }
    
    /**
     * حساب أولوية الصفحة استنادًا إلى عدد الزيارات
     * 
     * @param int $visitCount عدد الزيارات
     * @return string الأولوية (0.0 إلى 1.0)
     */
    private function calculatePriority($visitCount) {
        // زيادة الأولوية بناءً على عدد الزيارات
        if ($visitCount > 1000) {
            return '1.0';
        } elseif ($visitCount > 500) {
            return '0.9';
        } elseif ($visitCount > 100) {
            return '0.8';
        } elseif ($visitCount > 50) {
            return '0.7';
        } elseif ($visitCount > 10) {
            return '0.6';
        } else {
            return '0.5';
        }
    }
    
    /**
     * حساب تكرار التغيير استنادًا إلى تاريخ التحديث
     * 
     * @param string $updateDate تاريخ التحديث
     * @return string تكرار التغيير
     */
    private function calculateChangeFreq($updateDate) {
        if (empty($updateDate)) {
            return 'monthly';
        }
        
        $now = time();
        $updateTime = strtotime($updateDate);
        $daysDiff = floor(($now - $updateTime) / (60 * 60 * 24));
        
        if ($daysDiff <= 1) {
            return 'daily';
        } elseif ($daysDiff <= 7) {
            return 'weekly';
        } elseif ($daysDiff <= 30) {
            return 'monthly';
        } else {
            return 'yearly';
        }
    }
}

// تنفيذ توليد خريطة الموقع عند استدعاء هذا الملف مباشرة
if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    $db = Database::getInstance();
    
    // تحديد العنوان URL الأساسي للموقع
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host;
    
    $generator = new SitemapGenerator($db, $baseUrl);
    
    if ($generator->generate()) {
        echo "تم إنشاء ملف خريطة الموقع بنجاح!";
    } else {
        echo "حدث خطأ أثناء إنشاء ملف خريطة الموقع.";
    }
}