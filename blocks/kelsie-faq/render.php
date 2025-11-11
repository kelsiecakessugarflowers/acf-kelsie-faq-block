<?php
/**
 * Render callback for Kelsie FAQ block.
 *
 * @param array $block The block settings and attributes.
 * @param string $content The block inner HTML (if there is any).
 * @param bool $is_preview True during admin preview.
 */
function kelsie_render_faq_block( $block, $content = '', $is_preview = false ) {
    // Grab all FAQs, preferring per-post repeater then the options page.
    $faqs = [];
    if ( have_rows( KELSIE_FAQ_REPEATER ) ) {
        $context_id = get_the_ID();
    } elseif ( have_rows( KELSIE_FAQ_REPEATER, KELSIE_OPTIONS_ID ) ) {
        $context_id = KELSIE_OPTIONS_ID;
    } else {
        echo '<p>No FAQs found.</p>';
        return;
    }

    // Determine categories to include/exclude
    $include_terms = get_field( 'include_categories' );
    $exclude_terms = get_field( 'exclude_categories' );

    // Fall back to a per-page field if neither is set on the block
    if ( empty( $include_terms ) && empty( $exclude_terms ) ) {
        $include_terms = get_field( 'faq_categories_to_show', get_the_ID() );
    }

    // Normalize to slugs for easier comparison
    $to_slugs = function ( $terms ) {
        $slugs = [];
        if ( empty( $terms ) ) {
            return [];
        }
        foreach ( (array) $terms as $term ) {
            if ( is_numeric( $term ) ) {
                $t = get_term( (int) $term, 'faq_category' );
                if ( $t && ! is_wp_error( $t ) ) {
                    $slugs[] = $t->slug;
                }
            } elseif ( is_object( $term ) && isset( $term->slug ) ) {
                $slugs[] = $term->slug;
            }
        }
        return $slugs;
    };

    $include_slugs = $to_slugs( $include_terms );
    $exclude_slugs = $to_slugs( $exclude_terms );

    // Collect matching FAQs
    while ( have_rows( KELSIE_FAQ_REPEATER, $context_id ) ) {
        the_row();
        $q = get_sub_field( KELSIE_FAQ_QUESTION );
        $a = get_sub_field( KELSIE_FAQ_ANSWER );
        $cats = $to_slugs( get_sub_field( KELSIE_FAQ_CATEGORY ) );

        if ( $include_slugs ) {
            // Row must match at least one include term
            if ( ! array_intersect( $include_slugs, $cats ) ) {
                continue;
            }
        }
        if ( $exclude_slugs ) {
            // Row must not match any excluded term
            if ( array_intersect( $exclude_slugs, $cats ) ) {
                continue;
            }
        }

        $faqs[] = [
            'question' => $q,
            'answer'   => $a,
        ];
    }

    if ( empty( $faqs ) ) {
        echo '<p>No FAQs match this filter.</p>';
        return;
    }

    // Output markup with your existing styles; this example uses simple HTML
    echo '<div class="kelsie-faqs" itemscope itemtype="https://schema.org/FAQPage">';
    foreach ( $faqs as $item ) {
        ?>
        <div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
            <button class="faq-question" itemprop="name">
                <?php echo esc_html( $item['question'] ); ?>
            </button>
            <div class="faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                <div class="faq-answer__inner" itemprop="text">
                    <?php echo wp_kses_post( wpautop( $item['answer'] ) ); ?>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}
