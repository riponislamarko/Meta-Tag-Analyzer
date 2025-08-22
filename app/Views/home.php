<?php
// Set page variables
$pageTitle = 'Meta Tag Analyzer - SEO & Meta Data Analysis Tool';
$pageDescription = 'Analyze any public URL and extract comprehensive SEO meta data, Open Graph tags, Twitter cards, schema.org data, headings, and performance insights.';
$currentPage = 'home';

// Start output buffering for content
ob_start();
?>

<!-- Hero Section -->
<section class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="bi bi-search text-warning me-3"></i>
                    Meta Tag Analyzer
                </h1>
                <p class="lead mb-4">
                    Extract comprehensive SEO and meta data from any public URL. 
                    Analyze Open Graph tags, Twitter cards, schema.org data, headings, and more.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="bi bi-shield-check me-1"></i>SSRF Protected
                    </span>
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="bi bi-lightning me-1"></i>Fast Analysis
                    </span>
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="bi bi-download me-1"></i>Export Ready
                    </span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-white rounded-3 shadow p-4 mt-4 mt-lg-0">
                    <h5 class="text-dark mb-3">
                        <i class="bi bi-link-45deg me-2"></i>
                        Analyze URL
                    </h5>
                    
                    <!-- Analysis Form -->
                    <form id="analysisForm" method="POST" action="<?= htmlspecialchars($baseUrl ?? '') ?>/analyze.php">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                        
                        <div class="input-group mb-3">
                            <span class="input-group-text">
                                <i class="bi bi-globe"></i>
                            </span>
                            <input 
                                type="url" 
                                class="form-control form-control-lg" 
                                name="url" 
                                id="urlInput"
                                placeholder="https://example.com" 
                                required
                                pattern="https?://.*"
                                title="Please enter a valid HTTP or HTTPS URL"
                            >
                        </div>
                        
                        <!-- Analysis Options -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_raw_html" id="includeRawHtml">
                                    <label class="form-check-label small text-muted" for="includeRawHtml">
                                        Include raw HTML
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="bypass_cache" id="bypassCache">
                                    <label class="form-check-label small text-muted" for="bypassCache">
                                        Bypass cache
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-lg w-100" id="analyzeBtn">
                            <i class="bi bi-search me-2"></i>
                            <span class="btn-text">Analyze URL</span>
                            <span class="spinner-border spinner-border-sm d-none ms-2" role="status"></span>
                        </button>
                    </form>
                    
                    <!-- Quick Examples -->
                    <div class="mt-3">
                        <small class="text-muted">Quick examples:</small>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button class="btn btn-outline-secondary btn-sm example-url" data-url="https://github.com">
                                GitHub
                            </button>
                            <button class="btn btn-outline-secondary btn-sm example-url" data-url="https://www.wikipedia.org">
                                Wikipedia
                            </button>
                            <button class="btn btn-outline-secondary btn-sm example-url" data-url="https://developer.mozilla.org">
                                MDN
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="row text-center mb-5">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-6 fw-bold text-primary mb-3">
                    Comprehensive SEO Analysis
                </h2>
                <p class="lead text-muted">
                    Extract and analyze all the essential meta data and SEO information 
                    from any public website with our powerful analysis engine.
                </p>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Meta Tags -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                            <i class="bi bi-tags-fill text-primary fs-3"></i>
                        </div>
                        <h5 class="card-title fw-bold">Meta Tags</h5>
                        <p class="card-text text-muted">
                            Extract title, description, keywords, robots directives, 
                            viewport settings, and other essential meta tags.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Open Graph -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                            <i class="bi bi-share-fill text-success fs-3"></i>
                        </div>
                        <h5 class="card-title fw-bold">Open Graph</h5>
                        <p class="card-text text-muted">
                            Analyze Open Graph meta properties for social media sharing 
                            including title, description, images, and type.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Twitter Cards -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-info bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                            <i class="bi bi-twitter text-info fs-3"></i>
                        </div>
                        <h5 class="card-title fw-bold">Twitter Cards</h5>
                        <p class="card-text text-muted">
                            Extract Twitter Card meta data for optimized 
                            Twitter sharing and rich media previews.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Schema.org -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                            <i class="bi bi-diagram-3-fill text-warning fs-3"></i>
                        </div>
                        <h5 class="card-title fw-bold">Schema.org</h5>
                        <p class="card-text text-muted">
                            Detect structured data markup including JSON-LD 
                            and Microdata for enhanced search results.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Headings & Structure -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-danger bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                            <i class="bi bi-list-ol text-danger fs-3"></i>
                        </div>
                        <h5 class="card-title fw-bold">Content Structure</h5>
                        <p class="card-text text-muted">
                            Analyze heading hierarchy (H1-H3), word count, 
                            and overall content structure for SEO insights.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Performance -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="bg-dark bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                            <i class="bi bi-speedometer2 text-dark fs-3"></i>
                        </div>
                        <h5 class="card-title fw-bold">Performance</h5>
                        <p class="card-text text-muted">
                            Monitor HTTP status, redirect chains, content size, 
                            and response times for performance insights.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- API Section -->
<section class="bg-light py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="display-6 fw-bold text-primary mb-3">
                    <i class="bi bi-code-slash me-2"></i>
                    RESTful API
                </h2>
                <p class="lead text-muted mb-4">
                    Integrate meta tag analysis into your applications with our simple REST API. 
                    Get JSON responses with comprehensive analysis data.
                </p>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Simple GET request interface
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        JSON formatted responses
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        Built-in rate limiting
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        SSRF protection included
                    </li>
                </ul>
                <a href="<?= htmlspecialchars($baseUrl ?? '') ?>/api/analyze?url=https://example.com" 
                   target="_blank" 
                   class="btn btn-outline-primary">
                    <i class="bi bi-play-circle me-2"></i>
                    Try API Demo
                </a>
            </div>
            <div class="col-lg-6">
                <div class="bg-dark rounded-3 p-4 text-light mt-4 mt-lg-0">
                    <h6 class="text-warning mb-3">
                        <i class="bi bi-terminal me-2"></i>
                        Example API Call
                    </h6>
                    <pre class="text-light mb-0"><code>GET <?= htmlspecialchars($baseUrl ?? 'https://yourdomain.com') ?>/api/analyze?url=https://example.com

{
  "success": true,
  "data": {
    "meta": {
      "title": "Example Domain",
      "description": "This domain is for use...",
      "robots": "index, follow"
    },
    "open_graph": {
      "title": "Example Domain",
      "type": "website",
      "url": "https://example.com"
    },
    "analysis_meta": {
      "cache_hit": false,
      "fetch_time_ms": 245
    }
  }
}</code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Export Options -->
<section class="py-5">
    <div class="container">
        <div class="row text-center">
            <div class="col-lg-8 mx-auto">
                <h2 class="display-6 fw-bold text-primary mb-3">
                    <i class="bi bi-download me-2"></i>
                    Export Options
                </h2>
                <p class="lead text-muted mb-5">
                    Download your analysis results in multiple formats for further processing 
                    or integration with your workflow.
                </p>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                                <i class="bi bi-filetype-json text-primary fs-2"></i>
                            </div>
                            <h5 class="fw-bold">JSON Export</h5>
                            <p class="text-muted">
                                Download complete analysis data in JSON format, 
                                perfect for API integration and data processing.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3 mb-3 d-inline-flex">
                                <i class="bi bi-filetype-csv text-success fs-2"></i>
                            </div>
                            <h5 class="fw-bold">CSV Export</h5>
                            <p class="text-muted">
                                Export data in CSV format for spreadsheet analysis 
                                and bulk processing workflows.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Get content and clean buffer
$content = ob_get_clean();

// Include layout
include __DIR__ . '/layout.php';
?>