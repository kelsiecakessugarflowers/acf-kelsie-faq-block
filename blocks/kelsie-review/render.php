<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Kelsie Reviews Block — unified render
 *
 * Requires ACF subfields (constants):
 * - KELSIE_REVIEW_REPEATER
 * - KELSIE_REVIEW_BODY
 * - KELSIE_REVIEWER_NAME
 * - KELSIE_REVIEW_ID
 * - KELSIE_REVIEW_SAMEAS
 * - KELSIE_REVIEW_RATING
 * - KELSIE_REVIEW_TITLE
 * - KELSIE_OPTIONS_ID
 */
function kelsie_render_review_block( $block, $content = '', $is_preview = false ) {
    // 0) Guard: ACF inactive
    if ( ! function_exists( 'get_field' ) ) {
        if ( ! empty( $is_preview ) ) {
            echo '<div class="kelsie-review-list__empty"><em>ACF is inactive. Activate ACF to display reviews.</em></div>';
        }
        return;
    }

    // 1) Wrapper attributes
    $block_id    = 'review-list-' . ( $block['id'] ?? uniqid() );
    $anchor_raw  = ! empty( $block['anchor'] ) ? $block['anchor'] : $block_id;
    $anchor_safe = sanitize_title( $anchor_raw );
    $anchor      = $anchor_safe ? $anchor_safe : sanitize_title( $block_id );
    $class_name = 'kelsie-review-list';
    if ( ! empty( $block['className'] ) ) {
        $class_name .= ' ' . $block['className'];
    }

    $wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes(
            [
                'id'    => $anchor,
                'class' => $class_name,
            ]
        )
        : sprintf(
            'id="%s" class="%s"',
            esc_attr( $anchor ),
            esc_attr( $class_name )
        );

    // 2) Choose source: post repeater first, fallback to options
    $context_id = null;
    $source     = null;

    if ( have_rows( KELSIE_REVIEW_REPEATER ) ) {
        $context_id = get_the_ID();
        $source     = 'post';
    } elseif ( have_rows( KELSIE_REVIEW_REPEATER, KELSIE_OPTIONS_ID ) ) {
        $context_id = KELSIE_OPTIONS_ID;
        $source     = 'option';
    } else {
        if ( ! empty( $is_preview ) ) {
            echo '<div class="kelsie-review-list__empty"><em>No reviews found. Add rows on this post or in the Options Page.</em></div>';
        }
        return;
    }

    // 3) Collect rows -> normalized items
    $items = [];
    while ( have_rows( KELSIE_REVIEW_REPEATER, $context_id ) ) {
        the_row();

        $body_raw     = get_sub_field( KELSIE_REVIEW_BODY );
        $reviewer_raw = get_sub_field( KELSIE_REVIEWER_NAME );
        $title_raw    = get_sub_field( KELSIE_REVIEW_TITLE );
        $rating_raw   = get_sub_field( KELSIE_REVIEW_RATING );
        $same_as_raw  = get_sub_field( KELSIE_REVIEW_SAMEAS );
        $id_raw       = get_sub_field( KELSIE_REVIEW_ID );

        $body     = is_string( $body_raw ) ? trim( $body_raw ) : '';
        $reviewer = is_string( $reviewer_raw ) ? trim( $reviewer_raw ) : '';

        if ( '' === $body || '' === $reviewer ) {
            continue;
        }

        $title   = is_string( $title_raw ) ? trim( $title_raw ) : '';
        $rating  = is_numeric( $rating_raw ) ? max( 0, min( 5, (float) $rating_raw ) ) : null;
        $same_as = $same_as_raw ? esc_url( $same_as_raw ) : '';
        $id      = $id_raw ? sanitize_title( $id_raw ) : '';

        $items[] = [
            'title'      => $title,
            'body'       => wpautop( $body ),
            'body_plain' => wp_strip_all_tags( $body ),
            'reviewer'   => wp_strip_all_tags( $reviewer ),
            'rating'     => $rating,
            'same_as'    => $same_as,
            'review_id'  => $id,
        ];
    }

    if ( empty( $items ) ) {
        echo '<div class="kelsie-review-list__empty"><em>No reviews available.</em></div>';
        return;
    }

    // Editor hint
    if ( $is_preview ) {
        echo '<div style="font:12px/1.4 system-ui;opacity:.75;margin-bottom:.5rem;">Rendering reviews from <strong>'
           . esc_html( 'post' === $source ? 'this post' : 'Options Page' )
           . '</strong>.</div>';
    }
    ?>

    <section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
        <div class="kelsie-review-list__items" role="list">
            <?php
            $index = 0;
            foreach ( $items as $item ) :
                $index++;
                $title     = $item['title'] ? $item['title'] : 'Review ' . $index;
                $reviewer  = $item['reviewer'];
                $body_html = $item['body'];
                ?>
                <article class="kelsie-review" role="listitem">
                    <header class="kelsie-review__header">
                        <h3 class="kelsie-review__title"><?php echo esc_html( $title ); ?></h3>
                        <p class="kelsie-review__byline">Review by <span class="kelsie-review__reviewer"><?php echo esc_html( $reviewer ); ?></span></p>
                    </header>
                    <div class="kelsie-review__body">
                        <?php echo wp_kses_post( $body_html ); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php
        // 4) JSON-LD fallback only when Rank Math is inactive to avoid duplicate schema
        if ( ! defined( 'RANK_MATH_VERSION' ) ) {
            $item_reviewed = [
                '@type' => 'CreativeWork',
                '@id'   => esc_url_raw( get_permalink( $context_id ) . '#item' ),
                'name'  => wp_strip_all_tags( get_the_title( $context_id ) ),
                'url'   => get_permalink( $context_id ),
            ];

            $ld_reviews = array_map(
                function ( $item ) use ( $context_id, $item_reviewed ) {
                    $review = [
                        '@type'        => 'Review',
                        'reviewBody'   => wp_strip_all_tags( $item['body_plain'] ),
                        'author'       => [
                            '@type' => 'Person',
                            'name'  => wp_strip_all_tags( $item['reviewer'] ),
                        ],
                        'itemReviewed' => $item_reviewed,
                    ];

                    if ( ! empty( $item['title'] ) ) {
                        $review['name'] = wp_strip_all_tags( $item['title'] );
                    }

                    if ( null !== $item['rating'] ) {
                        $review['reviewRating'] = [
                            '@type'       => 'Rating',
                            'ratingValue' => $item['rating'],
                            'bestRating'  => 5,
                            'worstRating' => 0,
                        ];
                    }

                    if ( ! empty( $item['same_as'] ) ) {
                        $review['sameAs'] = esc_url_raw( $item['same_as'] );
                    }

                    if ( ! empty( $item['review_id'] ) ) {
                        $review['@id'] = esc_url_raw( get_permalink( $context_id ) . '#review-' . $item['review_id'] );
                    }

                    return $review;
                },
                $items
            );

            if ( ! empty( $ld_reviews ) ) {
                $ld = [
                    '@context' => 'https://schema.org',
                    '@graph'   => array_values( $ld_reviews ),
                ];
                ?>
                <script type="application/ld+json"><?php echo wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); ?></script>
                <?php
            }
        }
        ?>
    </section>
    <?php
}

// Important: ACF "Render Template" mode includes this file; if it’s included, call directly:
if ( isset( $block ) ) {
    kelsie_render_review_block( $block, $content ?? '', $is_preview ?? false );
}
