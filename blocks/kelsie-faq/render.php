<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Kelsie FAQ Block — unified render
 *
 * Requires ACF subfields (constants):
 * - KELSIE_FAQ_REPEATER
 * - KELSIE_FAQ_QUESTION
 * - KELSIE_FAQ_ANSWER
 * - KELSIE_FAQ_CATEGORY  (ACF taxonomy/select; can return terms, IDs, or strings)
 * - KELSIE_OPTIONS_ID    (Options page ID for fallback)
 *
 * Optional ACF fields on the block or page:
 * - include_categories   (array of terms/IDs/slugs)
 * - exclude_categories   (array of terms/IDs/slugs)
 * - faq_categories_to_show (page-level fallback include list)
 */

function kelsie_render_faq_block( $block, $content = '', $is_preview = false ) {

    // 0) Guard: ACF inactive
    if ( ! function_exists('get_field') ) {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>ACF is inactive. Activate ACF to display FAQs.</em></div>';
        }
        return;
    }

    // 1) Wrapper attributes
    $block_id   = 'faq-list-' . ( $block['id'] ?? uniqid() );
    $anchor     = ! empty( $block['anchor'] ) ? $block['anchor'] : $block_id;
    $class_name = 'kelsie-faq-list';
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
            echo '<div class="kelsie-faq-list__empty"><em>No FAQs found. Add rows on this post or in the Options Page.</em></div>';
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

        $cat_slugs = array_map( fn($t) => $t['slug'], $cats_n );

        // include: must match at least one if provided
        if ( ! empty($include_slugs) && empty( array_intersect($include_slugs, $cat_slugs) ) ) {
            continue;
        }
        // exclude: must not match any
        if ( ! empty($exclude_slugs) && ! empty( array_intersect($exclude_slugs, $cat_slugs) ) ) {
            continue;
        }

        $items[] = [
            'question' => is_string($q_raw) ? trim( wp_strip_all_tags($q_raw) ) : '',
            'answer'   => is_string($a_raw) ? $a_raw : '',
            'cats'     => $cats_n, // ['slug','label']
        ];
    }

    if ( empty($items) ) {
        echo '<div class="kelsie-faq-list__empty"><em>No FAQs match the current filters.</em></div>';
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
        echo '<div style="font:12px/1.4 system-ui;opacity:.75;margin-bottom:.5rem;">Rendering FAQs from <strong>'
           . esc_html( $source === 'post' ? 'this post' : 'Options Page' )
           . '</strong>.</div>';
    }
    ?>

    <section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>" itemscope itemtype="https://schema.org/FAQPage">

        <!-- Toolbar: Category, Search, Count (local-only; no form/role to avoid 3rd-party search hijacks) -->
        <div class="kelsie-faq-list__toolbar" aria-label="FAQ filters">
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
                $q      = $it['question'] ?: 'Untitled question';
                $a_html = wpautop( $it['answer'] );
                $cat_attr = '';
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
            <details class="kelsie-faq-list__item"
                     id="<?php echo $panel_id; ?>"
                     role="listitem"
                     data-cats="<?php echo esc_attr($cat_attr); ?>"
                     itemscope
                     itemprop="mainEntity"
                     itemtype="https://schema.org/Question">
                <summary id="<?php echo $summary_id; ?>" class="kelsie-faq-list__question" itemprop="name">
                    <?php echo esc_html($q); ?>
                </summary>
                <div class="kelsie-faq-list__answer"
                     itemscope
                     itemprop="acceptedAnswer"
                     itemtype="https://schema.org/Answer">
                    <div class="kelsie-faq-list__answer-inner" itemprop="text">
                        <?php echo wp_kses_post( $a_html ?: '<p>(No answer yet.)</p>' ); ?>
                    </div>
                    <?php if ($chips): ?>
                        <div class="kelsie-faq-list__meta">
                            <span class="kelsie-faq-list__label">Category:</span>
                            <?php echo $chips; // escaped above ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>

        <?php
        // 7) JSON-LD (plain text only for safety)
        $rank_math_schema_enabled = defined('RANK_MATH_VERSION') && apply_filters('kelsie_faq_rank_math_schema_enabled', true);
        $schema_preference        = function_exists('get_option') ? get_option('kelsie_faq_schema_source', '') : '';
        $default_schema_source    = $rank_math_schema_enabled ? 'rank-math' : 'inline';

        $schema_source = $schema_preference ?: $default_schema_source;
        $schema_source = apply_filters(
            'kelsie_faq_schema_source',
            $schema_source,
            [
                'rank_math_active'         => defined('RANK_MATH_VERSION'),
                'rank_math_schema_enabled' => $rank_math_schema_enabled,
                'context_id'               => $context_id,
                'source'                   => $source,
                'items_count'              => count($items),
            ]
        );

        if ( ! in_array($schema_source, ['inline', 'rank-math', 'both'], true) ) {
            $schema_source = $default_schema_source;
        }

        if ( 'rank-math' !== $schema_source ) {
            $ld = [
                '@context'    => 'https://schema.org',
                '@type'       => 'FAQPage',
                'mainEntity'  => array_values(array_map(function($it) {
                    return [
                        '@type' => 'Question',
                        'name'  => wp_strip_all_tags($it['question']),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text'  => wp_strip_all_tags($it['answer']),
                        ],
                    ];
                }, $items)),
            ];
            ?>
            <script type="application/ld+json"><?php echo wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); ?></script>
            <?php
        }
        ?>
    </section>
    <?php
}

// Important: ACF "Render Template" mode includes this file; if it’s included, call directly:
if ( isset($block) ) {
    kelsie_render_faq_block( $block, $content ?? '', $is_preview ?? false );
}