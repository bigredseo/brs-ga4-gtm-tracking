(function() {
    'use strict';

    if (window.__BRS_GA4_GTM_COMPLETE_TRACKING_LOADED) {
        return;
    }

    window.__BRS_GA4_GTM_COMPLETE_TRACKING_LOADED = true;
    window.dataLayer = window.dataLayer || [];

    var config = window.BRS_GA4_GTM_TRACKING || {};
    var GA4_ID = config.ga4_measurement_id || '';
    var features = config.features || {};
    var contentContext = config.content_context || {};
    var likelyRead = config.likely_read || {};

    if (!GA4_ID) {
        return;
    }

    window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };

    if (!window.__BRS_GA4_GTAG_SCRIPT_LOADED) {
        window.__BRS_GA4_GTAG_SCRIPT_LOADED = true;

        var existingScript = document.querySelector('script[src*="googletagmanager.com/gtag/js?id=' + GA4_ID + '"]');

        if (!existingScript) {
            var script = document.createElement('script');
            script.async = true;
            script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4_ID);
            document.head.appendChild(script);
        }
    }

    if (!window.__BRS_GA4_CONFIGURED) {
        window.__BRS_GA4_CONFIGURED = true;
        window.gtag('js', new Date());
        window.gtag('config', GA4_ID, { send_page_view: false });
    }

    function extend(target) {
        target = target || {};

        for (var i = 1; i < arguments.length; i++) {
            var source = arguments[i] || {};

            for (var key in source) {
                if (Object.prototype.hasOwnProperty.call(source, key) && source[key] !== undefined && source[key] !== null) {
                    target[key] = source[key];
                }
            }
        }

        return target;
    }

    function cleanText(value) {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value).replace(/\s+/g, ' ').trim();
    }

    function getContext() {
        if (features.content_context === false) {
            return {};
        }

        return extend({}, contentContext);
    }

    function getPageParams() {
        return {
            page_title: document.title,
            page_location: window.location.href,
            page_path: window.location.pathname + window.location.search
        };
    }

    function sendGA4Event(eventName, params) {
        params = extend({}, getContext(), params || {});
        window.gtag('event', eventName, params);
    }

    function sendContentContextEvent() {
        if (features.content_context === false) {
            return;
        }

        sendGA4Event('brs_content_context', getPageParams());
    }

    function sendPageView() {
        sendGA4Event('page_view', getPageParams());
    }

    function setupReadTracking() {
        if (features.read_tracking === false) {
            return;
        }

        var scrollThresholds = [25, 50, 75, 90];
        var sentScrolls = {};
        var readScrollThreshold = parseInt(likelyRead.scroll_threshold || 50, 10);
        var readTimeSeconds = parseInt(likelyRead.time_seconds || 120, 10);
        var readScrollReached = false;
        var readTimeReached = false;
        var likelyReadSent = false;
        var ticking = false;

        if (readScrollThreshold < 1 || readScrollThreshold > 100) {
            readScrollThreshold = 50;
        }

        if (readTimeSeconds < 5) {
            readTimeSeconds = 120;
        }

        function getScrollPercent() {
            var doc = document.documentElement;
            var body = document.body;
            var scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
            var scrollHeight = Math.max(body.scrollHeight, doc.scrollHeight, body.offsetHeight, doc.offsetHeight, body.clientHeight, doc.clientHeight);
            var viewportHeight = window.innerHeight || doc.clientHeight || body.clientHeight || 0;
            var trackLength = scrollHeight - viewportHeight;

            if (trackLength <= 0) {
                return 100;
            }

            return Math.min(100, Math.max(0, Math.round((scrollTop / trackLength) * 100)));
        }

        function checkLikelyRead() {
            if (likelyReadSent || !readScrollReached || !readTimeReached) {
                return;
            }

            likelyReadSent = true;

            sendGA4Event('article_likely_read', {
                likely_read_scroll: readScrollThreshold,
                likely_read_time: readTimeSeconds,
                page_title: document.title,
                page_location: window.location.href,
                page_path: window.location.pathname + window.location.search
            });
        }

        function evaluateScroll() {
            ticking = false;
            var percent = getScrollPercent();

            for (var i = 0; i < scrollThresholds.length; i++) {
                var threshold = scrollThresholds[i];

                if (percent >= threshold && !sentScrolls[threshold]) {
                    sentScrolls[threshold] = true;

                    sendGA4Event('content_scroll', {
                        percent_scrolled: threshold,
                        page_title: document.title,
                        page_location: window.location.href,
                        page_path: window.location.pathname + window.location.search
                    });
                }
            }

            if (percent >= readScrollThreshold) {
                readScrollReached = true;
                checkLikelyRead();
            }
        }

        function onScroll() {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame ? window.requestAnimationFrame(evaluateScroll) : setTimeout(evaluateScroll, 100);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        setTimeout(evaluateScroll, 500);

        [30, 60, 120, 180].forEach(function(seconds) {
            setTimeout(function() {
                sendGA4Event('engaged_' + seconds + '_seconds', {
                    engagement_time_label: seconds + '_seconds',
                    engagement_time_seconds: seconds,
                    page_title: document.title,
                    page_location: window.location.href,
                    page_path: window.location.pathname + window.location.search
                });
            }, seconds * 1000);
        });

        setTimeout(function() {
            readTimeReached = true;
            checkLikelyRead();
        }, readTimeSeconds * 1000);
    }

    function setupClickAndFormTracking() {
        if (features.click_form_tracking === false) {
            return;
        }

        var startedForms = [];
        var submittedForms = [];
        var successMessagesSeen = [];

        function arrayContains(array, item) {
            for (var i = 0; i < array.length; i++) {
                if (array[i] === item) {
                    return true;
                }
            }

            return false;
        }

        function closest(element, selector) {
            if (!element) {
                return null;
            }

            if (element.closest) {
                return element.closest(selector);
            }

            while (element && element.nodeType === 1) {
                if (element.matches && element.matches(selector)) {
                    return element;
                }
                element = element.parentElement;
            }

            return null;
        }

        function getFormDetails(form) {
            if (!form) {
                return {};
            }

            return {
                form_id: form.getAttribute('id') || '',
                form_name: form.getAttribute('name') || '',
                form_classes: form.getAttribute('class') || '',
                form_action: form.getAttribute('action') || window.location.href,
                page_title: document.title,
                page_location: window.location.href,
                page_path: window.location.pathname + window.location.search
            };
        }

        function isContactForm(form) {
            if (!form) {
                return false;
            }

            var raw = [
                form.getAttribute('id') || '',
                form.getAttribute('class') || '',
                form.getAttribute('name') || '',
                form.getAttribute('action') || ''
            ].join(' ').toLowerCase();

            if (/woocommerce|checkout|cart|search|comment|login|register/.test(raw)) {
                return false;
            }

            return /contact|wpforms|wpcf7|gform|elementor-form|fluentform|forminator|ninja-forms|nf-form|gravity/.test(raw);
        }

        function markFormStart(form) {
            if (!isContactForm(form) || arrayContains(startedForms, form)) {
                return;
            }

            startedForms.push(form);
            sendGA4Event('contact_form_start', getFormDetails(form));
        }

        function markFormSubmitAttempt(form) {
            if (!isContactForm(form) || arrayContains(submittedForms, form)) {
                return;
            }

            submittedForms.push(form);
            sendGA4Event('contact_form_submit_attempt', getFormDetails(form));
        }

        document.addEventListener('focusin', function(event) {
            var form = closest(event.target, 'form');
            markFormStart(form);
        }, true);

        document.addEventListener('change', function(event) {
            var form = closest(event.target, 'form');
            markFormStart(form);
        }, true);

        document.addEventListener('submit', function(event) {
            markFormSubmitAttempt(event.target);
        }, true);

        document.addEventListener('wpcf7mailsent', function(event) {
            sendGA4Event('contact_form_submission', extend(getFormDetails(event.target), { form_plugin: 'contact_form_7' }));
        }, false);

        if (window.jQuery) {
            window.jQuery(document).on('submit_success', function(event) {
                var form = event && event.target ? closest(event.target, 'form') : null;
                sendGA4Event('contact_form_submission', extend(getFormDetails(form), { form_plugin: 'elementor' }));
            });

            window.jQuery(document).on('gform_confirmation_loaded', function(event, formId) {
                sendGA4Event('contact_form_submission', {
                    form_plugin: 'gravity_forms',
                    form_id: formId ? String(formId) : '',
                    page_title: document.title,
                    page_location: window.location.href,
                    page_path: window.location.pathname + window.location.search
                });
            });

            window.jQuery(document).on('wpformsAjaxSubmitSuccess', function(event) {
                var form = event && event.target ? closest(event.target, 'form') : null;
                sendGA4Event('contact_form_submission', extend(getFormDetails(form), { form_plugin: 'wpforms' }));
            });
        }

        function checkSuccessMessages() {
            var selectors = [
                '.elementor-message-success',
                '.wpcf7-mail-sent-ok',
                '.wpcf7-response-output',
                '.wpforms-confirmation-container-full',
                '.gform_confirmation_message',
                '.ff-message-success',
                '.forminator-response-message.forminator-success',
                '.nf-response-msg'
            ];

            selectors.forEach(function(selector) {
                var nodes = document.querySelectorAll(selector);

                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    var text = cleanText(node.textContent || '');
                    var key = selector + '|' + text;

                    if (!text || arrayContains(successMessagesSeen, key)) {
                        continue;
                    }

                    if (/error|failed|invalid|required|problem/i.test(text) && !/success|thank|sent|received/i.test(text)) {
                        continue;
                    }

                    successMessagesSeen.push(key);
                    sendGA4Event('contact_form_submission', {
                        form_plugin: 'detected_success_message',
                        form_message: text.substring(0, 120),
                        page_title: document.title,
                        page_location: window.location.href,
                        page_path: window.location.pathname + window.location.search
                    });
                }
            });
        }

        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                checkSuccessMessages();
            });

            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
                characterData: true
            });
        }

        document.addEventListener('click', function(event) {
            var link = closest(event.target, 'a');

            if (!link || !link.href) {
                return;
            }

            var href = link.href;
            var linkText = cleanText(link.textContent || '');
            var linkClass = link.getAttribute('class') || '';
            var currentHost = window.location.hostname.replace(/^www\./, '');
            var linkHost = link.hostname ? link.hostname.replace(/^www\./, '') : '';
            var isInternal = !linkHost || linkHost === currentHost;
            var isSamePage = isInternal && link.pathname === window.location.pathname && link.search === window.location.search;
            var combined = (href + ' ' + linkText + ' ' + linkClass).toLowerCase();
            var linkParams = {
                link_url: href,
                link_text: linkText.substring(0, 120),
                link_classes: linkClass.substring(0, 120),
                page_title: document.title,
                page_location: window.location.href,
                page_path: window.location.pathname + window.location.search
            };

            if (/mailto:|tel:|\/contact|contact-us|#contact|consultation|quote|estimate|get-started|schedule/.test(combined)) {
                sendGA4Event('cta_contact_click', linkParams);
            }

            if (!isInternal && !/^mailto:|^tel:/i.test(href)) {
                sendGA4Event('outbound_click', linkParams);
            }

            if (isInternal && !isSamePage && contentContext.content_type === 'post' && !/\/cart|\/checkout|\/my-account|\/contact|mailto:|tel:/i.test(href)) {
                sendGA4Event('internal_article_click', linkParams);
            }
        }, true);
    }

    function setupWooCommerceDataLayerListener() {
        if (features.woocommerce_tracking === false) {
            return;
        }

        var eventMap = {
            brs_view_item: 'view_item',
            brs_view_item_list: 'view_item_list',
            brs_add_to_cart: 'add_to_cart',
            brs_remove_from_cart: 'remove_from_cart',
            brs_view_cart: 'view_cart',
            brs_begin_checkout: 'begin_checkout',
            brs_add_shipping_info: 'add_shipping_info',
            brs_add_payment_info: 'add_payment_info',
            brs_purchase: 'purchase',
            brs_product_form_start: 'product_form_start',
            brs_product_option_change: 'product_option_change',
            brs_product_variation_selected: 'product_variation_selected',
            brs_product_add_to_cart_attempt: 'product_add_to_cart_attempt',
            brs_checkout_form_start: 'checkout_form_start'
        };

        function shouldSkipPurchase(params) {
            if (!params || !params.transaction_id || !window.localStorage) {
                return false;
            }

            var key = 'brs_ga4_purchase_sent_' + params.transaction_id;

            if (window.localStorage.getItem(key)) {
                return true;
            }

            window.localStorage.setItem(key, '1');
            return false;
        }

        function processDataLayerObject(data) {
            if (!data || !data.event || !eventMap[data.event]) {
                return;
            }

            var userInitiatedOnlyEvents = {
                brs_product_option_change: true,
                brs_product_variation_selected: true
            };

            if (userInitiatedOnlyEvents[data.event] && data.brs_user_initiated !== true) {
                return;
            }

            var gaEventName = eventMap[data.event];
            var params = extend({}, data.ecommerce || {});

            for (var key in data) {
                if (Object.prototype.hasOwnProperty.call(data, key) && key !== 'event' && key !== 'ecommerce') {
                    params[key] = data[key];
                }
            }

            if (gaEventName === 'purchase' && shouldSkipPurchase(params)) {
                return;
            }

            sendGA4Event(gaEventName, params);
        }

        for (var i = 0; i < window.dataLayer.length; i++) {
            processDataLayerObject(window.dataLayer[i]);
        }

        var originalPush = window.dataLayer.push;

        window.dataLayer.push = function() {
            var result = originalPush.apply(window.dataLayer, arguments);

            for (var i = 0; i < arguments.length; i++) {
                processDataLayerObject(arguments[i]);
            }

            return result;
        };
    }

    sendPageView();
    sendContentContextEvent();
    setupReadTracking();
    setupClickAndFormTracking();
    setupWooCommerceDataLayerListener();
})();
