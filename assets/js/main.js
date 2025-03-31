// assets/js/main.js - محسن لدعم طرق البحث المتعددة

document.addEventListener('DOMContentLoaded', function() {
    // تهيئة البحث التلقائي المحسن
    initializeEnhancedAutocomplete();
    
    // تفعيل أداة التلميح (tooltip)
    enableTooltips();
});

/**
 * تهيئة خاصية البحث التلقائي المحسن في شريط البحث
 */
function initializeEnhancedAutocomplete() {
    // البحث عن جميع حقول البحث في الصفحة
    const searchInputs = document.querySelectorAll('input[type="search"], .search-autocomplete');
    
    searchInputs.forEach(input => {
        // إنشاء عنصر لعرض نتائج البحث التلقائي
        const searchDropdown = document.createElement('div');
        searchDropdown.className = 'search-dropdown';
        
        // إنشاء رأس للبحث مع الفلاتر
        const searchHeader = document.createElement('div');
        searchHeader.className = 'search-dropdown-header';
        
        // إنشاء خيارات طرق البحث
        const searchMethodsHTML = `
            <div class="search-methods mb-2">
                <span class="search-method-label">طريقة البحث:</span>
                <div class="search-method-options">
                    <button type="button" class="search-method-btn active" data-method="trade_name">بداية الاسم</button>
                    <button type="button" class="search-method-btn" data-method="trade_anypos">أي موضع</button>
                    <button type="button" class="search-method-btn" data-method="google_style">النطق المتشابه</button>
                    <button type="button" class="search-method-btn" data-method="combined">بحث شامل</button>
                </div>
            </div>
        `;
        
        // فلاتر البحث
        const searchFiltersHTML = `
            <div class="search-filters">
                <button type="button" class="search-filter-btn active" data-filter="all">الكل</button>
                <button type="button" class="search-filter-btn" data-filter="trade_name">الاسم التجاري</button>
                <button type="button" class="search-filter-btn" data-filter="scientific_name">المادة الفعالة</button>
                <button type="button" class="search-filter-btn" data-filter="company">الشركة المنتجة</button>
            </div>
        `;
        
        searchHeader.innerHTML = searchMethodsHTML + searchFiltersHTML;
        
        // إنشاء محتوى البحث
        const searchContent = document.createElement('div');
        searchContent.className = 'search-dropdown-content';
        
        // إنشاء نصائح البحث
        const searchTips = document.createElement('div');
        searchTips.className = 'search-tips';
        searchTips.innerHTML = `
            <div class="search-tips-content">
                <p><strong>نصائح البحث:</strong></p>
                <ul class="search-tips-list">
                    <li><b>بداية الاسم:</b> اكتب أول حرفين من اسم الدواء للبحث السريع</li>
                    <li><b>أي موضع:</b> البحث عن الكلمة في أي موضع في اسم الدواء</li>
                    <li><b>النطق المتشابه:</b> البحث باستخدام الأحرف المتشابهة في النطق</li>
                    <li><b>بحث شامل:</b> البحث في كل البيانات بما فيها السعر</li>
                </ul>
                <p class="search-tips-example">أمثلة: "au" أو "cef x vial" أو "زنتاك"</p>
            </div>
        `;
        
        // إنشاء تذييل البحث
        const searchFooter = document.createElement('div');
        searchFooter.className = 'search-footer';
        searchFooter.innerHTML = `
            <a href="search.php">
                <i class="fas fa-search me-1"></i> بحث متقدم
            </a>
        `;
        
        // إضافة العناصر إلى الحاوية
        searchDropdown.appendChild(searchHeader);
        searchDropdown.appendChild(searchContent);
        searchDropdown.appendChild(searchTips);
        searchDropdown.appendChild(searchFooter);
        
        // إضافة الحاوية للصفحة
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(searchDropdown);
        
        // فلتر بحث حالي وطريقة البحث الحالية
        let currentFilter = 'all';
        let currentMethod = 'trade_name';
        
        // إضافة مستمعات الأحداث لأزرار طرق البحث
        const methodButtons = searchHeader.querySelectorAll('.search-method-btn');
        methodButtons.forEach(button => {
            button.addEventListener('click', function() {
                // إزالة الفلتر النشط من جميع الأزرار
                methodButtons.forEach(btn => btn.classList.remove('active'));
                
                // تنشيط الزر المضغوط
                this.classList.add('active');
                
                // تحديث طريقة البحث الحالية
                currentMethod = this.getAttribute('data-method');
                
                // إظهار نصائح البحث
                searchTips.style.display = 'block';
                
                // تحديث نصائح البحث حسب الطريقة المحددة
                updateSearchTips(searchTips, currentMethod);
                
                // إعادة البحث باستخدام الطريقة الجديدة
                if (input.value.trim().length >= 2) {
                    fetchSearchResults(input.value.trim(), searchContent, currentFilter, currentMethod);
                }
            });
        });
        
        // إضافة مستمعات الأحداث لأزرار الفلتر
        const filterButtons = searchHeader.querySelectorAll('.search-filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // إزالة الفلتر النشط من جميع الأزرار
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // تنشيط الزر المضغوط
                this.classList.add('active');
                
                // تحديث الفلتر الحالي
                currentFilter = this.getAttribute('data-filter');
                
                // إعادة البحث باستخدام الفلتر الجديد
                if (input.value.trim().length >= 2) {
                    fetchSearchResults(input.value.trim(), searchContent, currentFilter, currentMethod);
                }
            });
        });
        
        // إضافة حدث للكتابة في حقل البحث
        input.addEventListener('input', debounce(function(e) {
            const query = e.target.value.trim();
            
            // إذا كان البحث فارغاً أو أقل من حرفين، أخفِ النتائج
            if (query.length < 2) {
                searchContent.innerHTML = '';
                searchDropdown.style.display = 'none';
                return;
            }
            
            // إرسال طلب AJAX للبحث
            fetchSearchResults(query, searchContent, currentFilter, currentMethod);
            searchDropdown.style.display = 'block';
            
            // إخفاء نصائح البحث عند ظهور نتائج
            searchTips.style.display = 'none';
        }, 300)); // تأخير 300 مللي ثانية للحد من عدد الطلبات
        
        // إظهار نصائح البحث عند التركيز في حقل البحث الفارغ
        input.addEventListener('focus', function() {
            if (this.value.trim().length < 2) {
                searchDropdown.style.display = 'block';
                searchContent.innerHTML = '';
                searchTips.style.display = 'block';
                updateSearchTips(searchTips, currentMethod);
            } else {
                searchDropdown.style.display = 'block';
            }
        });
        
        // إخفاء النتائج عند النقر خارجها
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });
    });
}

/**
 * تحديث نصائح البحث حسب طريقة البحث المحددة
 * 
 * @param {HTMLElement} tipsElement - عنصر نصائح البحث
 * @param {string} method - طريقة البحث
 */
function updateSearchTips(tipsElement, method) {
    const tipsList = tipsElement.querySelector('.search-tips-list');
    const tipsExample = tipsElement.querySelector('.search-tips-example');
    
    if (!tipsList || !tipsExample) return;
    
    switch (method) {
        case 'trade_name':
            tipsList.innerHTML = `
                <li>اكتب أول حرفين من اسم الدواء للبحث السريع</li>
                <li>استخدم المسافات بدل الأحرف غير المعروفة</li>
                <li>للبحث من أي موضع، ضع مسافة في بداية البحث</li>
                <li>مثال: "au" يبحث عن الأدوية التي تبدأ بـ "au"</li>
            `;
            tipsExample.innerHTML = 'أمثلة: "au" أو "au tab" أو " cef" (مسافة في البداية للبحث في أي موضع)';
            break;
            
        case 'trade_anypos':
            tipsList.innerHTML = `
                <li>البحث عن الكلمة في أي موضع في اسم الدواء</li>
                <li>يمكنك البحث بأجزاء من اسم الدواء</li>
                <li>يمكنك إدخال أكثر من كلمة للبحث عن جميعها</li>
                <li>مثال: "tab" يبحث عن كل الأدوية التي تحتوي على "tab"</li>
            `;
            tipsExample.innerHTML = 'أمثلة: "tab" أو "vial" أو "500 mg"';
            break;
            
        case 'google_style':
            tipsList.innerHTML = `
                <li>بحث عن الأحرف المتشابهة في النطق</li>
                <li>يمكنك البحث بالعربية أو الإنجليزية</li>
                <li>الأحرف المتشابهة تعتبر متطابقة عند البحث</li>
                <li>مثال: "زنتاك" يعطي نتائج مشابهة لـ "zantac"</li>
            `;
            tipsExample.innerHTML = 'أمثلة: "pholomoks" أو "saimsikon" أو "زنتاك"';
            break;
            
        case 'combined':
            tipsList.innerHTML = `
                <li>بحث شامل في جميع حقول الدواء</li>
                <li>يمكنك البحث بالاسم أو المادة الفعالة أو السعر</li>
                <li>إذا كان البحث رقمًا، سيتم البحث في نطاق سعري</li>
                <li>مثال: "35" يبحث عن الأدوية بسعر حوالي 35 جنيه</li>
            `;
            tipsExample.innerHTML = 'أمثلة: "نوفالجين" أو "باراسيتامول" أو "20" (للبحث بالسعر)';
            break;
    }
}

/**
 * جلب نتائج البحث من الخادم
 * @param {string} query - نص البحث
 * @param {HTMLElement} container - عنصر حاوية نتائج البحث
 * @param {string} filter - فلتر البحث (all, trade_name, scientific_name, company)
 * @param {string} method - طريقة البحث (trade_name, trade_anypos, google_style, combined)
 */
function fetchSearchResults(query, container, filter = 'all', method = 'trade_name') {
    // إظهار مؤشر التحميل
    container.innerHTML = `
        <div class="search-loading">
            <i class="fas fa-spinner fa-spin me-2"></i> جاري البحث...
        </div>
    `;
    
    // تحقق إذا كان البحث يحتوي على علامة النجمة (*)
    const hasWildcard = query.includes('*');
    
    // إعداد طلب AJAX مع فلتر البحث وطريقة البحث
    fetch(`search_autocomplete.php?q=${encodeURIComponent(query)}&filter=${filter}&method=${method}`)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            if (data.length === 0) {
                container.innerHTML = `
                    <div class="search-no-results">
                        <i class="fas fa-search fa-2x mb-2 text-muted"></i>
                        <p>لم يتم العثور على نتائج تطابق بحثك</p>
                        <small>جرب طريقة بحث أخرى أو استخدم كلمات مختلفة</small>
                    </div>
                `;
                return;
            }
            
            // عرض نتائج البحث
            data.forEach(item => {
                // تحديد نوع المطابقة
                let matchType = item.match_type || 'trade_name';
                let badgeClass = '';
                let badgeText = '';
                
                switch (matchType) {
                    case 'trade_name':
                    case 'phonetic':
                        badgeClass = 'search-result-badge-trade';
                        badgeText = 'اسم تجاري';
                        break;
                    case 'scientific_name':
                        badgeClass = 'search-result-badge-scientific';
                        badgeText = 'مادة فعالة';
                        break;
                    case 'company':
                        badgeClass = 'search-result-badge-company';
                        badgeText = 'شركة';
                        break;
                }
                
                // إضافة شارة لطريقة البحث
                let methodBadge = '';
                if (method === 'google_style') {
                    methodBadge = '<span class="search-method-badge">تطابق صوتي</span>';
                } else if (method === 'combined' && item.is_price_match) {
                    methodBadge = '<span class="search-method-badge">تطابق سعري</span>';
                }
                
                // إنشاء نص البحث مع تمييز الكلمات المطابقة
                let displayName = item.trade_name;
                
                // إذا كان البحث يحتوي على علامة النجمة، لا تقم بتمييز الكلمات
                if (!hasWildcard && method !== 'google_style') {
                    const regex = new RegExp('(' + query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + ')', 'gi');
                    displayName = displayName.replace(regex, '<span class="search-highlight">$1</span>');
                }
                
                // حساب نسبة الخصم إذا وجدت
                let discountBadge = '';
                if (item.old_price > 0 && item.old_price > item.current_price) {
                    const discountPercent = Math.round(100 - (item.current_price / item.old_price * 100));
                    discountBadge = `<span class="discount-badge">-${discountPercent}%</span>`;
                }
                
                // إنشاء عنصر النتيجة
                const resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                resultItem.innerHTML = `
                    <div class="search-result-item-info">
                        <div class="search-result-name">
                            <span class="search-result-badge ${badgeClass}">${badgeText}</span>
                            ${methodBadge}
                            ${displayName}
                        </div>
                        <div class="search-result-details">
                            <div class="search-result-detail">
                                <i class="fas fa-flask search-result-detail-icon"></i>
                                ${item.scientific_name}
                            </div>
                            <div class="search-result-detail">
                                <i class="fas fa-building search-result-detail-icon"></i>
                                ${item.company}
                            </div>
                            <div class="search-result-detail">
                                <i class="fas fa-box search-result-detail-icon"></i>
                                ${item.units_per_package || 1} وحدة
                            </div>
                        </div>
                    </div>
                    <div class="search-result-price">
                        <span class="search-result-price-current">${parseFloat(item.current_price).toFixed(2)} ج.م</span>
                        ${item.old_price > 0 && item.old_price > item.current_price 
                            ? `<span class="search-result-price-old">${parseFloat(item.old_price).toFixed(2)} ج.م</span>${discountBadge}` 
                            : ''}
                    </div>
                `;
                
                // إضافة حدث النقر للانتقال إلى صفحة تفاصيل الدواء
                resultItem.addEventListener('click', function() {
                    window.location.href = 'medication.php?id=' + item.id;
                });
                
                container.appendChild(resultItem);
            });
        })
        .catch(error => {
            console.error('خطأ في جلب نتائج البحث:', error);
            container.innerHTML = `
                <div class="search-no-results">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2 text-danger"></i>
                    <p>حدث خطأ أثناء البحث</p>
                    <small>يرجى المحاولة مرة أخرى لاحقاً</small>
                </div>
            `;
        });
}

/**
 * تأخير تنفيذ الدالة للحد من عدد الطلبات
 * @param {Function} func - الدالة المراد تأخير تنفيذها
 * @param {number} wait - وقت التأخير بالمللي ثانية
 * @returns {Function} - دالة مع تأخير
 */
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * تفعيل أداة التلميح (tooltip)
 */
function enableTooltips() {
    const tooltips = document.querySelectorAll('.custom-tooltip');
    
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const tooltipContent = this.querySelector('.tooltip-content');
            if (tooltipContent) {
                tooltipContent.style.visibility = 'visible';
                tooltipContent.style.opacity = '1';
            }
        });
        
        tooltip.addEventListener('mouseleave', function() {
            const tooltipContent = this.querySelector('.tooltip-content');
            if (tooltipContent) {
                tooltipContent.style.visibility = 'hidden';
                tooltipContent.style.opacity = '0';
            }
        });
    });
}