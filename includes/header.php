<?php
// includes/header.php

// التحقق من وجود تكوين الذكاء الاصطناعي
if (file_exists(dirname(__FILE__) . '/../ai_config.php')) {
    require_once dirname(__FILE__) . '/../ai_config.php';
    $headerUseAiSearch = isAISearchEnabled();
} else {
    $headerUseAiSearch = false;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>منصة الأدوية الشاملة</title>
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
    
    <?php if ($headerUseAiSearch): ?>
    <!-- تنسيقات البحث المعزز بالذكاء الاصطناعي -->
    <style>
    /* زر البحث المعزز بالذكاء الاصطناعي */
    .ai-search-btn {
        background: linear-gradient(135deg, #0d6efd 0%, #198754 100%);
        color: white;
        border: none;
        position: relative;
        overflow: hidden;
        transition: all 0.3s;
    }
    
    .ai-search-btn::before {
        content: '';
        position: absolute;
        top: 0;
        right: -50%;
        width: 150%;
        height: 100%;
        background: rgba(255, 255, 255, 0.2);
        transform: skewX(-25deg);
        transition: all 0.4s;
    }
    
    .ai-search-btn:hover::before {
        right: -180%;
    }
    
    .ai-search-btn:hover {
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        transform: translateY(-2px);
    }
    
    .ai-badge {
        background-color: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
        border: 1px solid rgba(13, 110, 253, 0.2);
        transition: all 0.3s;
    }
    
    .ai-badge i {
        font-size: 0.8rem;
    }
    
    .ai-badge:hover {
        background-color: #0d6efd;
        color: white;
    }
    </style>
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-capsules me-2"></i>دواؤك
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
                        <a class="nav-link <?php echo $currentPage == 'search' ? 'active' : ''; ?>" href="search.php">
                            <i class="fas <?php echo $headerUseAiSearch ? 'fa-robot' : 'fa-search'; ?> me-1"></i> <?php echo $headerUseAiSearch ? 'بحث ذكي' : 'بحث متقدم'; ?>
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
                </ul>
                <form class="d-flex" action="search.php" method="GET">
                    <div class="input-group">
                        <input 
                            type="search" 
                            class="form-control search-autocomplete" 
                            placeholder="<?php echo $headerUseAiSearch ? 'ابحث عن دواء أو اطرح سؤالاً...' : 'ابحث عن اسم دواء...'; ?>" 
                            name="q"
                            required
                        >
                        <button class="btn <?php echo $headerUseAiSearch ? 'ai-search-btn' : 'btn-light'; ?>" type="submit">
                            <i class="fas <?php echo $headerUseAiSearch ? 'fa-robot' : 'fa-search'; ?>"></i>
                        </button>
                    </div>
                    <?php if ($headerUseAiSearch): ?>
                    <div class="ms-2">
                        <span class="badge ai-badge">
                            <i class="fas fa-robot me-1"></i> البحث الذكي مفعّل
                        </span>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="container py-4">