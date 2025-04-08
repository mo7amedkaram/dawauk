<?php
/**
 * seo.php - ملف تحسين محركات البحث (SEO)
 * يقوم هذا الملف بإدارة التحسينات اللازمة لمحركات البحث مثل العناوين ووصف الصفحات والكلمات المفتاحية
 * والعلامات التعريفية لوسائل التواصل الاجتماعي ومعلومات Schema.org
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
     * 
     * @param mixed $db كائن قاعدة البيانات
     */
    public function __construct($db = null) {
        $this->db = $db;
        $this->currentUrl = $this->getCurrentUrl();
        
        // تعيين الصورة الافتراضية للمشاركة
        $this->ogImage = $this->getBaseUrl() . '/assets/images/logo-social.png';
    }
    
    /**
     * تعيين عنوان الصفحة
     * 
     * @param string $title عنوان الصفحة
     * @return SEO كائن SEO للتسلسل
     */
    public function setTitle($title) {
        // تنظيف العنوان وإضافة اسم الموقع
        $this->title = $this->cleanText($title);
        return $this;
    }
    
    /**
     * تعيين وصف الصفحة
     * 
     * @param string $description وصف الصفحة
     * @return SEO كائن SEO للتسلسل
     */
    public function setDescription($description) {
        // تنظيف الوصف وتقليل طوله إلى 160 حرفًا
        $this->description = $this->truncateText($this->cleanText($description), 160);
        return $this;
    }
    
    /**
     * تعيين الكلمات المفتاحية للصفحة
     * 
     * @param array|string $keywords الكلمات المفتاحية
     * @return SEO كائن SEO للتسلسل
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
     * 
     * @param string $url الرابط الأساسي
     * @return SEO كائن SEO للتسلسل
     */
    public function setCanonicalUrl($url) {
        $this->canonicalUrl = $url;
        return $this;
    }
    
    /**
     * تعيين نوع محتوى Open Graph
     * 
     * @param string $type نوع المحتوى (website, article, product, etc.)
     * @return SEO كائن SEO للتسلسل
     */
    public function setOgType($type) {
        $this->ogType = $type;
        return $this;
    }
    
    /**
     * تعيين صورة Open Graph للمشاركة
     * 
     * @param string $imageUrl رابط الصورة
     * @return SEO كائن SEO للتسلسل
     */
    public function setOgImage($imageUrl) {
        $this->ogImage = $imageUrl;
        return $this;
    }
    
    /**
     * تعيين نوع بطاقة تويتر
     * 
     * @param string $card نوع البطاقة (summary, summary_large_image)
     * @return SEO كائن SEO للتسلسل
     */
    public function setTwitterCard($card) {
        $this->twitterCard = $card;
        return $this;
    }
    
    /**
     * تعيين محتوى الروبوتس
     * 
     * @param string $content محتوى الروبوتس
     * @return SEO كائن SEO للتسلسل
     */
    public function setRobotsContent($content) {
        $this->robotsContent = $content;
        return $this;
    }
    
    /**
     * إضافة مخطط بيانات Schema.org
     * 
     * @param string $type نوع المخطط
     * @param array $data بيانات المخطط
     * @return SEO كائن SEO للتسلسل
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
     * 
     * @param array $medication بيانات الدواء
     * @return SEO كائن SEO للتسلسل
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
        
        $this->addSchema('MedicalEntity', $medicalSchema);
        
        return $this;
    }
    
    /**
     * إضافة مخطط بيانات صفحة FAQPage
     * 
     * @param array $faqs أسئلة وأجوبة
     * @return SEO كائن SEO للتسلسل
     */
    public function addFAQSchema($faqs) {
        $faqItems = [];
        
        foreach ($faqs as $faq) {
            $faqItems[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }
        
        $this->addSchema('FAQPage', [
            'mainEntity' => $faqItems
        ]);
        
        return $this;
    }
    
    /**
     * إضافة مخطط بيانات BreadcrumbList
     * 
     * @param array $breadcrumbs مسار التنقل
     * @return SEO كائن SEO للتسلسل
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
     * إضافة مخطط طبي لدواء
     * 
     * @param array $medication بيانات الدواء
     * @return SEO كائن SEO للتسلسل
     */
    public function addMedicalSchema($medication) {
        // إضافة مخطط Drug
        $drugSchema = [
            'name' => $medication['trade_name'],
            'activeIngredient' => $medication['scientific_name'] ?? '',
            'manufacturer' => [
                '@type' => 'Organization',
                'name' => $medication['company']
            ],
            'nonProprietaryName' => $medication['scientific_name'] ?? '',
            'url' => $this->currentUrl
        ];
        
        // إضافة معلومات إضافية إذا كانت متوفرة
        if (!empty($medication['details'])) {
            $drugSchema['administrationRoute'] = $medication['form'] ?? 'Oral';
            
            if (!empty($medication['details']['indications'])) {
                $drugSchema['description'] = $medication['details']['indications'];
                
                // إضافة مخطط MedicalIndication
                $this->addSchema('MedicalIndication', [
                    'name' => 'Indications for ' . $medication['trade_name'],
                    'description' => $medication['details']['indications']
                ]);
            }
            
            if (!empty($medication['details']['side_effects'])) {
                // إضافة مخطط MedicalSideEffect
                $this->addSchema('MedicalSideEffect', [
                    'name' => 'Side Effects of ' . $medication['trade_name'],
                    'description' => $medication['details']['side_effects']
                ]);
            }
        }
        
        // إضافة مخطط Drug
        $this->addSchema('Drug', $drugSchema);
        
        return $this;
    }
    
    /**
     * إنشاء وسوم SEO
     * 
     * @return string وسوم HTML لرأس الصفحة
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
        
        // علامات Open Graph
        $output .= "<meta property=\"og:title\" content=\"{$this->title}\" />\n";
        $output .= "<meta property=\"og:description\" content=\"{$this->description}\" />\n";
        $output .= "<meta property=\"og:type\" content=\"{$this->ogType}\" />\n";
        $output .= "<meta property=\"og:url\" content=\"{$this->currentUrl}\" />\n";
        $output .= "<meta property=\"og:image\" content=\"{$this->ogImage}\" />\n";
        $output .= "<meta property=\"og:site_name\" content=\"{$this->siteName}\" />\n";
        $output .= "<meta property=\"og:locale\" content=\"{$this->siteLanguage}\" />\n";
        
        // علامات Twitter Cards
        $output .= "<meta name=\"twitter:card\" content=\"{$this->twitterCard}\" />\n";
        $output .= "<meta name=\"twitter:title\" content=\"{$this->title}\" />\n";
        $output .= "<meta name=\"twitter:description\" content=\"{$this->description}\" />\n";
        $output .= "<meta name=\"twitter:image\" content=\"{$this->ogImage}\" />\n";
        
        // إضافة مخططات Schema.org
        if (!empty($this->schema)) {
            foreach ($this->schema as $schema) {
                $output .= "<script type=\"application/ld+json\">\n";
                $output .= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $output .= "\n</script>\n";
            }
        }
        
        return $output;
    }
    
    /**
     * تنظيف النص من العلامات غير المرغوب فيها
     * 
     * @param string $text النص المراد تنظيفه
     * @return string النص المنظف
     */
    private function cleanText($text) {
        return htmlspecialchars(strip_tags($text), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * تقليص النص إلى طول معين
     * 
     * @param string $text النص المراد تقليصه
     * @param int $length الحد الأقصى للطول
     * @return string النص المقلص
     */
    private function truncateText($text, $length) {
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length - 3) . '...';
        }
        return $text;
    }
    
    /**
     * الحصول على الرابط الحالي للصفحة
     * 
     * @return string الرابط الحالي
     */
    private function getCurrentUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * الحصول على الرابط الأساسي للموقع
     * 
     * @return string الرابط الأساسي
     */
    private function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . '://' . $host;
    }
}