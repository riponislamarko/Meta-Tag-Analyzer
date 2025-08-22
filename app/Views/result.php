<?php
// Set page variables
$pageTitle = 'Analysis Results - ' . ($analysisData['meta']['title'] ?? 'Meta Tag Analyzer');
$pageDescription = 'Meta tag analysis results for ' . ($finalUrl ?? $originalUrl ?? '');
$currentPage = 'results';

// Extract data for easier access
$meta = $analysisData['meta'] ?? [];
$og = $analysisData['open_graph'] ?? [];
$twitter = $analysisData['twitter_card'] ?? [];
$headings = $analysisData['headings'] ?? [];
$hreflang = $analysisData['hreflang'] ?? [];
$canonical = $analysisData['canonical'] ?? null;
$favicon = $analysisData['favicon'] ?? null;
$schema = $analysisData['schema_org'] ?? [];
$wordCount = $analysisData['word_count'] ?? 0;
$analysisMeta = $analysisData['analysis_meta'] ?? [];

// Start output buffering for content
ob_start();
?>

<!-- Results Header -->
<section class="bg-light py-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item">
                            <a href="<?= htmlspecialchars($baseUrl ?? '') ?>/" class="text-decoration-none">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        <li class="breadcrumb-item active">Analysis Results</li>
                    </ol>
                </nav>
                
                <h1 class="h3 mb-2">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    Analysis Complete
                </h1>
                
                <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                    <span>
                        <i class="bi bi-link-45deg me-1"></i>
                        <a href="<?= htmlspecialchars($finalUrl ?? $originalUrl ?? '') ?>" 
                           target="_blank" 
                           rel="noopener"
                           class="text-decoration-none">
                            <?= htmlspecialchars(Helpers::truncateText($finalUrl ?? $originalUrl ?? '', 80)) ?>
                        </a>
                    </span>
                    <?php if (isset($fetchData['http_code'])): ?>
                        <span>
                            <i class="bi bi-info-circle me-1"></i>
                            HTTP <?= htmlspecialchars($fetchData['http_code']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($cacheHit ?? false): ?>
                        <span class="badge bg-info">
                            <i class="bi bi-archive me-1"></i>
                            Cached Result
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <div class="btn-group" role="group">
                    <button type="button" 
                            class="btn btn-outline-primary btn-sm"
                            onclick="exportData('json')"
                            title="Export as JSON">
                        <i class="bi bi-filetype-json me-1"></i>
                        JSON
                    </button>
                    <button type="button" 
                            class="btn btn-outline-success btn-sm"
                            onclick="exportData('csv')"
                            title="Export as CSV">
                        <i class="bi bi-filetype-csv me-1"></i>
                        CSV
                    </button>
                    <button type="button" 
                            class="btn btn-outline-secondary btn-sm"
                            onclick="window.print()"
                            title="Print Results">
                        <i class="bi bi-printer me-1"></i>
                        Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Results Content -->
<section class="py-4">
    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                
                <!-- Meta Tags -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-tags-fill me-2"></i>
                            Meta Tags
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($meta['title'])): ?>
                            <div class="mb-3">
                                <strong class="text-primary">Title:</strong>
                                <div class="mt-1 p-2 bg-light rounded">
                                    <?= htmlspecialchars($meta['title']) ?>
                                </div>
                                <small class="text-muted">
                                    Length: <?= strlen($meta['title']) ?> characters
                                    <?php if (strlen($meta['title']) > 60): ?>
                                        <span class="text-warning">⚠️ Too long (recommended: 50-60 chars)</span>
                                    <?php elseif (strlen($meta['title']) < 30): ?>
                                        <span class="text-warning">⚠️ Too short (recommended: 50-60 chars)</span>
                                    <?php else: ?>
                                        <span class="text-success">✓ Good length</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($meta['description'])): ?>
                            <div class="mb-3">
                                <strong class="text-primary">Description:</strong>
                                <div class="mt-1 p-2 bg-light rounded">
                                    <?= htmlspecialchars($meta['description']) ?>
                                </div>
                                <small class="text-muted">
                                    Length: <?= strlen($meta['description']) ?> characters
                                    <?php if (strlen($meta['description']) > 160): ?>
                                        <span class="text-warning">⚠️ Too long (recommended: 150-160 chars)</span>
                                    <?php elseif (strlen($meta['description']) < 120): ?>
                                        <span class="text-warning">⚠️ Too short (recommended: 150-160 chars)</span>
                                    <?php else: ?>
                                        <span class="text-success">✓ Good length</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <?php 
                            $metaFields = [
                                'keywords' => 'Keywords',
                                'robots' => 'Robots',
                                'viewport' => 'Viewport',
                                'author' => 'Author',
                                'generator' => 'Generator',
                                'charset' => 'Charset'
                            ];
                            
                            foreach ($metaFields as $field => $label):
                                if (!empty($meta[$field])):
                            ?>
                                <div class="col-md-6 mb-2">
                                    <strong class="text-muted small"><?= $label ?>:</strong>
                                    <div class="small"><?= htmlspecialchars($meta[$field]) ?></div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Open Graph -->
                <?php if (!empty(array_filter($og))): ?>
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-share-fill me-2"></i>
                            Open Graph
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <?php if (!empty($og['title'])): ?>
                                    <div class="mb-3">
                                        <strong class="text-success">OG Title:</strong>
                                        <div class="mt-1"><?= htmlspecialchars($og['title']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($og['description'])): ?>
                                    <div class="mb-3">
                                        <strong class="text-success">OG Description:</strong>
                                        <div class="mt-1"><?= htmlspecialchars($og['description']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <?php 
                                    $ogFields = [
                                        'type' => 'Type',
                                        'url' => 'URL',
                                        'site_name' => 'Site Name',
                                        'locale' => 'Locale'
                                    ];
                                    
                                    foreach ($ogFields as $field => $label):
                                        if (!empty($og[$field])):
                                    ?>
                                        <div class="col-md-6 mb-2">
                                            <strong class="text-muted small"><?= $label ?>:</strong>
                                            <div class="small">
                                                <?php if ($field === 'url'): ?>
                                                    <a href="<?= htmlspecialchars($og[$field]) ?>" target="_blank" class="text-decoration-none">
                                                        <?= htmlspecialchars(Helpers::truncateText($og[$field], 40)) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($og[$field]) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($og['image'])): ?>
                            <div class="col-md-4">
                                <strong class="text-success">OG Image:</strong>
                                <div class="mt-2">
                                    <img src="<?= htmlspecialchars($og['image']) ?>" 
                                         alt="<?= htmlspecialchars($og['image_alt'] ?? 'Open Graph Image') ?>"
                                         class="img-fluid rounded"
                                         style="max-height: 150px;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="text-muted small mt-1" style="display: none;">
                                        <i class="bi bi-image"></i> Image could not be loaded
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <a href="<?= htmlspecialchars($og['image']) ?>" target="_blank" class="text-decoration-none">
                                            View Full Image
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Twitter Card -->
                <?php if (!empty(array_filter($twitter))): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-twitter me-2"></i>
                            Twitter Card
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <?php 
                                $twitterFields = [
                                    'card' => 'Card Type',
                                    'title' => 'Title',
                                    'description' => 'Description',
                                    'site' => 'Site',
                                    'creator' => 'Creator'
                                ];
                                
                                foreach ($twitterFields as $field => $label):
                                    if (!empty($twitter[$field])):
                                ?>
                                    <div class="mb-2">
                                        <strong class="text-info"><?= $label ?>:</strong>
                                        <span class="ms-2"><?= htmlspecialchars($twitter[$field]) ?></span>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                            
                            <?php if (!empty($twitter['image'])): ?>
                            <div class="col-md-4">
                                <strong class="text-info">Twitter Image:</strong>
                                <div class="mt-2">
                                    <img src="<?= htmlspecialchars($twitter['image']) ?>" 
                                         alt="<?= htmlspecialchars($twitter['image_alt'] ?? 'Twitter Card Image') ?>"
                                         class="img-fluid rounded"
                                         style="max-height: 150px;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="text-muted small mt-1" style="display: none;">
                                        <i class="bi bi-image"></i> Image could not be loaded
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Content Structure -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ol me-2"></i>
                            Content Structure
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-warning">Headings</h6>
                                <?php foreach (['h1', 'h2', 'h3'] as $level): ?>
                                    <?php if (!empty($headings[$level])): ?>
                                        <div class="mb-3">
                                            <strong class="text-muted"><?= strtoupper($level) ?> Tags:</strong>
                                            <ul class="list-unstyled mt-1 ms-3">
                                                <?php foreach ($headings[$level] as $heading): ?>
                                                    <li class="small">
                                                        <i class="bi bi-arrow-right me-1"></i>
                                                        <?= htmlspecialchars(Helpers::truncateText($heading, 80)) ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-warning">Content Metrics</h6>
                                <div class="mb-2">
                                    <strong class="text-muted">Word Count:</strong>
                                    <span class="ms-2"><?= number_format($wordCount) ?> words</span>
                                </div>
                                
                                <?php if (!empty($canonical)): ?>
                                    <div class="mb-2">
                                        <strong class="text-muted">Canonical URL:</strong>
                                        <div class="small mt-1">
                                            <a href="<?= htmlspecialchars($canonical) ?>" target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars(Helpers::truncateText($canonical, 60)) ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($favicon)): ?>
                                    <div class="mb-2">
                                        <strong class="text-muted">Favicon:</strong>
                                        <div class="mt-1">
                                            <img src="<?= htmlspecialchars($favicon) ?>" 
                                                 alt="Favicon" 
                                                 style="width: 16px; height: 16px;"
                                                 class="me-2">
                                            <a href="<?= htmlspecialchars($favicon) ?>" target="_blank" class="text-decoration-none small">
                                                View Favicon
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                
                <!-- Analysis Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Analysis Summary
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (isset($fetchData['fetch_time_ms'])): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Fetch Time:</span>
                                <strong><?= htmlspecialchars(Helpers::formatDuration($fetchData['fetch_time_ms'])) ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($analysisMeta['analysis_time_ms'])): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Analysis Time:</span>
                                <strong><?= htmlspecialchars(Helpers::formatDuration($analysisMeta['analysis_time_ms'])) ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($fetchData['content_length'])): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Content Size:</span>
                                <strong><?= htmlspecialchars(Helpers::formatBytes($fetchData['content_length'])) ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($fetchData['redirect_count']) && $fetchData['redirect_count'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Redirects:</span>
                                <strong><?= htmlspecialchars($fetchData['redirect_count']) ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($fetchData['content_type'])): ?>
                            <div class="mb-2">
                                <span class="text-muted">Content Type:</span>
                                <div class="small"><?= htmlspecialchars($fetchData['content_type']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Schema.org Data -->
                <?php if (!empty($schema)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-diagram-3-fill me-2"></i>
                            Schema.org Data
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">Detected structured data types:</p>
                        <?php foreach ($schema as $schemaType): ?>
                            <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($schemaType) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hreflang -->
                <?php if (!empty($hreflang)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-globe me-2"></i>
                            Hreflang Links
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($hreflang as $lang): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-info"><?= htmlspecialchars($lang['lang']) ?></span>
                                <a href="<?= htmlspecialchars($lang['url']) ?>" 
                                   target="_blank" 
                                   class="text-decoration-none small">
                                    View Page
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-lightning-fill me-2"></i>
                            Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="analyzeAnother()">
                                <i class="bi bi-arrow-left me-1"></i>
                                Analyze Another URL
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="shareResults()">
                                <i class="bi bi-share me-1"></i>
                                Share Results
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="reanalyze()">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Re-analyze (Bypass Cache)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Export Data (Hidden) -->
<div id="exportData" style="display: none;">
    <?= htmlspecialchars(json_encode($analysisData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>
</div>

<script>
// Export functionality
function exportData(format) {
    const data = <?= json_encode($analysisData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const url = '<?= htmlspecialchars($originalUrl ?? '') ?>';
    
    if (format === 'json') {
        exportAsJSON(data, url);
    } else if (format === 'csv') {
        exportAsCSV(data, url);
    }
}

function exportAsJSON(data, url) {
    const jsonData = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonData], { type: 'application/json' });
    const filename = 'meta-analysis-' + generateFilename(url) + '.json';
    downloadBlob(blob, filename);
}

function exportAsCSV(data, url) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv' });
    const filename = 'meta-analysis-' + generateFilename(url) + '.csv';
    downloadBlob(blob, filename);
}

function convertToCSV(data) {
    const rows = [];
    
    function flattenObject(obj, prefix = '') {
        const flattened = {};
        for (const key in obj) {
            if (obj.hasOwnProperty(key)) {
                const newKey = prefix ? `${prefix}.${key}` : key;
                if (typeof obj[key] === 'object' && obj[key] !== null && !Array.isArray(obj[key])) {
                    Object.assign(flattened, flattenObject(obj[key], newKey));
                } else if (Array.isArray(obj[key])) {
                    flattened[newKey] = obj[key].join('; ');
                } else {
                    flattened[newKey] = obj[key];
                }
            }
        }
        return flattened;
    }
    
    const flattened = flattenObject(data);
    const headers = Object.keys(flattened);
    const values = Object.values(flattened);
    
    rows.push(headers.join(','));
    rows.push(values.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','));
    
    return rows.join('\n');
}

function generateFilename(url) {
    try {
        const domain = new URL(url).hostname;
        return domain.replace(/[^a-zA-Z0-9]/g, '-');
    } catch {
        return 'unknown-domain';
    }
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Quick actions
function analyzeAnother() {
    window.location.href = '<?= htmlspecialchars($baseUrl ?? '') ?>/';
}

function shareResults() {
    if (navigator.share) {
        navigator.share({
            title: 'Meta Tag Analysis Results',
            text: 'Check out these meta tag analysis results',
            url: window.location.href
        });
    } else {
        // Fallback: copy URL to clipboard
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Results URL copied to clipboard!');
        });
    }
}

function reanalyze() {
    const url = '<?= htmlspecialchars($originalUrl ?? '') ?>';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= htmlspecialchars($baseUrl ?? '') ?>/analyze.php';
    
    const urlInput = document.createElement('input');
    urlInput.type = 'hidden';
    urlInput.name = 'url';
    urlInput.value = url;
    
    const bypassInput = document.createElement('input');
    bypassInput.type = 'hidden';
    bypassInput.name = 'bypass_cache';
    bypassInput.value = '1';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    
    form.appendChild(urlInput);
    form.appendChild(bypassInput);
    form.appendChild(csrfInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
// Get content and clean buffer
$content = ob_get_clean();

// Include layout
include __DIR__ . '/layout.php';
?>