<?php
/**
 * seo.php - ملف تحسين محركات البحث (SEO)
 */

class SEO {
    private $title;
    private $description;
    private $keywords;
    private $canonicalUrl;
    private $ogType = 'website';
    private $ogImage;
    private $twitterCard = 'summary_large_image';
    private $schema = [];
    private $db;
    private $currentUrl;
    private $siteName = 'دواؤك - دليلك الشامل للأدوية';
    private $siteLanguage = 'ar';
    private $robotsContent = 'index, follow';
    
    /**
     * إنشاء كائن SEO جديد
     */
    public function __construct($db = null) {
        $this->db = $db;
        $this->currentUrl = $this->getCurrentUrl();
        $this->ogImage = $this->getBaseUrl() . '/assets/images/logo-social.png';
    }
    
    /**
     * تعيين عنوان الصفحة
     */
    public function setTitle($title) {
        $this->title = $this->cleanText($title);
        return $this;
    }
    
    /**
     * تعيين وصف الصفحة
     */
    public function setDescription($description) {
        $this->description = $this->truncateText($this->cleanText($description), 160);
        return $this;
    }
    
    /**
     * تعيين الكلمات المفتاحية للصفحة
     */
    public function setKeywords($keywords) {
        if (is_array($keywords)) {
            $this->keywords = implode(', ', $keywords);
        } else {
            $this->keywords = $this->cleanText($keywords);
        }
        return $this;
    }
    
    /**
     * تعيين الرابط الأساسي (canonical) للصفحة
     */
    public function setCanonicalUrl($url) {
        $this->canonicalUrl = $url;
        return $this;
    }
    
    /**
     * تعيين نوع محتوى Open Graph
     */
    public function setOgType($type) {
        $this->ogType = $type;
        return $this;
    }
    
    /**
     * تعيين صورة Open Graph للمشاركة
     */
    public function setOgImage($imageUrl) {
        $this->ogImage = $imageUrl;
        return $this;
    }
    
    /**
     * إضافة مخطط بيانات Schema.org
     */
    public function addSchema($type, $data) {
        $baseSchema = [
            '@context' => 'https://schema.org',
            '@type' => $type
        ];
        
        $this->schema[] = array_merge($baseSchema, $data);
        return $this;
    }
    
    /**
     * إضافة مخطط بيانات دواء
     */
    public function addMedicationSchema($medication) {
        // تحويل بيانات الدواء إلى مخطط Schema.org من نوع Product
        $schema = [
            'name' => $medication['trade_name'],
            'description' => $medication['description'] ?? ($medication['scientific_name'] . ' - ' . $medication['company']),
            'sku' => $medication['id'],
            'brand' => [
                '@type' => 'Brand',
                'name' => $medication['company']
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $medication['current_price'],
                'priceCurrency' => 'EGP',
                'availability' => 'https://schema.org/InStock',
                'url' => $this->currentUrl
            ]
        ];
        
        // إضافة صورة الدواء إذا وجدت
        if (!empty($medication['image_url'])) {
            $schema['image'] = $medication['image_url'];
            $this->setOgImage($medication['image_url']);
        }
        
        // إضافة معلومات البديل والمادة الفعالة
        if (!empty($medication['scientific_name'])) {
            $schema['activeIngredient'] = $medication['scientific_name'];
        }
        
        // إضافة المخطط
        $this->addSchema('Product', $schema);
        
        // إضافة مخطط خاص بالمنتج الطبي
        $medicalSchema = [
            'category' => $medication['category'] ?? 'Medicine',
            'activeIngredient' => $medication['scientific_name'] ?? '',
            'merchantReturnDays' => '14',
            'manufacturer' => [
                '@type' => 'Organization',
                'name' => $medication['company']
            ]
        ];
        
        if (!empty($medication['details'])) {
            $medicalSchema['description'] = $medication['details']['indications'] ?? '';
        }
        
        $this->addSchema('Drug', $medicalSchema);
        
        return $this;
    }
    
    /**
     * إضافة مخطط بيانات BreadcrumbList
     */
    public function addBreadcrumbSchema($breadcrumbs) {
        $items = [];
        $position = 1;
        
        foreach ($breadcrumbs as $name => $url) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $name,
                'item' => $url
            ];
            $position++;
        }
        
        $this->addSchema('BreadcrumbList', [
            'itemListElement' => $items
        ]);
        
        return $this;
    }
    
    /**
     * إنشاء وسوم SEO
     */
    public function generate() {
        $output = '';
        
        // إذا لم يتم تعيين عنوان، ضع عنوانًا افتراضيًا
        if (empty($this->title)) {
            $this->setTitle('دواؤك - دليلك الشامل للأدوية');
        }
        
        // إذا لم يتم تعيين وصف، ضع وصفًا افتراضيًا
        if (empty($this->description)) {
            $this->setDescription('منصة دواؤك هي دليلك الشامل للأدوية في مصر، ابحث وقارن واطلع على المعلومات التفصيلية عن الأدوية والبدائل المتاحة والأسعار.');
        }
        
        // عنوان الصفحة
        $output .= "<title>{$this->title}</title>\n";
        
        // العلامات الوصفية الأساسية
        $output .= "<meta name=\"description\" content=\"{$this->description}\" />\n";
        
        if (!empty($this->keywords)) {
            $output .= "<meta name=\"keywords\" content=\"{$this->keywords}\" />\n";
        }
        
        // علامة الروبوتس
        $output .= "<meta name=\"robots\" content=\"{$this->robotsContent}\" />\n";
        
        // العلامة القانونية
        if (!empty($this->canonicalUrl)) {
            $output .= "<link rel=\"canonical\" href=\"{$this->canonicalUrl}\" />\n";
        } else {
            $output .= "<link rel=\"canonical\" href=\"{$this->currentUrl}\" />\n";
        }
        
        // علامات اللغة والإتجاه
        $output .= "<meta property=\"og:locale\" content=\"ar_AR\" />\n";
        $output .= "<meta http-equiv=\"content-language\" content=\"ar\" />\n";
        
        // علامات Open Graph
        $output .= "<meta property=\"og:title\" content=\"{$this->title}\" />\n";
        $output .= "<meta property=\"og:description\" content=\"{$this->description}\" />\n";
        $output .= "<meta property=\"og:type\" content=\"{$this->ogType}\" />\n";
        $output .= "<meta property=\"og:url\" content=\"{$this->currentUrl}\" />\n";
        $output .= "<meta property=\"og:site_name\" content=\"{$this->siteName}\" />\n";
        
        if (!empty($this->ogImage)) {
            $output .= "<meta property=\"og:image\" content=\"{$this->ogImage}\" />\n";
            $output .= "<meta property=\"og:image:secure_url\" content=\"{$this->ogImage}\" />\n";
            $output .= "<meta property=\"og:image:width\" content=\"1200\" />\n";
            $output .= "<meta property=\"og:image:height\" content=\"630\" />\n";
            $output .= "<meta property=\"og:image:alt\" content=\"{$this->title}\" />\n";
        }
        
        // علامات Twitter Card
        $output .= "<meta name=\"twitter:card\" content=\"{$this->twitterCard}\" />\n";
        $output .= "<meta name=\"twitter:title\" content=\"{$this->title}\" />\n";
        $output .= "<meta name=\"twitter:description\" content=\"{$this->description}\" />\n";
        
        if (!empty($this->ogImage)) {
            $output .= "<meta name=\"twitter:image\" content=\"{$this->ogImage}\" />\n";
        }
        
        // إضافة مخططات البيانات الهيكلية
        if (!empty($this->schema)) {
            $output .= "<script type=\"application/ld+json\">\n";
            $output .= json_encode($this->schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $output .= "\n</script>\n";
        }
        
        return $output;
    }
    
    /**
     * الحصول على العنوان URL الحالي
     */
    public function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * الحصول على العنوان URL الأساسي للموقع
     */
    public function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        return $protocol . '://' . $host;
    }
    
    /**
     * تنظيف النص من الرموز الخاصة
     */
    private function cleanText($text) {
        $text = trim(strip_tags($text));
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        return $text;
    }
    
    /**
     * اقتصاص النص إلى طول معين
     */
    private function truncateText($text, $length = 160) {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        
        $text = mb_substr($text, 0, $length, 'UTF-8');
        $lastSpace = mb_strrpos($text, ' ', 0, 'UTF-8');
        
        if ($lastSpace !== false) {
            $text = mb_substr($text, 0, $lastSpace, 'UTF-8');
        }
        
        return $text . '...';
    }
}
?>