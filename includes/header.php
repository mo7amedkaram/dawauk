<?php
// includes/header.php
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
                            <i class="fas fa-search me-1"></i> بحث متقدم
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
                            class="form-control" 
                            placeholder="ابحث عن اسم دواء..." 
                            name="q"
                            required
                        >
                        <button class="btn btn-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="container py-4">