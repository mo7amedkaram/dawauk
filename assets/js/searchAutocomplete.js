/**
 * searchAutocomplete.js - ملف JavaScript لتنفيذ البحث التلقائي والإكمال التلقائي
 */

document.addEventListener('DOMContentLoaded', function() {
    // تهيئة البحث التلقائي
    initializeAdvancedSearch();
});

/**
 * تهيئة خاصية البحث التلقائي المتطور
 */
function initializeAdvancedSearch() {
    // البحث عن جميع حقول البحث في الصفحة
    const searchInputs = document.querySelectorAll('.advanced-search-input');
    
    searchInputs.forEach(input => {
        // إنشاء عنصر لعرض نتائج البحث التلقائي
        const searchDropdown = document.createElement('div');
        searchDropdown.className = 'search-dropdown';
        
        // إنشاء رأس للبحث مع الفلاتر
        const searchHeader = document.createElement('div');
        searchHeader.className = 'search-dropdown-header';
        searchHeader.innerHTML = `
            <div class="search-filters">
                <button type="button" class="search-filter-btn active" data-method="trade">البحث من البداية</button>
                <button type="button" class="search-filter-btn" data-method="trade_any">أي موضع</button>
                <button type="button" class="search-filter-btn" data-method="google">طريقة جوجل</button>
            </div>
        `;
        
        // إنشاء محتوى البحث
        const searchContent = document.createElement('div');
        searchContent.className = 'search-dropdown-content';
        
        // إنشاء تذييل البحث
        const searchFooter = document.createElement('div');
        searchFooter.className = 'search-footer';
        searchFooter.innerHTML = `
            <a href="search_ui.php">
                <i class="fas fa-search-plus me-1"></i> بحث متقدم
            </a>
        `;
        
        // إضافة العناصر إلى الحاوية
        searchDropdown.appendChild(searchHeader);
        searchDropdown.appendChild(searchContent);
        searchDropdown.appendChild(searchFooter);
        
        // إضافة الحاوية للصفحة
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(searchDropdown);
        
        // طريقة بحث حالية
        let currentMethod = 'trade';
        
        // إضافة مستمعات الأحداث لأزرار الفلتر
        const filterButtons = searchHeader.querySelectorAll('.search-filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // إزالة الفلتر النشط من جميع الأزرار
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // تنشيط الزر المضغوط
                this.classList.add('active');
                
                // تحديث طريقة البحث الحالية
                currentMethod = this.getAttribute('data-method');
                
                // إعادة البحث باستخدام الطريقة الجديدة
                if (input.value.trim().length >= 2) {
                    fetchSearchResults(input.value.trim(), searchContent, currentMethod);
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
            
            // تغيير placeholder حسب طريقة البحث
            updatePlaceholder(input, currentMethod);
            
            // إرسال طلب AJAX للبحث
            fetchSearchResults(query, searchContent, currentMethod);
            searchDropdown.style.display = 'block';
        }, 300)); // تأخير 300 مللي ثانية للحد من عدد الطلبات
        
        // إخفاء النتائج عند النقر خارجها
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.style.display = 'none';
            }
        });
        
        // تحديد النص عند الضغط على حقل البحث
        input.addEventListener('focus', function() {
            if (this.value.trim().length >= 2) {
                searchDropdown.style.display = 'block';
            }
            
            // تغيير placeholder حسب طريقة البحث
            updatePlaceholder(input, currentMethod);
        });
    });
}

/**
 * جلب نتائج البحث من الخادم
 * @param {string} query - نص البحث
 * @param {HTMLElement} container - عنصر حاوية نتائج البحث
 * @param {string} method - طريقة البحث (trade, trade_any, google, price)
 */
function fetchSearchResults(query, container, method = 'trade') {
    // إظهار مؤشر التحميل
    container.innerHTML = `
        <div class="search-loading">
            <i class="fas fa-spinner fa-spin me-2"></i> جاري البحث...
        </div>
    `;
    
    // إعداد طلب AJAX للبحث
    fetch(`search_ajax.php?q=${encodeURIComponent(query)}&method=${method}&limit=8`)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            if (!data.success || data.results.length === 0) {
                container.innerHTML = `
                    <div class="search-no-results">
                        <i class="fas fa-search fa-2x mb-2 text-muted"></i>
                        <p>لم يتم العثور على نتائج تطابق بحثك</p>
                        <small>
                            جرب 
                            <a href="#" class="try-method" data-method="google">البحث بطريقة جوجل</a> 
                            أو 
                            <a href="#" class="try-method" data-method="trade_any">البحث في أي موضع</a>
                        </small>
                    </div>
                `;
                
                // إضافة مستمعات الأحداث للروابط
                const tryMethodLinks = container.querySelectorAll('.try-method');
                tryMethodLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const methodToTry = this.getAttribute('data-method');
                        
                        // تحديث طريقة البحث النشطة
                        const filterButtons = document.querySelectorAll('.search-filter-btn');
                        filterButtons.forEach(btn => {
                            if (btn.getAttribute('data-method') === methodToTry) {
                                btn.click();
                            }
                        });
                    });
                });
                
                return;
            }
            
            // إظهار عدد النتائج
            const resultsCountEl = document.createElement('div');
            resultsCountEl.className = 'search-results-count';
            resultsCountEl.innerHTML = `
                <small class="text-muted">عدد النتائج: ${data.total}</small>
            `;
            container.appendChild(resultsCountEl);
            
            // عرض نتائج البحث
            data.results.forEach(item => {
                // تحديد نوع المطابقة
                let matchType = item.match_type || 'trade_name';
                let badgeClass = '';
                let badgeText = '';
                
                switch (matchType) {
                    case 'trade_name':
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
                
                // إنشاء نص البحث مع تمييز الكلمات المطابقة
                let displayName = item.trade_name;
                
                // تمييز الكلمات المطابقة فقط في طرق البحث العادية
                if (method !== 'google') {
                    const regex = new RegExp('(' + query.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + ')', 'gi');
                    displayName = displayName.replace(regex, '<span class="search-highlight">$1</span>');
                }
                
                // حساب نسبة الخصم إذا وجدت
                let discountBadge = '';
                if (item.discount !== null) {
                    discountBadge = `<span class="discount-badge">-${item.discount}%</span>`;
                }
                
                // إنشاء عنصر النتيجة
                const resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                resultItem.innerHTML = `
                    <div class="search-result-item-info">
                        <div class="search-result-name">
                            <span class="search-result-badge ${badgeClass}">${badgeText}</span>
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
                            ${item.form || item.strength ? `
                            <div class="search-result-detail">
                                <i class="fas fa-prescription-bottle-alt search-result-detail-icon"></i>
                                ${item.strength} ${item.form}
                            </div>
                            ` : ''}
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
            
            // إذا كان هناك المزيد من النتائج، أضف رابط "عرض المزيد"
            if (data.total > data.results.length) {
                const moreResultsLink = document.createElement('div');
                moreResultsLink.className = 'search-more-results';
                moreResultsLink.innerHTML = `
                    <a href="search_ui.php?q=${encodeURIComponent(query)}&method=${method}" class="more-results-link">
                        <i class="fas fa-search-plus me-1"></i> عرض كل النتائج (${data.total})
                    </a>
                `;
                container.appendChild(moreResultsLink);
            }
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
 * تحديث نص placeholder حسب طريقة البحث
 * @param {HTMLElement} inputElement - عنصر حقل الإدخال
 * @param {string} method - طريقة البحث الحالية
 */
function updatePlaceholder(inputElement, method) {
    switch (method) {
        case 'trade':
            inputElement.placeholder = 'اكتب حرفين من بداية اسم الدواء...';
            break;
        case 'trade_any':
            inputElement.placeholder = 'اكتب أي جزء من اسم الدواء...';
            break;
        case 'google':
            inputElement.placeholder = 'ابحث بأي هجاء تقريبي، يدعم العربية والإنجليزية...';
            break;
        case 'price':
            inputElement.placeholder = 'اسم الدواء | السعر (مثال: panadol | 20-50)';
            break;
        default:
            inputElement.placeholder = 'اسم الدواء أو المادة الفعالة...';
    }
}