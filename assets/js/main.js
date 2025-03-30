// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // تهيئة البحث التلقائي
    initializeAutocomplete();
    
    // تفعيل أداة التلميح (tooltip)
    enableTooltips();
});

/**
 * تهيئة خاصية البحث التلقائي في شريط البحث
 */
function initializeAutocomplete() {
    // البحث عن جميع حقول البحث في الصفحة
    const searchInputs = document.querySelectorAll('input[type="search"], .search-autocomplete');
    
    searchInputs.forEach(input => {
        // إنشاء عنصر لعرض نتائج البحث التلقائي
        const searchDropdown = document.createElement('div');
        searchDropdown.className = 'search-dropdown';
        
        // إنشاء رأس للبحث مع الفلاتر
        const searchHeader = document.createElement('div');
        searchHeader.className = 'search-dropdown-header';
        searchHeader.innerHTML = `
            <div class="search-filters">
                <button type="button" class="search-filter-btn active" data-filter="all">الكل</button>
                <button type="button" class="search-filter-btn" data-filter="trade_name">الاسم التجاري</button>
                <button type="button" class="search-filter-btn" data-filter="scientific_name">المادة الفعالة</button>
                <button type="button" class="search-filter-btn" data-filter="company">الشركة المنتجة</button>
            </div>
        `;
        
        // إنشاء محتوى البحث
        const searchContent = document.createElement('div');
        searchContent.className = 'search-dropdown-content';
        
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
        searchDropdown.appendChild(searchFooter);
        
        // إضافة الحاوية للصفحة
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(searchDropdown);
        
        // فلتر بحث حالي
        let currentFilter = 'all';
        
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
                    fetchSearchResults(input.value.trim(), searchContent, currentFilter);
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
            fetchSearchResults(query, searchContent, currentFilter);
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
        });
    });
}

/**
 * جلب نتائج البحث من الخادم
 * @param {string} query - نص البحث
 * @param {HTMLElement} container - عنصر حاوية نتائج البحث
 * @param {string} filter - فلتر البحث (all, trade_name, scientific_name, company)
 */
function fetchSearchResults(query, container, filter = 'all') {
    // تحقق إذا كان البحث يحتوي على علامة النجمة (*)
    const hasWildcard = query.includes('*');
    
    // إعداد طلب AJAX مع فلتر البحث
    fetch(`search_autocomplete.php?q=${encodeURIComponent(query)}&filter=${filter}`)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            if (data.length === 0) {
                container.innerHTML = `
                    <div class="search-no-results">
                        <i class="fas fa-search fa-2x mb-2 text-muted"></i>
                        <p>لم يتم العثور على نتائج تطابق بحثك</p>
                        <small>حاول استخدام كلمات مختلفة أو علامة * بدلاً من الحروف غير المعروفة</small>
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
                
                // إذا كان البحث يحتوي على علامة النجمة، لا تقم بتمييز الكلمات
                if (!hasWildcard) {
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