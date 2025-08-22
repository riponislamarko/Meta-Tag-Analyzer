<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Analyze any public URL and extract comprehensive SEO meta data, Open Graph tags, Twitter cards, schema.org data, and more.') ?>">
    <meta name="robots" content="index, follow">
    <title><?= htmlspecialchars($pageTitle ?? 'Meta Tag Analyzer') ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= htmlspecialchars($baseUrl ?? '') ?>/assets/css/app.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($baseUrl ?? '') ?>/assets/img/favicon.svg">
    <link rel="alternate icon" href="<?= htmlspecialchars($baseUrl ?? '') ?>/assets/img/favicon.ico">
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    
    <?php if (isset($additionalHead)): ?>
        <?= $additionalHead ?>
    <?php endif; ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= htmlspecialchars($baseUrl ?? '') ?>/">
                <i class="bi bi-search me-2"></i>
                <strong>Meta Tag Analyzer</strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage ?? '') === 'home' ? 'active' : '' ?>" href="<?= htmlspecialchars($baseUrl ?? '') ?>/">
                            <i class="bi bi-house me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($baseUrl ?? '') ?>/api/analyze?url=https://example.com" target="_blank">
                            <i class="bi bi-code me-1"></i>API
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://github.com" target="_blank" rel="noopener">
                            <i class="bi bi-github me-1"></i>GitHub
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Alert Messages -->
    <?php if (isset($alertMessage) && isset($alertType)): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= htmlspecialchars($alertType) ?> alert-dismissible fade show" role="alert">
                <?php if ($alertType === 'danger'): ?>
                    <i class="bi bi-exclamation-triangle me-2"></i>
                <?php elseif ($alertType === 'success'): ?>
                    <i class="bi bi-check-circle me-2"></i>
                <?php elseif ($alertType === 'warning'): ?>
                    <i class="bi bi-exclamation-circle me-2"></i>
                <?php else: ?>
                    <i class="bi bi-info-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($alertMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="flex-grow-1">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary mb-3">Meta Tag Analyzer</h6>
                    <p class="text-muted small mb-2">
                        A lightweight PHP tool for analyzing SEO meta data, Open Graph tags, 
                        Twitter cards, and more from any public URL.
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-shield-check me-1"></i>
                        Built with security and shared hosting compatibility in mind.
                    </p>
                </div>
                <div class="col-md-3">
                    <h6 class="text-primary mb-3">Features</h6>
                    <ul class="list-unstyled small text-muted">
                        <li><i class="bi bi-check me-1"></i>Meta tag extraction</li>
                        <li><i class="bi bi-check me-1"></i>Open Graph analysis</li>
                        <li><i class="bi bi-check me-1"></i>Twitter card detection</li>
                        <li><i class="bi bi-check me-1"></i>Schema.org discovery</li>
                        <li><i class="bi bi-check me-1"></i>SEO insights</li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 class="text-primary mb-3">Export Options</h6>
                    <ul class="list-unstyled small text-muted">
                        <li><i class="bi bi-filetype-json me-1"></i>JSON format</li>
                        <li><i class="bi bi-filetype-csv me-1"></i>CSV format</li>
                        <li><i class="bi bi-cloud-download me-1"></i>Instant download</li>
                        <li><i class="bi bi-api me-1"></i>RESTful API</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted small mb-0">
                        &copy; <?= date('Y') ?> Meta Tag Analyzer. 
                        Built with <i class="bi bi-heart-fill text-danger"></i> using PHP and Bootstrap.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>
                        Generated in <?= isset($generationTime) ? number_format($generationTime * 1000, 2) : '0' ?>ms
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?= htmlspecialchars($baseUrl ?? '') ?>/assets/js/app.js"></script>
    
    <!-- Dark Mode Toggle Script -->
    <script>
        // Simple dark mode toggle (if implemented)
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    document.body.classList.toggle('dark-mode');
                    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
                });
                
                // Load saved preference
                if (localStorage.getItem('darkMode') === 'true') {
                    document.body.classList.add('dark-mode');
                }
            }
        });
    </script>
    
    <?php if (isset($additionalScripts)): ?>
        <?= $additionalScripts ?>
    <?php endif; ?>
</body>
</html>