<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Kelsie FAQ Block — unified render
 *
 * Requires ACF subfields (constants):
 * - KELSIE_FAQ_REPEATER
 * - KELSIE_FAQ_QUESTION
 * - KELSIE_FAQ_ANSWER
 * - KELSIE_FAQ_CATEGORY   (ACF taxonomy/select; can return terms, IDs, or strings)
 * - KELSIE_OPTIONS_ID     (Options page ID for fallback)
 *
 * Optional ACF fields on the block or page:
 * - include_categories   (array of terms/IDs/slugs)
 * - exclude_categories   (array of terms/IDs/slugs)
 * - faq_categories_to_show (page-level fallback include list)
 * - faq_reviewer_name    (per-row reviewer label)
 * - faq_reviewer_url     (per-row profile URL for sameAs)
 * - faq_rating           (per-row numeric rating 0-5)
 */

function kelsie_render_faq_block( $block, $content = '', $is_preview = false ) {

    // 0) Guard: ACF inactive
    if ( ! function_exists('get_field') ) {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>ACF is inactive. Activate ACF to display reviews.</em></div>';
        }
        return;
    }

    // 1) Wrapper attributes
    $block_id   = 'faq-list-' . ( $block['id'] ?? uniqid() );
    $anchor     = ! empty( $block['anchor'] ) ? $block['anchor'] : $block_id;
    $class_name = 'kelsie-faq-list kelsie-faq-list--reviews';
    if ( ! empty( $block['className'] ) ) $class_name .= ' ' . $block['className'];
    if ( ! empty( $block['align'] ) )     $class_name .= ' align' . $block['align'];

    // 2) Helper: normalize any category value(s) to ['slug','label']
    $to_terms = function( $terms ) {
        $out = [];
        if (empty($terms)) return $out;

        foreach ( (array) $terms as $term ) {
            // a) Numeric ID
            if ( is_numeric($term) ) {
                $t = get_term( (int) $term ); // taxonomy optional; WP will resolve when unique
                if ( $t && ! is_wp_error($t) ) {
                    $out[] = [
                        'slug'  => sanitize_title($t->slug),
                        'label' => sanitize_text_field($t->name),
                    ];
                }
                continue;
            }
            // b) WP_Term object
            if ( is_object($term) && isset($term->term_id) ) {
                $out[] = [
                    'slug'  => sanitize_title($term->slug),
                    'label' => sanitize_text_field($term->name ?? $term->slug),
                ];
                continue;
            }
            // c) String (slug/label)
            if ( is_string($term) ) {
                $slug  = sanitize_title($term);
                $label = trim( wp_strip_all_tags($term) );
                $out[] = [
                    'slug'  => $slug,
                    'label' => $label ?: $slug,
                ];
            }
        }

        // de-dupe by slug
        $seen = [];
        $uniq = [];
        foreach ($out as $t) {
            if (!isset($seen[$t['slug']])) {
                $seen[$t['slug']] = true;
                $uniq[] = $t;
            }
        }
        return $uniq;
    };

    // 3) Choose source: post repeater first, fallback to options
    $context_id = null;
    $source     = null;

    if ( have_rows( KELSIE_FAQ_REPEATER ) ) {
        $context_id = get_the_ID();
        $source     = 'post';
    } elseif ( have_rows( KELSIE_FAQ_REPEATER, KELSIE_OPTIONS_ID ) ) {
        $context_id = KELSIE_OPTIONS_ID;
        $source     = 'option';
    } else {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>No reviews found. Add rows on this post or in the Options Page.</em></div>';
        }
        return;
    }

    // 4) Optional include/exclude lists (block-level first, then page-level fallback for include)
    $include_terms = get_field('include_categories');
    $exclude_terms = get_field('exclude_categories');

    if ( empty($include_terms) && empty($exclude_terms) ) {
        $include_terms = get_field('faq_categories_to_show', get_the_ID());
    }

    $include      = $to_terms($include_terms);
    $exclude      = $to_terms($exclude_terms);
    $include_slugs = array_values( array_unique( array_map( fn($t) => $t['slug'], $include ) ) );
    $exclude_slugs = array_values( array_unique( array_map( fn($t) => $t['slug'], $exclude ) ) );

    // 5) Collect rows -> normalized items (and filter)
    $items = [];
    while ( have_rows( KELSIE_FAQ_REPEATER, $context_id ) ) {
        the_row();

        $q_raw = get_sub_field( KELSIE_FAQ_QUESTION );
        $a_raw = get_sub_field( KELSIE_FAQ_ANSWER );
        $cats  = get_sub_field( KELSIE_FAQ_CATEGORY );  // may be term objects, IDs, strings, or arrays
        $cats_n = $to_terms( $cats );
        $reviewer  = get_sub_field( 'faq_reviewer_name' );
        $same_as   = get_sub_field( 'faq_reviewer_url' );
        $rating_raw = get_sub_field( 'faq_rating' );

        $cat_slugs = array_map( fn($t) => $t['slug'], $cats_n );

        // include: must match at least one if provided
        if ( ! empty($include_slugs) && empty( array_intersect($include_slugs, $cat_slugs) ) ) {
            continue;
        }
        // exclude: must not match any
        if ( ! empty($exclude_slugs) && ! empty( array_intersect($exclude_slugs, $cat_slugs) ) ) {
            continue;
        }

        $rating_val = null;
        if ( is_numeric( $rating_raw ) ) {
            $rating_val = max( 0, min( 5, (float) $rating_raw ) );
        }

        $items[] = [
            'question' => is_string($q_raw) ? trim( wp_strip_all_tags($q_raw) ) : '',
            'answer'   => is_string($a_raw) ? $a_raw : '',
            'cats'     => $cats_n, // ['slug','label']
            'reviewer' => is_string( $reviewer ) ? trim( wp_strip_all_tags( $reviewer ) ) : '',
            'same_as'  => is_string( $same_as ) ? esc_url_raw( $same_as ) : '',
            'rating'   => $rating_val,
        ];
    }

    if ( empty($items) ) {
        echo '<div class="kelsie-faq-list__empty"><em>No reviews match the current filters.</em></div>';
        return;
    }

    // 6) Build unique cat list for the <select>
    $all_cats = [];
    foreach ($items as $it) {
        foreach ($it['cats'] as $t) {
            $all_cats[$t['slug']] = $t['label'];
        }
    }
    ksort($all_cats, SORT_NATURAL | SORT_FLAG_CASE);

    // Editor hint
    if ( $is_preview ) {
        echo '<div style="font:12px/1.4 system-ui;opacity:.75;margin-bottom:.5rem;">Rendering reviews from <strong>'
           . esc_html( $source === 'post' ? 'this post' : 'Options Page' )
           . '</strong>.</div>';
    }
    ?>

    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">

        <!-- Toolbar: Category, Search, Count (local-only; no form/role to avoid 3rd-party search hijacks) -->
        <div class="kelsie-faq-list__toolbar" aria-label="Review filters">
            <label class="kelsie-faq-list__control">
                <span class="kelsie-faq-list__control-label">Category</span>
                <select class="kelsie-faq-list__filter" aria-controls="<?php echo esc_attr($anchor); ?>">
                    <option value="">All</option>
                    <?php foreach ($all_cats as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="kelsie-faq-list__control">
                <span class="kelsie-faq-list__control-label">Search</span>
                <input type="search"
                    class="kelsie-faq-list__search"
                    placeholder="Type to filter…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-controls="<?php echo esc_attr($anchor); ?>" />
            </label>

            <span class="kelsie-faq-list__count" aria-live="polite"></span>
        </div>

        <!-- Items -->
        <div class="kelsie-faq-list__items" role="list">
            <?php
            $i = 0;
            foreach ($items as $it):
                $i++;
                $q      = $it['question'] ?: 'Untitled review';
                $a_html = wpautop( $it['answer'] );
                $cat_attr = '';
                $reviewer = $it['reviewer'];
                $same_as  = $it['same_as'];
                $rating   = $it['rating'];
                $chips    = '';

                if ( ! empty($it['cats']) ) {
                    $slugs = array_map(fn($t) => $t['slug'], $it['cats']);
                    $cat_attr = strtolower( implode('|', array_unique(array_map('sanitize_title', $slugs))) );
                    foreach ($it['cats'] as $t) {
                        $chips .= '<span class="kelsie-faq-list__chip">' . esc_html($t['label']) . '</span>';
                    }
                }

                $panel_id   = esc_attr($anchor . '-item-' . $i);
                $summary_id = esc_attr($panel_id . '-summary');
            ?>
            <article class="kelsie-faq-list__item"
                     id="<?php echo $panel_id; ?>"
                     role="listitem"
                     data-cats="<?php echo esc_attr($cat_attr); ?>"
                     aria-labelledby="<?php echo esc_attr($summary_id); ?>">
                <div class="kelsie-faq-list__header">
                    <h3 id="<?php echo esc_attr($summary_id); ?>" class="kelsie-faq-list__question">
                        <?php echo esc_html($q); ?>
                    </h3>
                    <?php if ( null !== $rating ): ?>
                        <p class="kelsie-faq-list__rating" aria-label="Rating: <?php echo esc_attr( $rating ); ?> out of 5">
                            <span aria-hidden="true">★</span>
                            <strong><?php echo esc_html( $rating ); ?></strong>
                            <span aria-hidden="true">/5</span>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="kelsie-faq-list__answer">
                    <div class="kelsie-faq-list__answer-inner">
                        <?php echo wp_kses_post( $a_html ?: '<p>(No review text yet.)</p>' ); ?>
                    </div>
                    <div class="kelsie-faq-list__meta">
                        <?php if ( $reviewer ): ?>
                            <span class="kelsie-faq-list__label">Reviewer:</span>
                            <span class="kelsie-faq-list__reviewer">
                                <?php if ( $same_as ): ?>
                                    <a href="<?php echo esc_url( $same_as ); ?>" rel="nofollow noopener" target="_blank"><?php echo esc_html( $reviewer ); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html( $reviewer ); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($chips): ?>
                            <span class="kelsie-faq-list__label">Category:</span>
                            <?php echo $chips; // escaped above ?>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php
        // 7) JSON-LD (plain text only for safety) — only emit if Rank Math is not available
        if ( ! defined( 'RANK_MATH_VERSION' ) ) {
            $permalink = function_exists( 'get_permalink' ) ? get_permalink() : '';
            $graph = [];

            foreach ( array_values( $items ) as $idx => $it ) {
                $node = [
                    '@type'      => 'Review',
                    'name'       => $it['question'] ?: 'Review ' . ( $idx + 1 ),
                    'reviewBody' => wp_strip_all_tags( $it['answer'] ),
                ];

                if ( $permalink ) {
                    $node['@id'] = trailingslashit( $permalink ) . '#review-' . ( $idx + 1 );
                }

                if ( $it['reviewer'] ) {
                    $node['author'] = [
                        '@type' => 'Person',
                        'name'  => $it['reviewer'],
                    ];
                }

                if ( $it['same_as'] ) {
                    $node['sameAs'] = $it['same_as'];
                }

                if ( null !== $it['rating'] ) {
                    $node['reviewRating'] = [
                        '@type'       => 'Rating',
                        'ratingValue' => $it['rating'],
                        'bestRating'  => 5,
                        'worstRating' => 1,
                    ];
                }

                $graph[] = $node;
            }

            if ( ! empty( $graph ) ) {
                $ld = [
                    '@context' => 'https://schema.org',
                    '@graph'   => $graph,
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
if ( isset($block) ) {
    kelsie_render_faq_block( $block, $content ?? '', $is_preview ?? false );
}