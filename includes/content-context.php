<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get a primary term name for a post.
 *
 * This intentionally avoids Rank Math/Yoast-specific primary category APIs so the plugin stays portable.
 */
function brs_ga4_gtm_tracking_get_first_term_name( $post_id, $taxonomy ) {
    $terms = get_the_terms( $post_id, $taxonomy );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return '';
    }

    $term = reset( $terms );

    return isset( $term->name ) ? $term->name : '';
}

/**
 * Build WordPress content context for GA4/GTM.
 */
function brs_ga4_gtm_tracking_get_content_context() {
    $content_group    = 'Other';
    $content_type     = 'other';
    $post_type        = '';
    $content_id       = 0;
    $content_title    = wp_get_document_title();
    $primary_category = '';
    $template_type    = 'other';

    if ( function_exists( 'is_cart' ) && is_cart() ) {
        $content_group = 'Ecommerce';
        $content_type  = 'cart';
        $template_type = 'cart';
    } elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
        $content_group = 'Ecommerce';
        $content_type  = function_exists( 'is_order_received_page' ) && is_order_received_page() ? 'order_received' : 'checkout';
        $template_type = $content_type;
    } elseif ( function_exists( 'is_product' ) && is_product() ) {
        $content_group    = 'Products';
        $content_type     = 'product';
        $post_type        = 'product';
        $content_id       = get_the_ID();
        $content_title    = get_the_title( $content_id );
        $primary_category = brs_ga4_gtm_tracking_get_first_term_name( $content_id, 'product_cat' );
        $template_type    = 'product';
    } elseif ( function_exists( 'is_shop' ) && is_shop() ) {
        $content_group = 'Product Archives';
        $content_type  = 'shop';
        $template_type = 'shop';
    } elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
        $content_group = 'Product Archives';
        $content_type  = 'product_category_archive';
        $template_type = 'product_category_archive';

        $term = get_queried_object();
        if ( isset( $term->name ) ) {
            $primary_category = $term->name;
        }
    } elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
        $content_group = 'Product Archives';
        $content_type  = 'product_tag_archive';
        $template_type = 'product_tag_archive';
    } elseif ( is_front_page() ) {
        $content_group = 'Static Pages';
        $content_type  = 'front_page';
        $template_type = 'front_page';
        $content_id    = get_queried_object_id();
    } elseif ( is_home() ) {
        $content_group = 'Blog Archives';
        $content_type  = 'blog_home';
        $template_type = 'blog_home';
        $content_id    = get_queried_object_id();
    } elseif ( is_singular() ) {
        $post_type     = get_post_type();
        $content_id    = get_the_ID();
        $content_title = get_the_title( $content_id );
        $template_type = 'singular';

        if ( 'post' === $post_type ) {
            $content_group    = 'Blog Posts';
            $content_type     = 'post';
            $primary_category = brs_ga4_gtm_tracking_get_first_term_name( $content_id, 'category' );
        } elseif ( 'page' === $post_type ) {
            $content_group = 'Static Pages';
            $content_type  = 'page';
        } else {
            $content_group = 'Custom Post Types';
            $content_type  = $post_type;
        }
    } elseif ( is_category() ) {
        $content_group = 'Blog Archives';
        $content_type  = 'category_archive';
        $template_type = 'category_archive';

        $term = get_queried_object();
        if ( isset( $term->name ) ) {
            $primary_category = $term->name;
        }
    } elseif ( is_tag() ) {
        $content_group = 'Blog Archives';
        $content_type  = 'tag_archive';
        $template_type = 'tag_archive';
    } elseif ( is_author() ) {
        $content_group = 'Blog Archives';
        $content_type  = 'author_archive';
        $template_type = 'author_archive';
    } elseif ( is_date() ) {
        $content_group = 'Blog Archives';
        $content_type  = 'date_archive';
        $template_type = 'date_archive';
    } elseif ( is_search() ) {
        $content_group = 'Search';
        $content_type  = 'search';
        $template_type = 'search';
    } elseif ( is_404() ) {
        $content_group = 'Error Pages';
        $content_type  = '404';
        $template_type = '404';
    } elseif ( is_archive() ) {
        $content_group = 'Archives';
        $content_type  = 'archive';
        $template_type = 'archive';
    }

    return array(
        'content_group'    => $content_group,
        'content_type'     => $content_type,
        'post_type'        => $post_type,
        'content_id'       => $content_id,
        'content_title'    => $content_title,
        'primary_category' => $primary_category,
        'template_type'    => $template_type,
    );
}
