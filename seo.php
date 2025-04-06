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
     * إعداد السيو للصفحة الرئيسية
     * 
     * @return SEO كائن SEO للتسلسل
     */
    public function setupHomePage() {
        $this->setTitle('دواؤك - دليلك الشامل للأدوية في مصر')
             ->setDescription('منصة دواؤك توفر معلومات شاملة عن الأدوية، قارن الأسعار، ابحث عن البدائل، واطلع على الآثار الجانبية والتفاعلات الدوائية لأكثر من 25,000 دواء.')
             ->setKeywords(['أدوية مصر', 'أسعار الأدوية', 'بدائل الأدوية', 'دليل الأدوية', 'معلومات دوائية', 'تفاعلات دوائية', 'الآثار الجانبية', 'مقارنة أدوية'])
             ->setCanonicalUrl($this->getBaseUrl())
             ->setOgType('website');
        
        // إضافة مخطط بيانات للصفحة الرئيسية
        $this->addSchema('WebSite', [
            'name' => 'دواؤك - دليلك الشامل للأدوية',
            'url' => $this->getBaseUrl(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $this->getBaseUrl() . '/search.php?q={search_term_string}',
                'query-input' => 'required name=search_term_string'
            ]
        ]);
        
        // إضافة مخطط للمنظمة
        $this->addSchema('Organization', [
            'name' => 'دواؤك',
            'url' => $this->getBaseUrl(),
            'logo' => $this->getBaseUrl() . '/assets/images/logo.png',
            'sameAs' => [
                'https://www.facebook.com/dawaauk',
                'https://twitter.com/dawaauk'
            ]
        ]);
        
        return $this;
    }
    
    /**
     * إعداد السيو لصفحة البحث
     * 
     * @param string $query استعلام البحث
     * @param int $total إجمالي نتائج البحث
     * @return SEO كائن SEO للتسلسل
     */
    public function setupSearchPage($query, $total = 0) {
        $title = empty($query) ? 'بحث متقدم عن الأدوية' : 'نتائج البحث عن: ' . $query;
        $description = empty($query) 
            ? 'استخدم محرك البحث المتقدم في دواؤك للعثور على الأدوية بسهولة، البحث بالاسم التجاري، المادة الفعالة، الشركة، السعر، أو بأسلوب البحث الذكي.'
            : "نتائج البحث عن {$query} - تم العثور على {$total} من الأدوية المطابقة في قاعدة بيانات دواؤك الشاملة. قارن الأسعار والبدائل المتاحة.";
        
        $keywords = ['بحث أدوية', 'بحث متقدم', 'محرك بحث أدوية'];
        if (!empty($query)) {
            $keywords = array_merge($keywords, [
                $query, 'بحث عن ' . $query, 'أدوية مشابهة لـ ' . $query, 'بدائل ' . $query
            ]);
        }
        
        $this->setTitle($title)
             ->setDescription($description)
             ->setKeywords($keywords)
             ->setCanonicalUrl($this->currentUrl)
             ->setOgType('website');
        
        // منع فهرسة نتائج البحث المخصصة للحفاظ على جودة الفهرسة
        if (!empty($query)) {
            $this->setRobotsContent('noindex, follow');
        }
        
        return $this;
    }
    
    /**
     * إعداد السيو لصفحة تفاصيل الدواء
     * 
     * @param array $medication بيانات الدواء
     * @return SEO كائن SEO للتسلسل
     */
    public function setupMedicationPage($medication) {
        $scientificName = $medication['scientific_name'] ?? '';
        $company = $medication['company'] ?? '';
        $category = $medication['category'] ?? '';
        
        $title = $medication['trade_name'];
        if (!empty($scientificName)) {
            $title .= ' (' . $scientificName . ')';
        }
        
        $description = "معلومات شاملة عن دواء {$medication['trade_name']}";
        if (!empty($scientificName)) {
            $description .= " - المادة الفعالة: {$scientificName}";
        }
        if (!empty($company)) {
            $description .= " - الشركة المنتجة: {$company}";
        }
        if (!empty($medication['current_price'])) {
            $description .= " - السعر: {$medication['current_price']} ج.م";
        }
        
        // إضافة معلومات من details إذا توفرت
        if (!empty($medication['details']) && !empty($medication['details']['indications'])) {
            $description .= " - " . $this->truncateText($medication['details']['indications'], 80);
        }
        
        $keywords = [
            $medication['trade_name'],
            'دواء ' . $medication['trade_name'],
            'سعر ' . $medication['trade_name'],
            'بديل ' . $medication['trade_name']
        ];
        
        if (!empty($scientificName)) {
            $keywords[] = $scientificName;
            $keywords[] = 'أدوية تحتوي على ' . $scientificName;
        }
        
        if (!empty($company)) {
            $keywords[] = 'أدوية شركة ' . $company;
        }
        
        if (!empty($category)) {
            $keywords[] = 'أدوية ' . $category;
        }
        
        $this->setTitle($title)
             ->setDescription($description)
             ->setKeywords($keywords)
             ->setCanonicalUrl($this->currentUrl)
             ->setOgType('product');
        
        // إضافة صورة المشاركة
        if (!empty($medication['image_url'])) {
            $this->setOgImage($medication['image_url']);
        }
        
        // إضافة مخططات بيانات هيكلية للدواء
        $this->addMedicationSchema($medication);
        $this->addMedicalSchema($medication);
        
        // إضافة مخطط BreadcrumbList
        $breadcrumbs = [
            'الرئيسية' => $this->getBaseUrl(),
        ];
        
        if (!empty($category)) {
            $breadcrumbs[$category] = $this->getBaseUrl() . '/search.php?category=' . urlencode($category);
        }
        
        $breadcrumbs[$medication['trade_name']] = $this->currentUrl;
        
        $this->addBreadcrumbSchema($breadcrumbs);
        
        return $this;
    }
    
    /**
     * إعداد السيو لصفحة التصنيفات
     * 
     * @param array $category تفاصيل التصنيف (اختياري)
     * @return SEO كائن SEO للتسلسل
     */
    public function setupCategoriesPage($category = null) {
        if ($category) {
            $title = $category['arabic_name'] . ' - دليل أدوية ' . $category['arabic_name'];
            $description = !empty($category['description']) 
                ? $this->truncateText($category['description'], 160) 
                : "قائمة شاملة بأدوية {$category['arabic_name']} المتوفرة في مصر، تصفح قائمة الأدوية، قارن الأسعار، وتعرف على البدائل المتاحة.";
            
            $keywords = [
                'أدوية ' . $category['arabic_name'],
                'علاج ' . $category['arabic_name'],
                'دليل أدوية ' . $category['arabic_name'],
                'قائمة أدوية ' . $category['arabic_name'],
                'أسعار أدوية ' . $category['arabic_name']
            ];
        } else {
            $title = 'تصنيفات الأدوية - دليل التصنيفات العلاجية';
            $description = 'تصفح الأدوية حسب التصنيف العلاجي. دليل شامل لجميع تصنيفات الأدوية المتوفرة في مصر مع أمثلة ومعلومات تفصيلية عن كل فئة.';
            $keywords = [
                'تصنيفات الأدوية',
                'أنواع الأدوية',
                'تصنيفات علاجية',
                'فئات الأدوية',
                'دليل تصنيفات الأدوية',
                'أدوية حسب التصنيف'
            ];
        }
        
        $this->setTitle($title)
             ->setDescription($description)
             ->setKeywords($keywords)
             ->setCanonicalUrl($this->currentUrl)
             ->setOgType('website');
        
        // إضافة مخطط للصفحة
        if ($category) {
            $this->addSchema('CollectionPage', [
                'name' => $category['arabic_name'],
                'description' => $description,
                'url' => $this->currentUrl
            ]);
            
            // إضافة مخطط BreadcrumbList
            $breadcrumbs = [
                'الرئيسية' => $this->getBaseUrl(),
                'التصنيفات' => $this->getBaseUrl() . '/categories.php',
                $category['arabic_name'] => $this->currentUrl
            ];
            
            $this->addBreadcrumbSchema($breadcrumbs);
        } else {
            $this->addSchema('CollectionPage', [
                'name' => 'تصنيفات الأدوية',
                'description' => $description,
                'url' => $this->currentUrl
            ]);
            
            // إضافة مخطط BreadcrumbList
            $breadcrumbs = [
                'الرئيسية' => $this->getBaseUrl(),
                'التصنيفات' => $this->currentUrl
            ];
            
            $this->addBreadcrumbSchema($breadcrumbs);
        }
        
        return $this;
    }
    
    /**
     * إعداد السيو لصفحة المقارنة
     * 
     * @param array $medications قائمة الأدوية للمقارنة
     * @return SEO كائن SEO للتسلسل
     */
    public function setupComparePage($medications = []) {
        if (!empty($medications)) {
            $medNames = array_map(function($med) {
                return $med['trade_name'];
            }, $medications);
            
            $title = 'مقارنة بين: ' . implode(' و ', $medNames);
            $description = 'مقارنة تفصيلية بين أدوية ' . implode(' و ', $medNames) . ' من حيث السعر، المادة الفعالة، دواعي الاستعمال، الآثار الجانبية، والشركة المنتجة.';
            
            $keywords = [
                'مقارنة أدوية',
                'مقارنة ' . implode(' و ', $medNames),
                'الفرق بين ' . implode(' و ', $medNames),
                'أيهما أفضل ' . implode(' أم ', $medNames)
            ];
            
            // منع فهرسة صفحات المقارنة المخصصة للحفاظ على جودة الفهرسة
            $this->setRobotsContent('noindex, follow');
        } else {
            $title = 'مقارنة الأدوية - أداة المقارنة الشاملة للأدوية';
            $description = 'قارن بين الأدوية المختلفة من حيث السعر، المادة الفعالة، دواعي الاستعمال، الآثار الجانبية، الجرعات، والشركة المنتجة للحصول على أفضل قرار علاجي.';
            
            $keywords = [
                'مقارنة أدوية',
                'أداة مقارنة الأدوية',
                'مقارنة أسعار الأدوية',
                'مقارنة فعالية الأدوية',
                'مقارنة الآثار الجانبية',
                'مقارنة البدائل الدوائية'
            ];
        }
        
        $this->setTitle($title)
             ->setDescription($description)
             ->setKeywords($keywords)
             ->setCanonicalUrl($this->currentUrl)
             ->setOgType('website');
        
        // إضافة مخطط للصفحة
        $this->addSchema('WebPage', [
            'name' => $title,
            'description' => $description,
            'url' => $this->currentUrl
        ]);
        
        // إضافة مخطط BreadcrumbList
        $breadcrumbs = [
            'الرئيسية' => $this->getBaseUrl(),
            'مقارنة الأدوية' => $this->currentUrl
        ];
        
        $this->addBreadcrumbSchema($breadcrumbs);
        
        return $this;
    }
    
    /**
     * إعداد السيو لصفحة الدليل الدوائي
     * 
     * @param string $letter الحرف المحدد (اختياري)
     * @return SEO كائن SEO للتسلسل
     */
    public function setupGuidePage($letter = null) {
        if ($letter) {
            $title = "الأدوية التي تبدأ بحرف {$letter} - الدليل الدوائي";
            $description = "قائمة شاملة بالأدوية التي تبدأ بحرف {$letter}. تصفح الأدوية حسب الترتيب الأبجدي للحصول على معلومات عن كل دواء، السعر، المادة الفعالة، والشركة المنتجة.";
            
            $keywords = [
                "أدوية تبدأ بحرف {$letter}",
                "قائمة أدوية {$letter}",
                "دليل أدوية {$letter}",
                "علاجات تبدأ بحرف {$letter}"
            ];
        } else {
            $title = "الدليل الدوائي - تصفح الأدوية بالترتيب الأبجدي";
            $description = "الدليل الدوائي الشامل لتصفح الأدوية حسب الحرف الأبجدي. اختر الحرف للاطلاع على قائمة الأدوية المتوفرة والحصول على معلومات تفصيلية عن كل دواء.";
            
            $keywords = [
                "الدليل الدوائي",
                "تصفح الأدوية أبجدياً",
                "قاموس الأدوية",
                "قائمة الأدوية",
                "الدليل الأبجدي للأدوية",
                "أدوية حسب الحرف"
            ];
        }
        
        $this->setTitle($title)
             ->setDescription($description)
             ->setKeywords($keywords)
             ->setCanonicalUrl($this->currentUrl)
             ->setOgType('website');
        
        // إضافة مخطط للصفحة
        $this->addSchema('CollectionPage', [
            'name' => $title,
            'description' => $description,
            'url' => $this->currentUrl
        ]);
        
        // إضافة مخطط BreadcrumbList
        $breadcrumbs = [
            'الرئيسية' => $this->getBaseUrl(),
            'الدليل الدوائي' => $this->getBaseUrl() . '/guide.php'
        ];
        
        if ($letter) {
            $breadcrumbs["أدوية تبدأ بحرف {$letter}"] = $this->currentUrl;
        }
        
        $this->addBreadcrumbSchema($breadcrumbs);
        
        return $this;
    }
    
    /**
     * إنشاء وسوم السيو الخاصة بصفحة معينة
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
        
        // علامات اللغة والإتجاه
        $output .= "<meta property=\"og:locale\" content=\"ar_AR\" />\n";
        $output .= "<meta http-equiv=\"content-language\" content=\"ar\" />\n";
        $output .= "<html lang=\"ar\" dir=\"rtl\">\n";
        
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
        
        // علامات إضافية للهواتف المحمولة
        $output .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n";
        $output .= "<meta name=\"mobile-web-app-capable\" content=\"yes\" />\n";
        $output .= "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\" />\n";
        $output .= "<meta name=\"apple-mobile-web-app-title\" content=\"{$this->siteName}\" />\n";
        
        // الروابط البديلة للهواتف
        $output .= "<link rel=\"alternate\" media=\"only screen and (max-width: 640px)\" href=\"{$this->currentUrl}\" />\n";
        
        // إضافة رمز الأيقونة
        $output .= "<link rel=\"icon\" href=\"{$this->getBaseUrl()}/favicon.ico\" type=\"image/x-icon\" />\n";
        $output .= "<link rel=\"shortcut icon\" href=\"{$this->getBaseUrl()}/favicon.ico\" type=\"image/x-icon\" />\n";
        
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
     * 
     * @return string العنوان URL الحالي
     */
    private function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * الحصول على العنوان URL الأساسي للموقع
     * 
     * @return string العنوان URL الأساسي
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        return $protocol . '://' . $host;
    }
    
    /**
     * تنظيف النص من الرموز الخاصة
     * 
     * @param string $text النص المراد تنظيفه
     * @return string النص المنظف
     */
    private function cleanText($text) {
        // تنظيف النص من الرموز الخاصة في HTML
        $text = trim(strip_tags($text));
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        return $text;
    }
    
    /**
     * اقتصاص النص إلى طول معين
     * 
     * @param string $text النص المراد اقتصاصه
     * @param int $length الطول المطلوب
     * @return string النص المقتصص
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