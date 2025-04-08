<?php
// includes/header.php

// تعريف المتغيرات الافتراضية إذا لم تكن معرفة من قبل
if (!isset($siteName)) {
    $siteName = 'دواؤك';
}

if (!isset($headerUseAiSearch)) {
    $headerUseAiSearch = false;
}

// التحقق من وجود تكوين الذكاء الاصطناعي
if (file_exists(dirname(__FILE__) . '/../ai_config.php')) {
    require_once dirname(__FILE__) . '/../ai_config.php';
    $headerUseAiSearch = isAISearchEnabled();
}

// التأكد من تعريف متغير الصفحة الحالية
if (!isset($currentPage)) {
    $currentPage = '';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
    // إذا كان هناك كائن SEO، استخدمه لتوليد العلامات
    if (isset($seo) && $seo instanceof SEO) {
        echo $seo->generate();
    } else {
        // علامات افتراضية إذا لم يتم تعيين SEO
        echo "<title>" . (isset($pageTitle) ? $pageTitle . ' | ' : '') . "دواؤك - دليلك الشامل للأدوية</title>";
        echo "<meta name='description' content='دليلك الشامل للأدوية في مصر، ابحث وقارن واطلع على المعلومات التفصيلية عن الأدوية' />";
    }
    ?>
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Cairo Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- البحث المتطور CSS -->
    <link rel="stylesheet" href="assets/css/search-styles.css">
    <link rel="icon" type="image/png" href="../images/favicon.png">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-capsules me-2"></i><?php echo $siteName; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'home' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-home me-1"></i> الرئيسية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'search' ? 'active' : ''; ?>" href="search_ui.php">
                            <i class="fas fa-search-plus me-1"></i> البحث المتطور
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'compare' ? 'active' : ''; ?>" href="compare.php">
                            <i class="fas fa-balance-scale me-1"></i> مقارنة الأدوية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'categories' ? 'active' : ''; ?>" href="categories.php">
                            <i class="fas fa-th-list me-1"></i> التصنيفات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'guide' ? 'active' : ''; ?>" href="guide.php">
                            <i class="fas fa-book-medical me-1"></i> الدليل الدوائي
                        </a>
                    </li>
                    <?php if ($headerUseAiSearch): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage == 'ai_search' ? 'active' : ''; ?>" href="search.php">
                            <i class="fas fa-robot me-1"></i> البحث الذكي
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <form class="d-flex" action="search_ui.php" method="GET">
                    <div class="input-group position-relative">
                        <input 
                            type="search" 
                            class="form-control advanced-search-input" 
                            placeholder="ابحث عن اسم دواء..." 
                            name="q"
                            required
                            autocomplete="off"
                        >
                        <button class="btn btn-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <input type="hidden" name="method" value="trade">
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="container py-4">