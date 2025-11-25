<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Kelsie FAQ Block — unified render
 *
 * This template now renders review rows stored in the ACF repeater
 * defined in KELSIE_REVIEW_REPEATER. Only frontend-safe values are
 * shown. Backend-only metadata stays unused in the markup.
 */
function kelsie_render_faq_block( $block, $content = '', $is_preview = false ) {

    // 0) Guard: ACF inactive
    if ( ! function_exists('have_rows') ) {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>ACF is inactive. Activate ACF to display reviews.</em></div>';
        }
        return;
    }

    // 1) Wrapper attributes
    $block_id   = 'faq-list-' . ( $block['id'] ?? uniqid() );
    $anchor     = ! empty( $block['anchor'] ) ? $block['anchor'] : $block_id;
    $class_name = 'kelsie-faq-list kelsie-review-list';
    if ( ! empty( $block['className'] ) ) $class_name .= ' ' . $block['className'];
    if ( ! empty( $block['align'] ) )     $class_name .= ' align' . $block['align'];

    // 2) Choose source: block repeater first, fallback to options
    $context_id       = null;
    $source           = null;
    $block_context_id = $block['id'] ?? null;

    if ( $block_context_id && have_rows( KELSIE_REVIEW_REPEATER, $block_context_id ) ) {
        $context_id = $block_context_id;
        $source     = 'block';
    } elseif ( have_rows( KELSIE_REVIEW_REPEATER, KELSIE_OPTIONS_ID ) ) {
        $context_id = KELSIE_OPTIONS_ID;
        $source     = 'option';
    } else {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>No reviews found. Add rows on this post or in the Options Page.</em></div>';
        }
        return;
    }

    // 3) Collect review rows (frontend-safe values only)
    $reviews = [];
    while ( have_rows( KELSIE_REVIEW_REPEATER, $context_id ) ) {
        the_row();

        $body_raw  = get_sub_field( KELSIE_REVIEW_BODY );
        $name_raw  = get_sub_field( KELSIE_REVIEW_NAME );
        $title_raw = get_sub_field( KELSIE_REVIEW_TITLE );

        $body  = is_string($body_raw) ? trim($body_raw) : '';
        $name  = is_string($name_raw) ? trim($name_raw) : '';
        $title = is_string($title_raw) ? trim($title_raw) : '';

        if ( $body === '' || $name === '' ) {
            continue; // required fields must be present
        }

        $reviews[] = [
            'body'  => wpautop( wp_kses_post( $body ) ),
            'name'  => sanitize_text_field( $name ),
            'title' => $title ? wp_strip_all_tags( $title ) : '',
        ];
    }

    if ( empty($reviews) ) {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>Add at least one review row to display content.</em></div>';
        }
        return;
    }

    // Editor hint
    if ( $is_preview ) {
        echo '<div style="font:12px/1.4 system-ui;opacity:.75;margin-bottom:.5rem;">Rendering reviews from <strong>'
           . esc_html( $source === 'block' ? 'this block' : 'Options Page' )
           . '</strong>.</div>';
    }
    ?>

    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
        <div class="kelsie-faq-list__items" role="list">
            <?php foreach ($reviews as $i => $review):
                $heading_id = esc_attr($anchor . '-review-' . ($i + 1));
                $title_text = $review['title'] ?: sprintf(esc_html__('Review from %s', 'kelsie-faq-block'), $review['name']);
            ?>
            <article class="kelsie-faq-list__item kelsie-review-list__item" role="listitem" aria-labelledby="<?php echo $heading_id; ?>">
                <header class="kelsie-review-list__header">
                    <h3 id="<?php echo $heading_id; ?>" class="kelsie-review-list__title"><?php echo esc_html($title_text); ?></h3>
                    <p class="kelsie-review-list__byline">
                        <span class="screen-reader-text"><?php esc_html_e('Reviewer:', 'kelsie-faq-block'); ?> </span>
                        <span class="kelsie-review-list__reviewer"><?php echo esc_html($review['name']); ?></span>
                    </p>
                </header>
                <div class="kelsie-review-list__body">
                    <?php echo $review['body']; // sanitized above ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

// Important: ACF "Render Template" mode includes this file; if it’s included, call directly:
if ( isset($block) ) {
    kelsie_render_faq_block( $block, $content ?? '', $is_preview ?? false );
}
