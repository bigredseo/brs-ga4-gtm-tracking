<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get WooCommerce currency safely.
 */
function brs_ga4_gtm_tracking_wc_currency() {
    return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
}

/**
 * Format a numeric value for GA4.
 */
function brs_ga4_gtm_tracking_wc_number( $value ) {
    return (float) wc_format_decimal( $value, 2 );
}

/**
 * Get product category fields for GA4 item parameters.
 */
function brs_ga4_gtm_tracking_wc_item_categories( $product_id ) {
    $categories = array();
    $terms      = get_the_terms( $product_id, 'product_cat' );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return $categories;
    }

    $index = 1;

    foreach ( $terms as $term ) {
        if ( $index > 5 ) {
            break;
        }

        $key                = 1 === $index ? 'item_category' : 'item_category' . $index;
        $categories[ $key ] = $term->name;
        $index++;
    }

    return $categories;
}

/**
 * Build a GA4 item from a WooCommerce product.
 */
function brs_ga4_gtm_tracking_wc_product_item( $product, $quantity = 1, $index = 0, $line_price = null ) {
    if ( is_numeric( $product ) ) {
        $product = wc_get_product( absint( $product ) );
    }

    if ( ! $product instanceof WC_Product ) {
        return array();
    }

    $product_id   = $product->get_id();
    $parent_id    = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;
    $sku          = $product->get_sku();
    $item_id      = ! empty( $sku ) ? $sku : (string) $product_id;
    $regular_name = $product->get_name();
    $price        = null !== $line_price ? $line_price : wc_get_price_to_display( $product );

    $item = array(
        'item_id'   => (string) $item_id,
        'item_name' => $regular_name,
        'price'     => brs_ga4_gtm_tracking_wc_number( $price ),
        'quantity'  => brs_ga4_gtm_tracking_wc_number( $quantity ),
    );

    if ( $index > 0 ) {
        $item['index'] = absint( $index );
    }

    $categories = brs_ga4_gtm_tracking_wc_item_categories( $parent_id );

    if ( ! empty( $categories ) ) {
        $item = array_merge( $item, $categories );
    }

    if ( $product->is_type( 'variation' ) ) {
        $attributes = $product->get_attributes();

        if ( ! empty( $attributes ) ) {
            $variant_parts = array();

            foreach ( $attributes as $attribute_name => $attribute_value ) {
                $variant_parts[] = wc_attribute_label( str_replace( 'attribute_', '', $attribute_name ) ) . ': ' . $attribute_value;
            }

            $item['item_variant'] = implode( ', ', $variant_parts );
        }
    }

    return $item;
}

/**
 * Build cart ecommerce payload.
 */
function brs_ga4_gtm_tracking_wc_cart_ecommerce() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return array(
            'currency' => brs_ga4_gtm_tracking_wc_currency(),
            'value'    => 0,
            'items'    => array(),
        );
    }

    $items = array();
    $index = 1;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
            continue;
        }

        $quantity   = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
        $line_price = null;

        if ( ! empty( $quantity ) && isset( $cart_item['line_subtotal'] ) ) {
            $line_price = (float) $cart_item['line_subtotal'] / (float) $quantity;
        }

        $item = brs_ga4_gtm_tracking_wc_product_item( $cart_item['data'], $quantity, $index, $line_price );

        if ( ! empty( $item ) ) {
            $items[] = $item;
            $index++;
        }
    }

    return array(
        'currency' => brs_ga4_gtm_tracking_wc_currency(),
        'value'    => brs_ga4_gtm_tracking_wc_number( WC()->cart->get_total( 'edit' ) ),
        'items'    => $items,
    );
}

/**
 * Output a dataLayer event.
 */
function brs_ga4_gtm_tracking_wc_output_event( $event_name, $payload = array() ) {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    $payload = is_array( $payload ) ? $payload : array();
    $payload = array_merge( array( 'event' => $event_name ), $payload );
    ?>
    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(<?php echo wp_json_encode( $payload ); ?>);
    </script>
    <?php
}

/**
 * Add a pending WooCommerce event to the session for non-AJAX cart changes.
 */
function brs_ga4_gtm_tracking_wc_add_pending_event( $event_name, $payload ) {
    if ( ! function_exists( 'WC' ) || ! WC()->session ) {
        return;
    }

    $events = WC()->session->get( 'brs_ga4_gtm_pending_events', array() );

    if ( ! is_array( $events ) ) {
        $events = array();
    }

    $events[] = array(
        'event'   => $event_name,
        'payload' => $payload,
    );

    WC()->session->set( 'brs_ga4_gtm_pending_events', $events );
}

/**
 * Store non-AJAX add_to_cart events for the next page load.
 */
function brs_ga4_gtm_tracking_wc_track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
    if ( wp_doing_ajax() ) {
        return;
    }

    $product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
    $item    = brs_ga4_gtm_tracking_wc_product_item( $product, $quantity, 1 );

    if ( empty( $item ) ) {
        return;
    }

    brs_ga4_gtm_tracking_wc_add_pending_event(
        'brs_add_to_cart',
        array(
            'ecommerce' => array(
                'currency' => brs_ga4_gtm_tracking_wc_currency(),
                'value'    => isset( $item['price'] ) ? brs_ga4_gtm_tracking_wc_number( $item['price'] * $quantity ) : 0,
                'items'    => array( $item ),
            ),
        )
    );
}
add_action( 'woocommerce_add_to_cart', 'brs_ga4_gtm_tracking_wc_track_add_to_cart', 10, 6 );

/**
 * Store remove_from_cart events for the next page load.
 */
function brs_ga4_gtm_tracking_wc_track_remove_from_cart( $cart_item_key, $cart ) {
    if ( empty( $cart->removed_cart_contents[ $cart_item_key ] ) ) {
        return;
    }

    $cart_item = $cart->removed_cart_contents[ $cart_item_key ];

    if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
        return;
    }

    $quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
    $item     = brs_ga4_gtm_tracking_wc_product_item( $cart_item['data'], $quantity, 1 );

    if ( empty( $item ) ) {
        return;
    }

    brs_ga4_gtm_tracking_wc_add_pending_event(
        'brs_remove_from_cart',
        array(
            'ecommerce' => array(
                'currency' => brs_ga4_gtm_tracking_wc_currency(),
                'value'    => isset( $item['price'] ) ? brs_ga4_gtm_tracking_wc_number( $item['price'] * $quantity ) : 0,
                'items'    => array( $item ),
            ),
        )
    );
}
add_action( 'woocommerce_remove_cart_item', 'brs_ga4_gtm_tracking_wc_track_remove_from_cart', 10, 2 );

/**
 * Output WooCommerce page events and pending events.
 */
function brs_ga4_gtm_tracking_wc_output_page_events() {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    if ( function_exists( 'is_product' ) && is_product() ) {
        $product = wc_get_product( get_the_ID() );
        $item    = brs_ga4_gtm_tracking_wc_product_item( $product, 1, 1 );

        if ( ! empty( $item ) ) {
            brs_ga4_gtm_tracking_wc_output_event(
                'brs_view_item',
                array(
                    'ecommerce' => array(
                        'currency' => brs_ga4_gtm_tracking_wc_currency(),
                        'value'    => isset( $item['price'] ) ? brs_ga4_gtm_tracking_wc_number( $item['price'] ) : 0,
                        'items'    => array( $item ),
                    ),
                )
            );
        }
    }

    if ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_category' ) && is_product_category() ) || ( function_exists( 'is_product_tag' ) && is_product_tag() ) ) {
        global $wp_query;

        $items = array();
        $index = 1;

        if ( ! empty( $wp_query->posts ) ) {
            foreach ( $wp_query->posts as $post ) {
                if ( $index > 24 ) {
                    break;
                }

                $product = wc_get_product( $post->ID );
                $item    = brs_ga4_gtm_tracking_wc_product_item( $product, 1, $index );

                if ( ! empty( $item ) ) {
                    $items[] = $item;
                    $index++;
                }
            }
        }

        if ( ! empty( $items ) ) {
            brs_ga4_gtm_tracking_wc_output_event(
                'brs_view_item_list',
                array(
                    'ecommerce' => array(
                        'currency'      => brs_ga4_gtm_tracking_wc_currency(),
                        'item_list_name' => wp_get_document_title(),
                        'items'          => $items,
                    ),
                )
            );
        }
    }

    if ( function_exists( 'is_cart' ) && is_cart() ) {
        brs_ga4_gtm_tracking_wc_output_event(
            'brs_view_cart',
            array(
                'ecommerce' => brs_ga4_gtm_tracking_wc_cart_ecommerce(),
            )
        );
    }

    if ( function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) ) {
        brs_ga4_gtm_tracking_wc_output_event(
            'brs_begin_checkout',
            array(
                'ecommerce' => brs_ga4_gtm_tracking_wc_cart_ecommerce(),
            )
        );
    }

    if ( function_exists( 'WC' ) && WC()->session ) {
        $pending_events = WC()->session->get( 'brs_ga4_gtm_pending_events', array() );

        if ( is_array( $pending_events ) && ! empty( $pending_events ) ) {
            foreach ( $pending_events as $pending_event ) {
                if ( empty( $pending_event['event'] ) ) {
                    continue;
                }

                brs_ga4_gtm_tracking_wc_output_event(
                    $pending_event['event'],
                    isset( $pending_event['payload'] ) && is_array( $pending_event['payload'] ) ? $pending_event['payload'] : array()
                );
            }

            WC()->session->__unset( 'brs_ga4_gtm_pending_events' );
        }
    }
}
add_action( 'wp_footer', 'brs_ga4_gtm_tracking_wc_output_page_events', 20 );

/**
 * Output purchase event on the order received page.
 */
function brs_ga4_gtm_tracking_wc_output_purchase_event( $order_id ) {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    $order = wc_get_order( $order_id );

    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $items = array();
    $index = 1;

    foreach ( $order->get_items() as $order_item ) {
        if ( ! $order_item instanceof WC_Order_Item_Product ) {
            continue;
        }

        $product = $order_item->get_product();

        if ( ! $product instanceof WC_Product ) {
            continue;
        }

        $quantity   = $order_item->get_quantity();
        $line_price = $quantity > 0 ? (float) $order_item->get_subtotal() / (float) $quantity : 0;
        $item       = brs_ga4_gtm_tracking_wc_product_item( $product, $quantity, $index, $line_price );

        if ( ! empty( $item ) ) {
            $items[] = $item;
            $index++;
        }
    }

    $coupon_codes = $order->get_coupon_codes();

    brs_ga4_gtm_tracking_wc_output_event(
        'brs_purchase',
        array(
            'ecommerce' => array(
                'transaction_id' => (string) $order->get_order_number(),
                'currency'       => $order->get_currency(),
                'value'          => brs_ga4_gtm_tracking_wc_number( $order->get_total() ),
                'tax'            => brs_ga4_gtm_tracking_wc_number( $order->get_total_tax() ),
                'shipping'       => brs_ga4_gtm_tracking_wc_number( $order->get_shipping_total() ),
                'coupon'         => ! empty( $coupon_codes ) ? implode( ',', $coupon_codes ) : '',
                'items'          => $items,
            ),
        )
    );
}
add_action( 'woocommerce_thankyou', 'brs_ga4_gtm_tracking_wc_output_purchase_event', 20 );

/**
 * Enqueue WooCommerce-specific interaction listener only on WooCommerce-related pages.
 */
function brs_ga4_gtm_tracking_wc_enqueue_scripts() {
    if ( brs_ga4_gtm_tracking_is_tracking_excluded() ) {
        return;
    }

    $is_woo_page = false;

    if ( function_exists( 'is_product' ) && is_product() ) {
        $is_woo_page = true;
    } elseif ( function_exists( 'is_shop' ) && is_shop() ) {
        $is_woo_page = true;
    } elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
        $is_woo_page = true;
    } elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
        $is_woo_page = true;
    } elseif ( function_exists( 'is_cart' ) && is_cart() ) {
        $is_woo_page = true;
    } elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
        $is_woo_page = true;
    }

    if ( ! $is_woo_page ) {
        return;
    }

    wp_enqueue_script(
        'brs-ga4-gtm-woocommerce',
        BRS_GA4_GTM_TRACKING_URL . 'assets/js/brs-ga4-woocommerce.js',
        array( 'jquery' ),
        BRS_GA4_GTM_TRACKING_VERSION,
        true
    );

    $product_data = array();

    if ( function_exists( 'is_product' ) && is_product() ) {
        $product = wc_get_product( get_the_ID() );
        $item    = brs_ga4_gtm_tracking_wc_product_item( $product, 1, 1 );

        if ( ! empty( $item ) ) {
            $product_data = array(
                'product_id' => $product instanceof WC_Product ? $product->get_id() : 0,
                'currency'   => brs_ga4_gtm_tracking_wc_currency(),
                'item'       => $item,
            );
        }
    }

    wp_localize_script(
        'brs-ga4-gtm-woocommerce',
        'BRS_GA4_WC_TRACKING',
        array(
            'currency' => brs_ga4_gtm_tracking_wc_currency(),
            'cart'     => brs_ga4_gtm_tracking_wc_cart_ecommerce(),
            'product'  => $product_data,
        )
    );
}
add_action( 'wp_enqueue_scripts', 'brs_ga4_gtm_tracking_wc_enqueue_scripts' );
