/*!
 * Meta Tag Analyzer - JavaScript
 * 
 * Handles form submission, AJAX requests, user interactions,
 * and enhanced user experience features.
 */

(function() {
    'use strict';

    // App configuration
    const Config = {
        apiEndpoint: '/api/analyze',
        maxRetries: 3,
        retryDelay: 1000,
        timeouts: {
            analysis: 30000, // 30 seconds
            request: 15000   // 15 seconds
        }
    };

    // Utility functions
    const Utils = {
        /**
         * Debounce function to limit function calls
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info', duration = 5000) {
            const toastContainer = this.getToastContainer();
            const toast = this.createToast(message, type);
            
            toastContainer.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove
            setTimeout(() => this.removeToast(toast), duration);
        },

        /**
         * Get or create toast container
         */
        getToastContainer: function() {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'position-fixed top-0 end-0 p-3';
                container.style.zIndex = '1055';
                document.body.appendChild(container);
            }
            return container;
        },

        /**
         * Create toast element
         */
        createToast: function(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${this.escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
                </div>
            `;
            
            return toast;
        },

        /**
         * Remove toast
         */
        removeToast: function(toast) {
            if (toast && toast.parentNode) {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Validate URL format
         */
        isValidUrl: function(string) {
            try {
                const url = new URL(string);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch (_) {
                return false;
            }
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                return navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                return Promise.resolve();
            }
        },

        /**
         * Format duration
         */
        formatDuration: function(ms) {
            if (ms < 1000) return `${Math.round(ms)}ms`;
            return `${(ms / 1000).toFixed(2)}s`;
        },

        /**
         * Format file size
         */
        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }
    };

    // Form handler
    const FormHandler = {
        init: function() {
            const form = document.getElementById('analysisForm');
            if (form) {
                this.bindEvents(form);
                this.setupExampleUrls();
                this.setupUrlValidation();
            }
        },

        bindEvents: function(form) {
            form.addEventListener('submit', this.handleSubmit.bind(this));
        },

        setupExampleUrls: function() {
            const exampleButtons = document.querySelectorAll('.example-url');
            exampleButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = button.getAttribute('data-url');
                    const urlInput = document.getElementById('urlInput');
                    if (urlInput && url) {
                        urlInput.value = url;
                        urlInput.focus();
                        
                        // Add visual feedback
                        button.classList.add('btn-primary');
                        button.classList.remove('btn-outline-secondary');
                        
                        setTimeout(() => {
                            button.classList.remove('btn-primary');
                            button.classList.add('btn-outline-secondary');
                        }, 500);
                    }
                });
            });
        },

        setupUrlValidation: function() {
            const urlInput = document.getElementById('urlInput');
            if (urlInput) {
                const debouncedValidation = Utils.debounce(this.validateUrl.bind(this), 500);
                urlInput.addEventListener('input', debouncedValidation);
                urlInput.addEventListener('blur', this.validateUrl.bind(this));
            }
        },

        validateUrl: function(event) {
            const input = event.target;
            const url = input.value.trim();
            
            // Remove existing validation classes
            input.classList.remove('is-valid', 'is-invalid');
            
            // Clear existing feedback
            const existingFeedback = input.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
            
            if (url && !Utils.isValidUrl(url)) {
                input.classList.add('is-invalid');
                this.showValidationError(input, 'Please enter a valid HTTP or HTTPS URL');
            } else if (url) {
                input.classList.add('is-valid');
            }
        },

        showValidationError: function(input, message) {
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = message;
            input.parentNode.appendChild(feedback);
        },

        handleSubmit: function(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const url = formData.get('url');
            
            if (!url || !Utils.isValidUrl(url)) {
                Utils.showToast('Please enter a valid URL', 'danger');
                return;
            }
            
            this.showLoading();
            this.performAnalysis(formData);
        },

        showLoading: function() {
            const btn = document.getElementById('analyzeBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner-border');
            
            if (btn && btnText && spinner) {
                btn.disabled = true;
                btnText.textContent = 'Analyzing...';
                spinner.classList.remove('d-none');
                
                // Add progress indicator
                this.showProgressIndicator();
            }
        },

        hideLoading: function() {
            const btn = document.getElementById('analyzeBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner-border');
            
            if (btn && btnText && spinner) {
                btn.disabled = false;
                btnText.textContent = 'Analyze URL';
                spinner.classList.add('d-none');
                
                // Remove progress indicator
                this.hideProgressIndicator();
            }
        },

        showProgressIndicator: function() {
            // Create progress bar if it doesn't exist
            let progressContainer = document.getElementById('analysis-progress');
            if (!progressContainer) {
                progressContainer = document.createElement('div');
                progressContainer.id = 'analysis-progress';
                progressContainer.className = 'analysis-progress mt-3';
                progressContainer.innerHTML = '<div class="analysis-progress-bar"></div>';
                
                const form = document.getElementById('analysisForm');
                if (form) {
                    form.appendChild(progressContainer);
                }
            }
        },

        hideProgressIndicator: function() {
            const progressContainer = document.getElementById('analysis-progress');
            if (progressContainer) {
                progressContainer.remove();
            }
        },

        performAnalysis: function(formData) {
            const url = formData.get('url');
            const bypassCache = formData.get('bypass_cache') ? '1' : '0';
            const includeRawHtml = formData.get('include_raw_html') ? '1' : '0';
            
            // Use regular form submission for now (can be enhanced with AJAX later)
            const form = document.getElementById('analysisForm');
            if (form) {
                form.submit();
            }
        }
    };

    // AJAX handler for API calls
    const ApiHandler = {
        analyzeUrl: function(url, options = {}) {
            const params = new URLSearchParams({
                url: url,
                ...(options.bypassCache && { bypass_cache: '1' }),
                ...(options.includeRawHtml && { include_raw_html: '1' })
            });
            
            return fetch(`${Config.apiEndpoint}?${params}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                timeout: Config.timeouts.analysis
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            });
        }
    };

    // Export functionality
    const ExportHandler = {
        init: function() {
            // This will be called from the results page
            window.exportData = this.exportData.bind(this);
        },

        exportData: function(format, data) {
            if (!data) {
                // Try to get data from page
                const exportDataElement = document.getElementById('exportData');
                if (exportDataElement) {
                    try {
                        data = JSON.parse(exportDataElement.textContent);
                    } catch (e) {
                        Utils.showToast('Failed to export data: Invalid data format', 'danger');
                        return;
                    }
                } else {
                    Utils.showToast('No data available for export', 'warning');
                    return;
                }
            }
            
            try {
                if (format === 'json') {
                    this.exportAsJSON(data);
                } else if (format === 'csv') {
                    this.exportAsCSV(data);
                } else {
                    throw new Error('Unsupported export format');
                }
                
                Utils.showToast(`Data exported as ${format.toUpperCase()}`, 'success');
            } catch (error) {
                Utils.showToast(`Export failed: ${error.message}`, 'danger');
            }
        },

        exportAsJSON: function(data) {
            const jsonData = JSON.stringify(data, null, 2);
            const blob = new Blob([jsonData], { type: 'application/json' });
            const filename = this.generateFilename(data.meta?.title || 'analysis', 'json');
            this.downloadBlob(blob, filename);
        },

        exportAsCSV: function(data) {
            const csv = this.convertToCSV(data);
            const blob = new Blob([csv], { type: 'text/csv' });
            const filename = this.generateFilename(data.meta?.title || 'analysis', 'csv');
            this.downloadBlob(blob, filename);
        },

        convertToCSV: function(data) {
            const rows = [];
            const flatData = this.flattenObject(data);
            
            // Headers
            rows.push(Object.keys(flatData).join(','));
            
            // Values
            const values = Object.values(flatData).map(value => {
                if (value === null || value === undefined) return '';
                return `"${String(value).replace(/"/g, '""')}"`;
            });
            rows.push(values.join(','));
            
            return rows.join('\n');
        },

        flattenObject: function(obj, prefix = '') {
            const flattened = {};
            
            for (const key in obj) {
                if (obj.hasOwnProperty(key)) {
                    const newKey = prefix ? `${prefix}.${key}` : key;
                    const value = obj[key];
                    
                    if (value === null || value === undefined) {
                        flattened[newKey] = '';
                    } else if (Array.isArray(value)) {
                        flattened[newKey] = value.join('; ');
                    } else if (typeof value === 'object') {
                        Object.assign(flattened, this.flattenObject(value, newKey));
                    } else {
                        flattened[newKey] = value;
                    }
                }
            }
            
            return flattened;
        },

        generateFilename: function(title, extension) {
            const safeName = title.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
            const timestamp = new Date().toISOString().slice(0, 10);
            return `meta-analysis-${safeName}-${timestamp}.${extension}`;
        },

        downloadBlob: function(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.style.display = 'none';
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            // Clean up
            setTimeout(() => URL.revokeObjectURL(url), 100);
        }
    };

    // Dark mode handler
    const DarkModeHandler = {
        init: function() {
            this.loadPreference();
            this.setupToggle();
        },

        loadPreference: function() {
            const darkMode = localStorage.getItem('darkMode') === 'true';
            if (darkMode) {
                document.body.classList.add('dark-mode');
            }
        },

        setupToggle: function() {
            const toggle = document.getElementById('darkModeToggle');
            if (toggle) {
                toggle.addEventListener('click', this.toggle.bind(this));
            }
        },

        toggle: function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
            
            Utils.showToast(
                `Switched to ${isDark ? 'dark' : 'light'} mode`,
                'info',
                2000
            );
        }
    };

    // Accessibility enhancements
    const AccessibilityHandler = {
        init: function() {
            this.setupKeyboardNavigation();
            this.setupFocusManagement();
            this.setupScreenReaderSupport();
        },

        setupKeyboardNavigation: function() {
            // Handle Escape key to close modals/dropdowns
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    // Close any open modals or dropdowns
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    });
                }
            });
        },

        setupFocusManagement: function() {
            // Ensure proper focus management for dynamic content
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList') {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                this.enhanceNewContent(node);
                            }
                        });
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        enhanceNewContent: function(element) {
            // Add proper ARIA labels to new elements
            const buttons = element.querySelectorAll('button:not([aria-label])');
            buttons.forEach(button => {
                if (!button.getAttribute('aria-label') && button.textContent.trim()) {
                    button.setAttribute('aria-label', button.textContent.trim());
                }
            });
        },

        setupScreenReaderSupport: function() {
            // Add live region for dynamic updates
            let liveRegion = document.getElementById('sr-live-region');
            if (!liveRegion) {
                liveRegion = document.createElement('div');
                liveRegion.id = 'sr-live-region';
                liveRegion.setAttribute('aria-live', 'polite');
                liveRegion.setAttribute('aria-atomic', 'true');
                liveRegion.className = 'sr-only';
                document.body.appendChild(liveRegion);
            }
            
            // Function to announce messages to screen readers
            window.announceToScreenReader = function(message) {
                liveRegion.textContent = message;
                setTimeout(() => {
                    liveRegion.textContent = '';
                }, 1000);
            };
        }
    };

    // Performance monitoring
    const PerformanceMonitor = {
        init: function() {
            this.monitorPageLoad();
            this.monitorUserInteractions();
        },

        monitorPageLoad: function() {
            window.addEventListener('load', () => {
                if ('performance' in window) {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        console.log('Page load time:', perfData.loadEventEnd - perfData.loadEventStart + 'ms');
                    }
                }
            });
        },

        monitorUserInteractions: function() {
            // Monitor long tasks that might affect user experience
            if ('PerformanceObserver' in window) {
                try {
                    const observer = new PerformanceObserver((list) => {
                        const entries = list.getEntries();
                        entries.forEach((entry) => {
                            if (entry.duration > 50) {
                                console.warn('Long task detected:', entry.duration + 'ms');
                            }
                        });
                    });
                    observer.observe({ entryTypes: ['longtask'] });
                } catch (e) {
                    // PerformanceObserver not supported or other error
                }
            }
        }
    };

    // Initialize app when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Meta Tag Analyzer - JavaScript Initialized');
        
        // Initialize all modules
        FormHandler.init();
        ExportHandler.init();
        DarkModeHandler.init();
        AccessibilityHandler.init();
        PerformanceMonitor.init();
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('JavaScript error:', e.error);
            Utils.showToast('An unexpected error occurred', 'danger');
        });
        
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
            Utils.showToast('A network or processing error occurred', 'warning');
        });
    });

    // Expose utilities globally for use in templates
    window.MetaAnalyzer = {
        Utils: Utils,
        ApiHandler: ApiHandler,
        ExportHandler: ExportHandler
    };

})();