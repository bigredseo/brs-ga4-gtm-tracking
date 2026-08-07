<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get all valid terms assigned to a post for a taxonomy.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 *
 * @return WP_Term[]
 */
function brs_ga4_gtm_tracking_get_terms( $post_id, $taxonomy ) {
    $terms = get_the_terms( $post_id, $taxonomy );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return array();
    }

    return array_values( $terms );
}

/**
 * Get the full hierarchical path for a term.
 *
 * Example:
 * Email > Gmail
 *
 * @param WP_Term $term Term object.
 *
 * @return array
 */
function brs_ga4_gtm_tracking_get_term_path( $term ) {
    if ( ! $term instanceof WP_Term ) {
        return array();
    }

    $path = array();

    $ancestor_ids = get_ancestors(
        $term->term_id,
        $term->taxonomy,
        'taxonomy'
    );

    if ( ! empty( $ancestor_ids ) ) {
        $ancestor_ids = array_reverse( $ancestor_ids );

        foreach ( $ancestor_ids as $ancestor_id ) {
            $ancestor = get_term( $ancestor_id, $term->taxonomy );

            if ( $ancestor instanceof WP_Term ) {
                $path[] = $ancestor->name;
            }
        }
    }

    $path[] = $term->name;

    return $path;
}

/**
 * Get an SEO-plugin-defined primary taxonomy term.
 *
 * Priority:
 * 1. Rank Math
 * 2. Yoast SEO
 *
 * Returns both the term and the source that selected it.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 *
 * @return array
 */
function brs_ga4_gtm_tracking_get_seo_primary_term( $post_id, $taxonomy ) {
    $result = array(
        'term'   => null,
        'source' => '',
    );

    /*
     * Rank Math.
     *
     * Rank Math stores primary taxonomy terms as:
     * rank_math_primary_{$taxonomy}
     *
     * Example:
     * rank_math_primary_category
     * rank_math_primary_product_cat
     */
    if ( defined( 'RANK_MATH_VERSION' ) ) {
        $primary_term_id = absint(
            get_post_meta(
                $post_id,
                'rank_math_primary_' . $taxonomy,
                true
            )
        );

        if ( $primary_term_id ) {
            $term = get_term( $primary_term_id, $taxonomy );

            if (
                $term instanceof WP_Term
                && has_term( $term->term_id, $taxonomy, $post_id )
            ) {
                $result['term']   = $term;
                $result['source'] = 'rank_math';

                return $result;
            }
        }
    }

    /*
     * Yoast SEO.
     */
    if ( class_exists( 'WPSEO_Primary_Term' ) ) {
        $yoast_primary = new WPSEO_Primary_Term(
            $taxonomy,
            $post_id
        );

        $primary_term_id = absint(
            $yoast_primary->get_primary_term()
        );

        if ( $primary_term_id ) {
            $term = get_term( $primary_term_id, $taxonomy );

            if (
                $term instanceof WP_Term
                && has_term( $term->term_id, $taxonomy, $post_id )
            ) {
                $result['term']   = $term;
                $result['source'] = 'yoast';

                return $result;
            }
        }
    }

    return $result;
}

/**
 * Select the primary taxonomy term.
 *
 * Priority:
 * 1. Rank Math primary term
 * 2. Yoast SEO primary term
 * 3. Deepest assigned hierarchical term
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 *
 * @return array
 */
function brs_ga4_gtm_tracking_get_primary_term( $post_id, $taxonomy ) {
    /*
     * First honor the SEO plugin's explicit primary term.
     */
    $seo_primary =
        brs_ga4_gtm_tracking_get_seo_primary_term(
            $post_id,
            $taxonomy
        );

    if ( $seo_primary['term'] instanceof WP_Term ) {
        return $seo_primary;
    }

    /*
     * No SEO primary term exists.
     * Fall back to the deepest assigned term.
     */
    $terms = brs_ga4_gtm_tracking_get_terms(
        $post_id,
        $taxonomy
    );

    if ( empty( $terms ) ) {
        return array(
            'term'   => null,
            'source' => '',
        );
    }

    usort(
        $terms,
        function ( $a, $b ) {
            $a_depth = count(
                get_ancestors(
                    $a->term_id,
                    $a->taxonomy,
                    'taxonomy'
                )
            );

            $b_depth = count(
                get_ancestors(
                    $b->term_id,
                    $b->taxonomy,
                    'taxonomy'
                )
            );

            if ( $a_depth === $b_depth ) {
                return $a->term_id <=> $b->term_id;
            }

            return $b_depth <=> $a_depth;
        }
    );

    return array(
        'term'   => reset( $terms ),
        'source' => 'fallback',
    );
}

/**
 * Build analytics context for a taxonomy.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 *
 * @return array
 */
function brs_ga4_gtm_tracking_get_taxonomy_context( $post_id, $taxonomy ) {
    $context = array(
        'primary_term'       => '',
        'primary_source'     => '',
        'root_term'        => '',
        'term_path'          => '',
        'all_terms'          => '',
        'secondary_terms'    => '',
    );

    $terms = brs_ga4_gtm_tracking_get_terms(
        $post_id,
        $taxonomy
    );

    if ( empty( $terms ) ) {
        return $context;
    }

    $primary =
        brs_ga4_gtm_tracking_get_primary_term(
            $post_id,
            $taxonomy
        );

    $primary_term = $primary['term'];

    if ( ! $primary_term instanceof WP_Term ) {
        return $context;
    }

    $path =
        brs_ga4_gtm_tracking_get_term_path(
            $primary_term
        );

    $context['primary_term']   = $primary_term->name;
    $context['primary_source'] = $primary['source'];
    $context['term_path']      = implode( ' > ', $path );

    /*
     * For reporting, the parent category represents the
     * highest-level category in the primary term's hierarchy.
     */
    if ( count( $path ) > 1 ) {
        $context['root_term'] = reset( $path );
    }

    /*
     * All directly assigned taxonomy terms.
     */
    $term_names = wp_list_pluck( $terms, 'name' );

    natcasesort( $term_names );

    $context['all_terms'] = implode(
        ' | ',
        array_values( $term_names )
    );

    /*
     * Secondary terms are every directly assigned term
     * except the selected primary.
     */
    $secondary_names = array();

    foreach ( $terms as $term ) {
        if ( $term->term_id === $primary_term->term_id ) {
            continue;
        }

        $secondary_names[] = $term->name;
    }

    if ( ! empty( $secondary_names ) ) {
        natcasesort( $secondary_names );

        $context['secondary_terms'] = implode(
            ' | ',
            array_values( $secondary_names )
        );
    }

    return $context;
}

/**
 * Backwards-compatible helper.
 *
 * Previously this returned the first term WordPress supplied.
 * It now returns the SEO-defined primary term when available,
 * falling back to the deepest assigned hierarchical term.
 */
function brs_ga4_gtm_tracking_get_first_term_name( $post_id, $taxonomy ) {
    $primary =
        brs_ga4_gtm_tracking_get_primary_term(
            $post_id,
            $taxonomy
        );

    return $primary['term'] instanceof WP_Term
        ? $primary['term']->name
        : '';
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
    $primary_category        = '';
    $primary_category_source = '';
    $secondary_categories    = '';
    $category_root        = '';
    $category_path           = '';
    $categories              = '';
    $template_type           = 'other';

    if ( function_exists( 'is_cart' ) && is_cart() ) {

        $content_group = 'Ecommerce';
        $content_type  = 'cart';
        $template_type = 'cart';

    } elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {

        $content_group = 'Ecommerce';
        $content_type  = (
            function_exists( 'is_order_received_page' )
            && is_order_received_page()
        )
            ? 'order_received'
            : 'checkout';

        $template_type = $content_type;

    } elseif ( function_exists( 'is_product' ) && is_product() ) {

        $content_group = 'Products';
        $content_type  = 'product';
        $post_type     = 'product';
        $content_id    = get_the_ID();
        $content_title = get_the_title( $content_id );
        $template_type = 'product';

        $taxonomy_context =
            brs_ga4_gtm_tracking_get_taxonomy_context(
                $content_id,
                'product_cat'
            );

        $primary_category        = $taxonomy_context['primary_term'];
        $primary_category_source = $taxonomy_context['primary_source'];
        $secondary_categories    = $taxonomy_context['secondary_terms'];
        $category_root        = $taxonomy_context['root_term'];
        $category_path           = $taxonomy_context['term_path'];
        $categories              = $taxonomy_context['all_terms'];

    } elseif ( function_exists( 'is_shop' ) && is_shop() ) {

        $content_group = 'Product Archives';
        $content_type  = 'shop';
        $template_type = 'shop';

    } elseif (
        function_exists( 'is_product_category' )
        && is_product_category()
    ) {

        $content_group = 'Product Archives';
        $content_type  = 'product_category_archive';
        $template_type = 'product_category_archive';

        $term = get_queried_object();

        if ( $term instanceof WP_Term ) {
            $path = brs_ga4_gtm_tracking_get_term_path( $term );

            $primary_category = $term->name;
            $category_path    = implode( ' > ', $path );

            if ( count( $path ) > 1 ) {
                $category_root= reset( $path );
            }
        }

    } elseif (
        function_exists( 'is_product_tag' )
        && is_product_tag()
    ) {

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

            $content_group = 'Blog Posts';
            $content_type  = 'post';

            $taxonomy_context =
                brs_ga4_gtm_tracking_get_taxonomy_context(
                    $content_id,
                    'category'
                );

                $primary_category =
                    $taxonomy_context['primary_term'];

                $primary_category_source =
                    $taxonomy_context['primary_source'];

                $secondary_categories =
                    $taxonomy_context['secondary_terms'];

                $category_root=
                    $taxonomy_context['root_term'];

                $category_path =
                    $taxonomy_context['term_path'];

                $categories =
                    $taxonomy_context['all_terms'];

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

        if ( $term instanceof WP_Term ) {
            $path = brs_ga4_gtm_tracking_get_term_path( $term );

            $primary_category = $term->name;
            $category_path    = implode( ' > ', $path );

            if ( count( $path ) > 1 ) {
                $category_root= reset( $path );
            }
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

        /*
         * Category/taxonomy reporting.
         */
        'primary_category'        => $primary_category,
        'primary_category_source' => $primary_category_source,
        'secondary_categories'    => $secondary_categories,
        'category_root'           => $category_root,
        'category_path'           => $category_path,
        'categories'              => $categories,

        'template_type'           => $template_type,
    );
}