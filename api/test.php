<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار API قاعدة بيانات الأدوية</title>
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        pre {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            overflow: auto;
        }
        
        .endpoint {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        
        .test-btn {
            min-width: 120px;
        }
        
        .response-container {
            margin-top: 15px;
            display: none;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: #0d6efd;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">اختبار API قاعدة بيانات الأدوية</h1>
        
        <div class="alert alert-info">
            <strong>ملاحظة:</strong> استخدم هذه الصفحة لاختبار نقاط نهاية API المختلفة.
        </div>
        
        <!-- البحث عن الأدوية -->
        <div class="endpoint">
            <h3>البحث عن الأدوية</h3>
            <form id="searchForm" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="searchQuery" placeholder="كلمة البحث" required>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="searchMethod">
                            <option value="trade_name">البحث من بداية الاسم</option>
                            <option value="trade_anypos">البحث من أي موضع</option>
                            <option value="google_style">البحث بطريقة جوجل</option>
                            <option value="combined">البحث الشامل</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" id="searchLimit" placeholder="الحد" value="5">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary test-btn">اختبار</button>
                    </div>
                </div>
            </form>
            <div class="response-container" id="searchResponse">
                <h5>الاستجابة:</h5>
                <pre></pre>
            </div>
        </div>
        
        <!-- تفاصيل دواء -->
        <div class="endpoint">
            <h3>تفاصيل دواء</h3>
            <form id="detailsForm" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="number" class="form-control" id="medicationId" placeholder="معرف الدواء" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary test-btn">اختبار</button>
                    </div>
                </div>
            </form>
            <div class="response-container" id="detailsResponse">
                <h5>الاستجابة:</h5>
                <pre></pre>
            </div>
        </div>
        
        <!-- مقارنة الأدوية -->
        <div class="endpoint">
            <h3>مقارنة الأدوية</h3>
            <form id="compareForm" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="compareIds" placeholder="معرفات الأدوية (مفصولة بفواصل)" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary test-btn">اختبار</button>
                    </div>
                </div>
            </form>
            <div class="response-container" id="compareResponse">
                <h5>الاستجابة:</h5>
                <pre></pre>
            </div>
        </div>
        
        <!-- الإحصائيات -->
        <div class="endpoint">
            <h3>الإحصائيات</h3>
            <form id="statsForm" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary test-btn">اختبار</button>
                    </div>
                </div>
            </form>
            <div class="response-container" id="statsResponse">
                <h5>الاستجابة:</h5>
                <pre></pre>
            </div>
        </div>
        
        <!-- قائمة التصنيفات -->
        <div class="endpoint">
            <h3>قائمة التصنيفات</h3>
            <form id="categoriesForm" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary test-btn">اختبار</button>
                    </div>
                </div>
            </form>
            <div class="response-container" id="categoriesResponse">
                <h5>الاستجابة:</h5>
                <pre></pre>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // اختبار البحث عن الأدوية
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const query = document.getElementById('searchQuery').value;
                const method = document.getElementById('searchMethod').value;
                const limit = document.getElementById('searchLimit').value;
                
                testEndpoint(
                    `/api/medications/search?q=${encodeURIComponent(query)}&method=${method}&limit=${limit}`,
                    'searchResponse'
                );
            });
            
            // اختبار تفاصيل دواء
            document.getElementById('detailsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const id = document.getElementById('medicationId').value;
                
                testEndpoint(
                    `/api/medications/${id}`,
                    'detailsResponse'
                );
            });
            
            // اختبار مقارنة الأدوية
            document.getElementById('compareForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const ids = document.getElementById('compareIds').value;
                
                testEndpoint(
                    `/api/medications/compare?ids=${ids}`,
                    'compareResponse'
                );
            });
            
            // اختبار الإحصائيات
            document.getElementById('statsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                testEndpoint(
                    `/api/statistics`,
                    'statsResponse'
                );
            });
            
            // اختبار قائمة التصنيفات
            document.getElementById('categoriesForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                testEndpoint(
                    `/api/categories`,
                    'categoriesResponse'
                );
            });
            
            // دالة لاختبار نقطة نهاية API
            function testEndpoint(url, responseContainerId) {
                const responseContainer = document.getElementById(responseContainerId);
                const pre = responseContainer.querySelector('pre');
                
                // عرض حاوية الاستجابة وإضافة مؤشر التحميل
                responseContainer.style.display = 'block';
                pre.innerHTML = '<span class="loading"></span> جاري تنفيذ الطلب...';
                
                // إرسال الطلب
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        // عرض البيانات بتنسيق مناسب
                        pre.innerHTML = JSON.stringify(data, null, 2);
                    })
                    .catch(error => {
                        pre.innerHTML = `Error: ${error.message}`;
                    });
            }
        });
    </script>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>