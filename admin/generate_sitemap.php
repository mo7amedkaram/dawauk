<?php
// admin/generate_sitemap.php - معالج توليد ملفات sitemap و robots

// تضمين ملف قاعدة البيانات
require_once '../config/database.php';

// التأكد من أن الطلب هو طلب Ajax
header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    
    // تحديد العنوان URL الأساسي للموقع
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host;
    
    // توليد ملف Sitemap
    $sitemapResult = generateSitemap($db, $baseUrl);
    
    // توليد ملف Robots.txt إذا لم يكن موجوداً
    $robotsResult = generateRobotsFile($baseUrl);
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'message' => 'تم توليد ملفات Sitemap و Robots بنجاح!'
    ]);
    
} catch (Exception $e) {
    // إرجاع رسالة الخطأ
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء توليد الملفات: ' . $e->getMessage()
    ]);
}

/**
 * توليد ملف Sitemap.xml
 * 
 * @param object $db كائن قاعدة البيانات
 * @param string $baseUrl العنوان URL الأساسي للموقع
 * @return bool نجاح العملية
 */
function generateSitemap($db, $baseUrl) {
    $sitemapPath = '../sitemap.xml';
    
    // بداية ملف XML
    $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $sitemapContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    
    // إضافة الصفحات الثابتة
    $staticPages = [
        '/index.php' => ['priority' => '1.0', 'changefreq' => 'daily'],
        '/search.php' => ['priority' => '0.8', 'changefreq' => 'daily'],
        '/search_ui.php' => ['priority' => '0.8', 'changefreq' => 'daily'],
        '/compare.php' => ['priority' => '0.7', 'changefreq' => 'weekly'],
        '/guide.php' => ['priority' => '0.8', 'changefreq' => 'weekly'],
        '/categories.php' => ['priority' => '0.8', 'changefreq' => 'weekly'],
        '/stats.php' => ['priority' => '0.5', 'changefreq' => 'weekly']
    ];
    
    foreach ($staticPages as $page => $settings) {
        $url = $baseUrl . $page;
        $sitemapContent .= '  <url>' . PHP_EOL;
        $sitemapContent .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
        $sitemapContent .= '    <changefreq>' . $settings['changefreq'] . '</changefreq>' . PHP_EOL;
        $sitemapContent .= '    <priority>' . $settings['priority'] . '</priority>' . PHP_EOL;
        $sitemapContent .= '  </url>' . PHP_EOL;
    }
    
    // إضافة صفحات الأدوية
    $sql = "SELECT id, trade_name, price_updated_date, visit_count FROM medications ORDER BY visit_count DESC";
    $medications = $db->fetchAll($sql);
    
    foreach ($medications as $medication) {
        $url = $baseUrl . '/medication.php?id=' . $medication['id'];
        
        // حساب الأولوية استنادًا إلى عدد الزيارات
        $priority = calculatePriority($medication['visit_count']);
        
        // تحديد تكرار الزحف استنادًا إلى تاريخ تحديث السعر
        $changefreq = calculateChangeFreq($medication['price_updated_date']);
        
        // تحديد تاريخ آخر تحديث
        $lastmod = !empty($medication['price_updated_date']) ? date('Y-m-d', strtotime($medication['price_updated_date'])) : date('Y-m-d');
        
        $sitemapContent .= '  <url>' . PHP_EOL;
        $sitemapContent .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
        $sitemapContent .= '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        $sitemapContent .= '    <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
        $sitemapContent .= '    <priority>' . $priority . '</priority>' . PHP_EOL;
        $sitemapContent .= '  </url>' . PHP_EOL;
    }
    
    // إضافة صفحات التصنيفات
    $sql = "SELECT id, name, arabic_name FROM categories ORDER BY name";
    $categories = $db->fetchAll($sql);
    
    foreach ($categories as $category) {
        $url = $baseUrl . '/categories.php?id=' . $category['id'];
        $sitemapContent .= '  <url>' . PHP_EOL;
        $sitemapContent .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
        $sitemapContent .= '    <changefreq>weekly</changefreq>' . PHP_EOL;
        $sitemapContent .= '    <priority>0.7</priority>' . PHP_EOL;
        $sitemapContent .= '  </url>' . PHP_EOL;
    }
    
    // إغلاق ملف XML
    $sitemapContent .= '</urlset>';
    
    // كتابة الملف
    $result = file_put_contents($sitemapPath, $sitemapContent);
    
    return ($result !== false);
}

/**
 * توليد ملف Robots.txt
 * 
 * @param string $baseUrl العنوان URL الأساسي للموقع
 * @return bool نجاح العملية
 */
function generateRobotsFile($baseUrl) {
    $robotsPath = '../robots.txt';
    
    // إذا كان الملف موجوداً، لا تقم بإعادة إنشائه
    if (file_exists($robotsPath)) {
        return true;
    }
    
    // محتوى ملف robots.txt
    $robotsContent = "User-agent: *\n";
    $robotsContent .= "Allow: /\n";
    $robotsContent .= "Disallow: /admin/\n";
    $robotsContent .= "Disallow: /config/\n";
    $robotsContent .= "Disallow: /includes/\n";
    $robotsContent .= "Disallow: /assets/js/\n";
    $robotsContent .= "Disallow: /assets/css/\n";
    $robotsContent .= "Disallow: /*.php$\n";
    $robotsContent .= "Allow: /index.php\n";
    $robotsContent .= "Allow: /search.php\n";
    $robotsContent .= "Allow: /medication.php\n";
    $robotsContent .= "Allow: /compare.php\n";
    $robotsContent .= "Allow: /categories.php\n";
    $robotsContent .= "Allow: /guide.php\n";
    $robotsContent .= "Allow: /search_ui.php\n";
    $robotsContent .= "\n";
    $robotsContent .= "# Allow Google Image bot to index images\n";
    $robotsContent .= "User-agent: Googlebot-Image\n";
    $robotsContent .= "Allow: /assets/images/\n";
    $robotsContent .= "Allow: /*.jpg$\n";
    $robotsContent .= "Allow: /*.jpeg$\n";
    $robotsContent .= "Allow: /*.png$\n";
    $robotsContent .= "Allow: /*.gif$\n";
    $robotsContent .= "\n";
    $robotsContent .= "# Allow GPT bot (for ChatGPT plugins)\n";
    $robotsContent .= "User-agent: GPTBot\n";
    $robotsContent .= "Allow: /\n";
    $robotsContent .= "\n";
    $robotsContent .= "# Sitemap location\n";
    $robotsContent .= "Sitemap: " . $baseUrl . "/sitemap.xml";
    
    // كتابة الملف
    $result = file_put_contents($robotsPath, $robotsContent);
    
    return ($result !== false);
}

/**
 * حساب أولوية الصفحة استنادًا إلى عدد الزيارات
 * 
 * @param int $visitCount عدد الزيارات
 * @return string الأولوية (0.0 إلى 1.0)
 */
function calculatePriority($visitCount) {
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
function calculateChangeFreq($updateDate) {
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