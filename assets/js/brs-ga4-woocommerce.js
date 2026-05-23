(function($) {
    'use strict';

    window.dataLayer = window.dataLayer || [];

    var config = window.BRS_GA4_WC_TRACKING || {};
    var formStarted = false;
    var checkoutStarted = false;
    var shippingInfoSent = {};
    var paymentInfoSent = {};
    var lastProductInteractionTime = 0;
    var optionChangeMemory = {};
    var interactionWindowMs = 5000;

    /**
     * Only count product option changes after a real visitor action.
     * This prevents modal/preload/default-option scripts from flooding GA4.
     */
    function isTrustedUserEvent(event) {
        return !!(
            event &&
            event.originalEvent &&
            event.originalEvent.isTrusted === true
        );
    }

    function markProductInteraction(event) {
        if (!isTrustedUserEvent(event)) {
            return;
        }

        lastProductInteractionTime = Date.now();
    }

    function hasRecentProductInteraction() {
        return lastProductInteractionTime && (Date.now() - lastProductInteractionTime <= interactionWindowMs);
    }

    function shouldTrackProductChange(event) {
        return hasRecentProductInteraction();
    }    

    function getTrackableFieldValue($field) {
        var value = $field.val();

        if ($field.is(':checkbox, :radio')) {
            if (!$field.is(':checked')) {
                return '';
            }

            value = $field.val();
        }

        if ($.isArray(value)) {
            value = value.join(', ');
        }

        return $.trim(String(value || ''));
    }

    function isDuplicateOptionChange(fieldKey, fieldValue) {
        if (!fieldKey || !fieldValue) {
            return false;
        }

        if (optionChangeMemory[fieldKey] === fieldValue) {
            return true;
        }

        optionChangeMemory[fieldKey] = fieldValue;
        return false;
    }

    function pushEvent(eventName, payload) {
        payload = payload || {};
        payload.event = eventName;
        window.dataLayer.push(payload);
    }

    function getBaseProductEcommerce(quantity) {
        var product = config.product || {};
        var item = product.item || null;

        if (!item) {
            return null;
        }

        quantity = quantity || 1;

        var clonedItem = $.extend(true, {}, item);
        clonedItem.quantity = quantity;

        return {
            currency: product.currency || config.currency || 'USD',
            value: clonedItem.price ? Number(clonedItem.price) * Number(quantity) : 0,
            items: [clonedItem]
        };
    }

    function getProductQuantity($form) {
        var quantity = 1;
        var $qty = $form.find('input.qty').first();

        if ($qty.length) {
            quantity = parseFloat($qty.val()) || 1;
        }

        return quantity;
    }

    function getFieldLabel($field) {
        var name = $field.attr('name') || '';
        var id = $field.attr('id') || '';
        var label = '';

        if (id) {
            label = $('label[for="' + id.replace(/(:|\.|\[|\]|,|=|@)/g, '\\$1') + '"]').first().text();
        }

        return $.trim(label || name || id || 'product_option');
    }

    function normalizeVariationAttributes(attributes) {
        var output = [];

        if (!attributes) {
            return '';
        }

        $.each(attributes, function(key, value) {
            if (value) {
                output.push(String(key).replace(/^attribute_/, '') + ': ' + value);
            }
        });

        return output.join(', ');
    }

    function buildVariationEcommerce(variation, quantity) {
        var baseProduct = config.product || {};
        var baseItem = baseProduct.item || {};
        var item = $.extend(true, {}, baseItem);

        quantity = quantity || 1;

        if (variation && variation.variation_id) {
            item.item_id = variation.sku || String(variation.variation_id);
            item.item_name = variation.display_name || item.item_name || document.title;
            item.price = variation.display_price || item.price || 0;
            item.item_variant = normalizeVariationAttributes(variation.attributes);
            item.quantity = quantity;
        }

        return {
            currency: baseProduct.currency || config.currency || 'USD',
            value: item.price ? Number(item.price) * Number(quantity) : 0,
            items: [item]
        };
    }

    function getAjaxButtonEcommerce($button) {
        var productId = $button.data('product_id') || $button.val() || '';
        var productSku = $button.data('product_sku') || '';
        var quantity = parseFloat($button.data('quantity')) || 1;
        var productName = $.trim(
            $button.attr('aria-label') ||
            $button.closest('.product').find('.woocommerce-loop-product__title, .product-title, h2, h3').first().text() ||
            $button.text() ||
            'Product'
        );
        var itemId = productSku || String(productId || 'product');

        return {
            currency: config.currency || 'USD',
            value: 0,
            items: [{
                item_id: itemId,
                item_name: productName,
                quantity: quantity
            }]
        };
    }

    function startProductForm($form) {
        if (formStarted) {
            return;
        }

        formStarted = true;

        pushEvent('brs_product_form_start', {
            ecommerce: getBaseProductEcommerce(getProductQuantity($form)) || undefined
        });
    }

    $(document).on('pointerdown mousedown touchstart keydown click', 'form.cart, form.variations_form, .selectedoptions, .modal, .popup, .wl-modal, .wl-fabric-modal, .wl-fabric-options', function(event) {
        markProductInteraction(event);

        if (hasRecentProductInteraction()) {
            startProductForm($(this).closest('form.cart, form.variations_form'));
        }
    });

    $(document).on('change', 'form.cart input, form.cart select, form.cart textarea, form.variations_form input, form.variations_form select, form.variations_form textarea', function(event) {
        var $field = $(this);
        var $form = $field.closest('form');
        var fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();
        var fieldName = $field.attr('name') || $field.attr('id') || '';
        var fieldValue = getTrackableFieldValue($field);
        var fieldKey = fieldName || getFieldLabel($field);

        if ('quantity' === fieldName || 'hidden' === fieldType || !fieldValue) {
            return;
        }

        if (!shouldTrackProductChange(event)) {
            return;
        }

        if (isDuplicateOptionChange(fieldKey, fieldValue)) {
            return;
        }

        startProductForm($form);

        pushEvent('brs_product_option_change', {
            brs_user_initiated: true,
            product_option_name: getFieldLabel($field),
            product_option_value: fieldValue,
            ecommerce: getBaseProductEcommerce(getProductQuantity($form)) || undefined
        });
    });

    $(document).on('found_variation', 'form.variations_form', function(event, variation) {
        var $form = $(this);
        var quantity = getProductQuantity($form);

        if (!shouldTrackProductChange(event)) {
            return;
        }

        startProductForm($form);

        pushEvent('brs_product_variation_selected', {
            brs_user_initiated: true,
            variation_id: variation && variation.variation_id ? variation.variation_id : '',
            variation_sku: variation && variation.sku ? variation.sku : '',
            ecommerce: buildVariationEcommerce(variation, quantity)
        });
    });

    $(document).on('submit', 'form.cart, form.variations_form', function() {
        var $form = $(this);
        var quantity = getProductQuantity($form);
        var ecommerce = getBaseProductEcommerce(quantity);

        startProductForm($form);

        pushEvent('brs_product_add_to_cart_attempt', {
            ecommerce: ecommerce || undefined
        });
    });

    $(document.body).on('added_to_cart', function(event, fragments, cartHash, $button) {
        if (!$button || !$button.length) {
            return;
        }

        pushEvent('brs_add_to_cart', {
            ecommerce: getAjaxButtonEcommerce($button)
        });
    });

    $(document.body).on('removed_from_cart', function(event, fragments, cartHash, $button) {
        var ecommerce = null;

        if ($button && $button.length) {
            ecommerce = getAjaxButtonEcommerce($button);
        }

        pushEvent('brs_remove_from_cart', {
            ecommerce: ecommerce || undefined
        });
    });

    $(document).on('focusin change', 'form.checkout', function() {
        if (checkoutStarted) {
            return;
        }

        checkoutStarted = true;

        pushEvent('brs_checkout_form_start', {
            ecommerce: config.cart || undefined
        });
    });

    $(document).on('change', 'input[name^="shipping_method"]', function() {
        var shippingTier = $(this).val() || '';

        if (!shippingTier || shippingInfoSent[shippingTier]) {
            return;
        }

        shippingInfoSent[shippingTier] = true;

        pushEvent('brs_add_shipping_info', {
            shipping_tier: shippingTier,
            ecommerce: $.extend(true, {}, config.cart || {}, { shipping_tier: shippingTier })
        });
    });

    $(document).on('change', 'input[name="payment_method"]', function() {
        var paymentType = $(this).val() || '';

        if (!paymentType || paymentInfoSent[paymentType]) {
            return;
        }

        paymentInfoSent[paymentType] = true;

        pushEvent('brs_add_payment_info', {
            payment_type: paymentType,
            ecommerce: $.extend(true, {}, config.cart || {}, { payment_type: paymentType })
        });
    });

})(jQuery);