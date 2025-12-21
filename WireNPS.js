/**
 * WireNPS Frontend JavaScript
 * Simple vanilla JS for NPS popup widget
 */

(function() {
    'use strict';
    
    console.log('[WireNPS] Script loaded');
    console.log('[WireNPS] window.wireNPSConfig available:', !!window.wireNPSConfig);
    
    // Config from PHP - always use window.wireNPSConfig directly
    const getConfig = () => window.wireNPSConfig || {
        delay: 5000,
        cookieExpiry: 30,
        ajaxUrl: '/wirenps-submit/',
        pageId: 1, // Fallback page ID
        allowMultiple: false
    };
    
    const config = getConfig();
    
    console.log('[WireNPS] Config loaded:', config);
    
    // State
    let selectedScore = null;
    let retryCount = 0;
    const MAX_RETRIES = 50; // 5 seconds max wait
    
    // Elements (will be set in init)
    let overlay, closeBtn, scoreButtons, feedbackContainer, feedbackTextarea, submitBtn, thankYouDiv;
    
    /**
     * Initialize
     */
    function init() {
        console.log('[WireNPS] Initializing...');
        
        // Set element references
        overlay = document.getElementById('wirenps-overlay');
        closeBtn = document.querySelector('.wirenps-close');
        scoreButtons = document.querySelectorAll('.wirenps-score-btn');
        feedbackContainer = document.querySelector('.wirenps-feedback-container');
        feedbackTextarea = document.getElementById('wirenps-feedback');
        submitBtn = document.getElementById('wirenps-submit');
        thankYouDiv = document.querySelector('.wirenps-thank-you');
        
        console.log('[WireNPS] Elements found:', {
            overlay: !!overlay,
            scoreButtons: scoreButtons.length,
            submitBtn: !!submitBtn
        });
        
        // Check if already submitted via cookie (only if multiple submissions not allowed)
        if(!config.allowMultiple && getCookie('wirenps_submitted')) {
            console.log('[WireNPS] Cookie found and multiple submissions disabled - user already submitted');
            return;
        }
        
        if(config.allowMultiple) {
            console.log('[WireNPS] Multiple submissions allowed - ignoring cookie');
        }
        
        console.log('[WireNPS] No cookie found or multiple allowed - will show popup in ' + config.delay + 'ms');
        
        // Show popup after delay
        setTimeout(showPopup, config.delay);
        
        // Event listeners
        if(closeBtn) {
            closeBtn.addEventListener('click', hidePopup);
        }
        
        if(overlay) {
            overlay.addEventListener('click', function(e) {
                if(e.target === overlay) {
                    hidePopup();
                }
            });
        }
        
        scoreButtons.forEach(function(btn) {
            btn.addEventListener('click', handleScoreClick);
        });
        
        if(submitBtn) {
            submitBtn.addEventListener('click', handleSubmit);
        }
        
        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape' && !overlay.classList.contains('hidden')) {
                hidePopup();
            }
        });
    }
    
    /**
     * Show popup
     */
    function showPopup() {
        console.log('[WireNPS] Showing popup...');
        if(overlay) {
            overlay.classList.remove('hidden');
            overlay.classList.add('wirenps-show');
            console.log('[WireNPS] Popup displayed');
        } else {
            console.error('[WireNPS] Overlay element not found!');
        }
    }
    
    /**
     * Hide popup
     */
    function hidePopup() {
        if(overlay) {
            overlay.classList.add('hidden');
            overlay.classList.remove('wirenps-show');
        }
    }
    
    /**
     * Handle score button click
     */
    function handleScoreClick(e) {
        const btn = e.currentTarget;
        selectedScore = parseInt(btn.dataset.score);
        
        // Remove active state from all buttons
        scoreButtons.forEach(function(b) {
            b.classList.remove('wirenps-active');
        });
        
        // Add active state to clicked button
        btn.classList.add('wirenps-active');
        
        // Show feedback container
        if(feedbackContainer) {
            feedbackContainer.classList.remove('hidden');
            feedbackContainer.classList.add('wirenps-fade-in');
            
            // Auto-focus textarea
            setTimeout(function() {
                if(feedbackTextarea) {
                    feedbackTextarea.focus();
                }
            }, 300);
        }
    }
    
    /**
     * Handle form submission
     */
    function handleSubmit() {
        if(selectedScore === null) {
            alert('Please select a score');
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        
        // Prepare data
        const data = {
            score: selectedScore,
            feedback: feedbackTextarea ? feedbackTextarea.value : '',
            page_id: getPageId()
        };
        
        console.log('[WireNPS] Submitting data:', data);
        
        // Send AJAX request
        sendRating(data);
    }
    
    /**
     * Send rating via AJAX
     */
    function sendRating(data) {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', config.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            console.log('[WireNPS] AJAX Response received');
            console.log('[WireNPS] Status:', xhr.status);
            console.log('[WireNPS] Response Text LENGTH:', xhr.responseText.length);
            console.log('[WireNPS] Response Text:', xhr.responseText);
            console.log('[WireNPS] First char code:', xhr.responseText.charCodeAt(0));
            console.log('[WireNPS] Last char code:', xhr.responseText.charCodeAt(xhr.responseText.length - 1));
            
            if(xhr.status === 200) {
                try {
                    // Try to clean the response - remove BOM, whitespace, etc.
                    let cleanResponse = xhr.responseText.trim();
                    
                    // Remove BOM if present
                    if(cleanResponse.charCodeAt(0) === 0xFEFF) {
                        cleanResponse = cleanResponse.substring(1);
                    }
                    
                    console.log('[WireNPS] Cleaned response:', cleanResponse);
                    
                    const response = JSON.parse(cleanResponse);
                    console.log('[WireNPS] Parsed successfully:', response);
                    
                    if(response.success) {
                        showThankYou(response.message);
                        // Only set cookie if multiple submissions not allowed
                        if(!config.allowMultiple) {
                            setCookie('wirenps_submitted', '1', config.cookieExpiry);
                        }
                    } else {
                        alert('Error: ' + (response.error || 'Unknown error'));
                        submitBtn.disabled = false;
                        submitBtn.textContent = document.querySelector('.wirenps-submit').getAttribute('data-original-text') || 'Submit';
                    }
                } catch(e) {
                    console.error('[WireNPS] Failed to parse response:', e);
                    console.error('[WireNPS] Response was:', xhr.responseText);
                    console.error('[WireNPS] Response bytes:', Array.from(xhr.responseText).map(c => c.charCodeAt(0)));
                    alert('An error occurred. Please try again.');
                    submitBtn.disabled = false;
                }
            } else {
                console.error('[WireNPS] HTTP error:', xhr.status);
                alert('Server error. Please try again later.');
                submitBtn.disabled = false;
            }
        };
        
        xhr.onerror = function() {
            alert('Network error. Please check your connection.');
            submitBtn.disabled = false;
        };
        
        // Add wirenps_action to identify this as WireNPS submission
        data.wirenps_action = 'submit';
        
        // Convert data to URL-encoded format
        const params = Object.keys(data).map(function(key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
        }).join('&');
        
        xhr.send(params);
    }
    
    /**
     * Show thank you message
     */
    function showThankYou(message) {
        // Hide feedback container
        if(feedbackContainer) {
            feedbackContainer.classList.add('hidden');
        }
        
        // Hide score buttons
        const scoresDiv = document.querySelector('.wirenps-scores');
        if(scoresDiv) {
            scoresDiv.classList.add('hidden');
        }
        
        const labelsDiv = document.querySelector('.wirenps-labels');
        if(labelsDiv) {
            labelsDiv.classList.add('hidden');
        }
        
        // Show thank you message
        if(thankYouDiv) {
            if(message) {
                thankYouDiv.querySelector('p').textContent = message;
            }
            thankYouDiv.classList.remove('hidden');
            thankYouDiv.classList.add('wirenps-fade-in');
        }
        
        // Auto-close after 3 seconds
        setTimeout(function() {
            hidePopup();
        }, 3000);
    }
    
    /**
     * Get current page ID
     */
    function getPageId() {
        console.log('[WireNPS] Getting page ID...');
        console.log('[WireNPS] window.wireNPSConfig:', window.wireNPSConfig);
        console.log('[WireNPS] config var:', config);
        
        // Get from config (passed from PHP)
        if(window.wireNPSConfig && window.wireNPSConfig.pageId) {
            const pageId = parseInt(window.wireNPSConfig.pageId);
            console.log('[WireNPS] Page ID from window.wireNPSConfig.pageId:', pageId);
            return pageId;
        }
        
        if(config && config.pageId) {
            const pageId = parseInt(config.pageId);
            console.log('[WireNPS] Page ID from config.pageId:', pageId);
            return pageId;
        }
        
        console.log('[WireNPS] No page ID in config, trying fallbacks...');
        
        // Fallback: Try to get from meta tag
        const metaTag = document.querySelector('meta[name="page-id"]');
        if(metaTag) {
            const pageId = parseInt(metaTag.content);
            console.log('[WireNPS] Page ID from meta tag:', pageId);
            return pageId;
        }
        
        // Fallback: Try to get from body data attribute
        const pageIdAttr = document.body.getAttribute('data-page-id');
        if(pageIdAttr) {
            const pageId = parseInt(pageIdAttr);
            console.log('[WireNPS] Page ID from body:', pageId);
            return pageId;
        }
        
        console.warn('[WireNPS] No page ID found! Using 1 as default');
        return 1; // Default to homepage
    }
    
    /**
     * Set cookie
     */
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
    }
    
    /**
     * Get cookie
     */
    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while(c.charAt(0) === ' ') {
                c = c.substring(1, c.length);
            }
            if(c.indexOf(nameEQ) === 0) {
                return c.substring(nameEQ.length, c.length);
            }
        }
        
        return null;
    }
    
    /**
     * Check if elements exist and initialize
     */
    function checkAndInit() {
        const testOverlay = document.getElementById('wirenps-overlay');
        
        if(!testOverlay) {
            retryCount++;
            
            if(retryCount >= MAX_RETRIES) {
                console.error('[WireNPS] Elements not found after ' + MAX_RETRIES + ' retries. Widget HTML may not be injected.');
                return;
            }
            
            console.log('[WireNPS] Elements not ready yet, waiting... (retry ' + retryCount + '/' + MAX_RETRIES + ')');
            setTimeout(checkAndInit, 100);
            return;
        }
        
        console.log('[WireNPS] Elements ready, initializing...');
        init();
    }
    
    // Initialize on DOM ready
    if(document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAndInit);
    } else {
        // DOM already loaded, but check if elements exist
        checkAndInit();
    }
    
})();
