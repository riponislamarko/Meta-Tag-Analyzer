<?php
/**
 * Analyzer Class
 * 
 * Extracts meta tags, Open Graph data, Twitter cards, schema.org data,
 * headings, and other SEO-relevant information from HTML content.
 */

class Analyzer
{
    private $dom;
    private $xpath;
    private $baseUrl;
    private $httpClient;
    
    public function __construct($baseUrl = null)
    {
        $this->baseUrl = $baseUrl;
        $this->httpClient = new HttpClient();
        
        // Configure DOMDocument for HTML5 parsing
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
        
        // Suppress HTML parsing errors
        libxml_use_internal_errors(true);
    }
    
    /**
     * Analyze HTML content and extract all relevant data
     */
    public function analyze($html, $baseUrl = null)
    {
        if ($baseUrl) {
            $this->baseUrl = $baseUrl;
        }
        
        $startTime = microtime(true);
        
        // Load HTML into DOM
        $this->loadHtml($html);
        
        // Initialize XPath
        $this->xpath = new DOMXPath($this->dom);
        
        // Extract all data
        $analysis = [
            'meta' => $this->extractMetaTags(),
            'open_graph' => $this->extractOpenGraph(),
            'twitter_card' => $this->extractTwitterCard(),
            'headings' => $this->extractHeadings(),
            'hreflang' => $this->extractHreflang(),
            'canonical' => $this->extractCanonical(),
            'favicon' => $this->extractFavicon(),
            'schema_org' => $this->extractSchemaOrg(),
            'word_count' => $this->calculateWordCount($html),
            'analysis_meta' => [
                'analysis_time_ms' => round((microtime(true) - $startTime) * 1000),
                'html_size_bytes' => strlen($html),
                'dom_elements_count' => $this->dom->getElementsByTagName('*')->length
            ]
        ];
        
        return $analysis;
    }
    
    /**
     * Load HTML content into DOM
     */
    private function loadHtml($html)
    {
        // Clean HTML before parsing
        $html = Helpers::cleanHtml($html);
        
        // Add DOCTYPE if missing for better parsing
        if (stripos($html, '<!DOCTYPE') === false) {
            $html = '<!DOCTYPE html>' . $html;
        }
        
        // Load HTML
        $success = $this->dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        if (!$success) {
            $errors = libxml_get_errors();
            $errorMessages = array_map(function($error) {
                return trim($error->message);
            }, $errors);
            
            logMessage('WARN', 'HTML parsing errors', [
                'errors' => $errorMessages,
                'html_length' => strlen($html)
            ]);
            
            libxml_clear_errors();
        }
    }
    
    /**
     * Extract meta tags
     */
    private function extractMetaTags()
    {
        $meta = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'robots' => null,
            'viewport' => null,
            'author' => null,
            'generator' => null,
            'theme_color' => null,
            'charset' => null
        ];
        
        // Extract title
        $titleNodes = $this->xpath->query('//title');
        if ($titleNodes->length > 0) {
            $meta['title'] = trim($titleNodes->item(0)->textContent);
        }
        
        // Extract meta tags
        $metaNodes = $this->xpath->query('//meta[@name or @property or @http-equiv or @charset]');
        
        foreach ($metaNodes as $metaNode) {
            $name = null;
            $content = null;
            
            // Get name attribute (name, property, or http-equiv)
            if ($metaNode->hasAttribute('name')) {
                $name = strtolower($metaNode->getAttribute('name'));
                $content = $metaNode->getAttribute('content');
            } elseif ($metaNode->hasAttribute('property')) {
                $name = strtolower($metaNode->getAttribute('property'));
                $content = $metaNode->getAttribute('content');
            } elseif ($metaNode->hasAttribute('http-equiv')) {
                $name = strtolower($metaNode->getAttribute('http-equiv'));
                $content = $metaNode->getAttribute('content');
            } elseif ($metaNode->hasAttribute('charset')) {
                $name = 'charset';
                $content = $metaNode->getAttribute('charset');
            }
            
            if (!$name || !$content) {
                continue;
            }
            
            // Map common meta tags
            switch ($name) {
                case 'description':
                    $meta['description'] = trim($content);
                    break;
                case 'keywords':
                    $meta['keywords'] = trim($content);
                    break;
                case 'robots':
                    $meta['robots'] = trim($content);
                    break;
                case 'viewport':
                    $meta['viewport'] = trim($content);
                    break;
                case 'author':
                    $meta['author'] = trim($content);
                    break;
                case 'generator':
                    $meta['generator'] = trim($content);
                    break;
                case 'theme-color':
                    $meta['theme_color'] = trim($content);
                    break;
                case 'charset':
                    $meta['charset'] = trim($content);
                    break;
            }
        }
        
        return $meta;
    }
    
    /**
     * Extract Open Graph data
     */
    private function extractOpenGraph()
    {
        $og = [
            'title' => null,
            'description' => null,
            'type' => null,
            'url' => null,
            'image' => null,
            'image_alt' => null,
            'site_name' => null,
            'locale' => null
        ];
        
        $ogNodes = $this->xpath->query('//meta[starts-with(@property, "og:")]');
        
        foreach ($ogNodes as $ogNode) {
            $property = $ogNode->getAttribute('property');
            $content = trim($ogNode->getAttribute('content'));
            
            if (empty($content)) {
                continue;
            }
            
            switch ($property) {
                case 'og:title':
                    $og['title'] = $content;
                    break;
                case 'og:description':
                    $og['description'] = $content;
                    break;
                case 'og:type':
                    $og['type'] = $content;
                    break;
                case 'og:url':
                    $og['url'] = $content;
                    break;
                case 'og:image':
                    if (!$og['image']) { // Take the first image
                        $og['image'] = $this->resolveUrl($content);
                    }
                    break;
                case 'og:image:alt':
                    $og['image_alt'] = $content;
                    break;
                case 'og:site_name':
                    $og['site_name'] = $content;
                    break;
                case 'og:locale':
                    $og['locale'] = $content;
                    break;
            }
        }
        
        return $og;
    }
    
    /**
     * Extract Twitter Card data
     */
    private function extractTwitterCard()
    {
        $twitter = [
            'card' => null,
            'title' => null,
            'description' => null,
            'image' => null,
            'image_alt' => null,
            'site' => null,
            'creator' => null
        ];
        
        $twitterNodes = $this->xpath->query('//meta[starts-with(@name, "twitter:")]');
        
        foreach ($twitterNodes as $twitterNode) {
            $name = $twitterNode->getAttribute('name');
            $content = trim($twitterNode->getAttribute('content'));
            
            if (empty($content)) {
                continue;
            }
            
            switch ($name) {
                case 'twitter:card':
                    $twitter['card'] = $content;
                    break;
                case 'twitter:title':
                    $twitter['title'] = $content;
                    break;
                case 'twitter:description':
                    $twitter['description'] = $content;
                    break;
                case 'twitter:image':
                    if (!$twitter['image']) { // Take the first image
                        $twitter['image'] = $this->resolveUrl($content);
                    }
                    break;
                case 'twitter:image:alt':
                    $twitter['image_alt'] = $content;
                    break;
                case 'twitter:site':
                    $twitter['site'] = $content;
                    break;
                case 'twitter:creator':
                    $twitter['creator'] = $content;
                    break;
            }
        }
        
        return $twitter;
    }
    
    /**
     * Extract headings (H1-H3)
     */
    private function extractHeadings()
    {
        $headings = [
            'h1' => [],
            'h2' => [],
            'h3' => []
        ];
        
        $maxPerLevel = Config::get('ANALYSIS.MAX_HEADINGS_PER_LEVEL', 3);
        
        for ($level = 1; $level <= 3; $level++) {
            $headingNodes = $this->xpath->query("//h{$level}");
            $count = 0;
            
            foreach ($headingNodes as $headingNode) {
                if ($count >= $maxPerLevel) {
                    break;
                }
                
                $text = trim($headingNode->textContent);
                if (!empty($text)) {
                    $headings["h{$level}"][] = $text;
                    $count++;
                }
            }
        }
        
        return $headings;
    }
    
    /**
     * Extract hreflang links
     */
    private function extractHreflang()
    {
        $hreflang = [];
        
        $hreflangNodes = $this->xpath->query('//link[@rel="alternate" and @hreflang]');
        
        foreach ($hreflangNodes as $link) {
            $lang = $link->getAttribute('hreflang');
            $href = $link->getAttribute('href');
            
            if (!empty($lang) && !empty($href)) {
                $hreflang[] = [
                    'lang' => $lang,
                    'url' => $this->resolveUrl($href)
                ];
            }
        }
        
        return $hreflang;
    }
    
    /**
     * Extract canonical URL
     */
    private function extractCanonical()
    {
        $canonicalNodes = $this->xpath->query('//link[@rel="canonical"]');
        
        if ($canonicalNodes->length > 0) {
            $href = $canonicalNodes->item(0)->getAttribute('href');
            return !empty($href) ? $this->resolveUrl($href) : null;
        }
        
        return null;
    }
    
    /**
     * Extract favicon URL
     */
    private function extractFavicon()
    {
        if (!Config::feature('ENABLE_FAVICON_DISCOVERY', true)) {
            return null;
        }
        
        // Look for various favicon link types
        $faviconSelectors = [
            '//link[@rel="icon"]',
            '//link[@rel="shortcut icon"]',
            '//link[@rel="apple-touch-icon"]',
            '//link[@rel="apple-touch-icon-precomposed"]'
        ];
        
        foreach ($faviconSelectors as $selector) {
            $faviconNodes = $this->xpath->query($selector);
            
            foreach ($faviconNodes as $faviconNode) {
                $href = $faviconNode->getAttribute('href');
                if (!empty($href)) {
                    $faviconUrl = $this->resolveUrl($href);
                    
                    // Try to validate the favicon URL
                    try {
                        $validated = $this->httpClient->fetchFavicon($this->baseUrl, $faviconUrl);
                        if ($validated) {
                            return $validated;
                        }
                    } catch (Exception $e) {
                        // Continue to next favicon
                        continue;
                    }
                }
            }
        }
        
        // Try common fallback locations
        if ($this->baseUrl) {
            return $this->httpClient->fetchFavicon($this->baseUrl);
        }
        
        return null;
    }
    
    /**
     * Extract Schema.org structured data
     */
    private function extractSchemaOrg()
    {
        if (!Config::feature('ENABLE_SCHEMA_DETECTION', true)) {
            return [];
        }
        
        $schemas = [];
        
        // Extract JSON-LD
        $jsonLdNodes = $this->xpath->query('//script[@type="application/ld+json"]');
        
        foreach ($jsonLdNodes as $scriptNode) {
            $jsonContent = trim($scriptNode->textContent);
            
            if (!empty($jsonContent)) {
                $decoded = json_decode($jsonContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $types = $this->extractSchemaTypes($decoded);
                    $schemas = array_merge($schemas, $types);
                }
            }
        }
        
        // Extract Microdata (simplified)
        $microdataNodes = $this->xpath->query('//*[@itemtype]');
        
        foreach ($microdataNodes as $node) {
            $itemtype = $node->getAttribute('itemtype');
            if (strpos($itemtype, 'schema.org') !== false) {
                $type = basename($itemtype);
                if (!in_array($type, $schemas, true)) {
                    $schemas[] = $type;
                }
            }
        }
        
        return array_unique($schemas);
    }
    
    /**
     * Extract schema types from JSON-LD data
     */
    private function extractSchemaTypes($data)
    {
        $types = [];
        
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $types = array_merge($types, $this->extractSchemaTypes($item));
                }
            }
        } elseif (is_object($data) || (is_array($data) && !empty($data))) {
            if (isset($data['@type'])) {
                if (is_array($data['@type'])) {
                    $types = array_merge($types, $data['@type']);
                } else {
                    $types[] = $data['@type'];
                }
            }
            
            // Recursively check nested objects
            foreach ($data as $value) {
                if (is_array($value) || is_object($value)) {
                    $types = array_merge($types, $this->extractSchemaTypes($value));
                }
            }
        }
        
        return $types;
    }
    
    /**
     * Calculate word count from HTML content
     */
    private function calculateWordCount($html)
    {
        if (!Config::feature('ENABLE_WORD_COUNT', true)) {
            return 0;
        }
        
        return Helpers::countWords($html);
    }
    
    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl($url)
    {
        if (!$this->baseUrl || empty($url)) {
            return $url;
        }
        
        return Helpers::resolveRelativeUrl($this->baseUrl, $url);
    }
    
    /**
     * Get all links from the page
     */
    public function extractLinks()
    {
        $links = [];
        
        $linkNodes = $this->xpath->query('//a[@href]');
        
        foreach ($linkNodes as $linkNode) {
            $href = $linkNode->getAttribute('href');
            $text = trim($linkNode->textContent);
            
            if (!empty($href)) {
                $links[] = [
                    'url' => $this->resolveUrl($href),
                    'text' => $text,
                    'is_external' => $this->isExternalUrl($href)
                ];
            }
        }
        
        return $links;
    }
    
    /**
     * Check if URL is external
     */
    private function isExternalUrl($url)
    {
        if (!$this->baseUrl) {
            return false;
        }
        
        $baseDomain = Helpers::extractDomain($this->baseUrl);
        $urlDomain = Helpers::extractDomain($url);
        
        return !empty($urlDomain) && $urlDomain !== $baseDomain;
    }
    
    /**
     * Extract all images from the page
     */
    public function extractImages()
    {
        $images = [];
        
        $imgNodes = $this->xpath->query('//img[@src]');
        
        foreach ($imgNodes as $imgNode) {
            $src = $imgNode->getAttribute('src');
            $alt = $imgNode->getAttribute('alt');
            $title = $imgNode->getAttribute('title');
            
            if (!empty($src)) {
                $images[] = [
                    'src' => $this->resolveUrl($src),
                    'alt' => $alt,
                    'title' => $title
                ];
            }
        }
        
        return $images;
    }
    
    /**
     * Get performance insights
     */
    public function getPerformanceInsights($html, $fetchData = [])
    {
        $insights = [];
        
        // HTML size
        $htmlSize = strlen($html);
        $insights['html_size'] = [
            'bytes' => $htmlSize,
            'formatted' => Helpers::formatBytes($htmlSize),
            'recommendation' => $htmlSize > 100000 ? 'Consider reducing HTML size' : 'HTML size is acceptable'
        ];
        
        // Number of DOM elements
        $domElements = $this->dom->getElementsByTagName('*')->length;
        $insights['dom_elements'] = [
            'count' => $domElements,
            'recommendation' => $domElements > 1500 ? 'Consider reducing DOM complexity' : 'DOM complexity is acceptable'
        ];
        
        // External resources count
        $scripts = $this->xpath->query('//script[@src]')->length;
        $stylesheets = $this->xpath->query('//link[@rel="stylesheet"]')->length;
        $images = $this->xpath->query('//img[@src]')->length;
        
        $insights['external_resources'] = [
            'scripts' => $scripts,
            'stylesheets' => $stylesheets,
            'images' => $images,
            'total' => $scripts + $stylesheets + $images
        ];
        
        // Fetch time (if available)
        if (isset($fetchData['fetch_time_ms'])) {
            $insights['fetch_time'] = [
                'milliseconds' => $fetchData['fetch_time_ms'],
                'formatted' => Helpers::formatDuration($fetchData['fetch_time_ms']),
                'recommendation' => $fetchData['fetch_time_ms'] > 3000 ? 'Slow response time' : 'Good response time'
            ];
        }
        
        return $insights;
    }
}