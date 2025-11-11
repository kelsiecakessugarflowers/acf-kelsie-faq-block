<?php
/**
 * Render callback for Kelsie FAQ block.
 *
 * Preserves:
 * - Per-post repeater with fallback to Options
 * - Include / exclude categories (by slug), with page-level fallback field
 * - <details>/<summary> accordion UI
 * - Toolbar: category <select>, search input, live count
 * - Category chips per item
 * - Scoped, accessible markup
 * - Optional editor notices
 *
 * Expects constants:
 * - KELSIE_FAQ_REPEATER
 * - KELSIE_FAQ_QUESTION
 * - KELSIE_FAQ_ANSWER
 * - KELSIE_FAQ_CATEGORY (taxonomy terms or ACF select values)
 * - KELSIE_OPTIONS_ID
 */
if (!defined('ABSPATH')) { exit; }

function kelsie_render_faq_block( $block, $content = '', $is_preview = false ) {
    // Guard if ACF is missing
    if ( ! function_exists('get_field') ) {
        if ( ! empty($is_preview) ) {
            echo '<div class="kelsie-faq-list__empty"><em>ACF is inactive. Activate ACF to display FAQs.</em></div>';
        }
        return;
    }

    // Wrapper attributes
    $block_id   = 'faq-list-' . ( $block['id'] ?? uniqid() );
    $anchor     = ! empty( $block['anchor'] ) ? $block['anchor'] : $block_id;
    $class_name = 'kelsie-faq-list';
    if ( ! empty( $block['className'] ) ) $class_name .= ' ' . $block['className'];
    if ( ! empty( $block['align'] ) )     $class_name .= ' align' . $block['align'];

    // Helper: normalize various term shapes to slugs + readable labels
    $to_terms = function( $terms ) {
        $out = [];
        if ( empty( $terms ) ) { return $out; }
        foreach ( (array) $terms as $term ) {
            // WP term object
            if ( is_object( $term ) && isset( $term->slug ) ) {
                $out[] = ['slug' => sanitize_title( $term->slug ), 'label' => sanitize_text_field( $term->name ?? $term->slug )];
                continue;
            }
            // Numeric (term_id)
            if ( is_numeric( $term ) ) {
               $t = get_term( (int) $term, KELSIE_FAQ_TAX );
                if ( $t && ! is_wp_error( $t ) ) {
                    $out[] = ['slug' => sanitize_title( $t->slug ), 'label' => sanitize_text_field( $t->name )];
                }
                continue;
            }
            // String (slug or label)
            if ( is_string( $term ) ) {
                $slug  = sanitize_title( $term );
                $label = trim( wp_strip_all_tags( $term ) );
                $out[] = ['slug' => $slug, 'label' => $label ?: $slug];
            }
        }
        return $out;
    };

    // Determine data source: post first, then options
    $context_id = null;
    if ( have_rows( KELSIE_FAQ_REPEATER ) ) {
        $context_id = get_the_ID();
        $source     = 'post';
    } elseif ( have_rows( KELSIE_FAQ_REPEATER, KELSIE_OPTIONS_ID ) ) {
        $context_id = KELSIE_OPTIONS_ID;
        $source     = 'option';
    } else {
        if ( ! empty( $is_preview ) ) {
            echo '<div class="kelsie-faq-list__empty"><em>No FAQs found. Add rows on this post or in the Options Page.</em></div>';
        }
        return;
    }

    if ( $is_preview ) {
        echo '<div style="font:12px/1.4 system-ui;opacity:.75;margin-bottom:.5rem;">Rendering FAQs from <strong>'
            . esc_html( $source === 'post' ? 'this post' : 'Options Page' )
            . '</strong>.</div>';
    }

    // Include / exclude categories from block fields
    $include_terms = get_field( 'include_categories' );
    $exclude_terms = get_field( 'exclude_categories' );

    // Page-level fallback if neither is set on the block
    if ( empty( $include_terms ) && empty( $exclude_terms ) ) {
        $include_terms = get_field( 'faq_categories_to_show', get_the_ID() );
    }

    $include = $to_terms( $include_terms );
    $exclude = $to_terms( $exclude_terms );

    $include_slugs = array_values( array_unique( array_map( fn($t) => $t['slug'], $include ) ) );
    $exclude_slugs = array_values( array_unique( array_map( fn($t) => $t['slug'], $exclude ) ) );

    // Collect rows -> normalized array for rendering + JSON-LD
    $items = [];
    while ( have_rows( KELSIE_FAQ_REPEATER, $context_id ) ) {
        the_row();
        $q_raw  = get_sub_field( KELSIE_FAQ_QUESTION );
        $a_raw  = get_sub_field( KELSIE_FAQ_ANSWER );
        $cats   = get_sub_field( KELSIE_FAQ_CATEGORY ); // may be term objects, ids, strings, or array thereof
        $cats_n = $to_terms( $cats );

        // Term slug arrays for filtering + data attribute
        $cat_slugs = array_values( array_unique( array_map( fn($t) => $t['slug'], $cats_n ) ) );

        // Include: must match at least one if provided
        if ( ! empty( $include_slugs ) ) {
            if ( empty( array_intersect( $include_slugs, $cat_slugs ) ) ) {
                continue;
            }
        }
        // Exclude: must not match any
        if ( ! empty( $exclude_slugs ) ) {
            if ( ! empty( array_intersect( $exclude_slugs, $cat_slugs ) ) ) {
                continue;
            }
        }

        // Keep
        $items[] = [
            'question' => is_string( $q_raw ) ? trim( wp_strip_all_tags( $q_raw ) ) : '',
            'answer'   => is_string( $a_raw ) ? $a_raw : '',
            'cats'     => $cats_n, // array of ['slug','label']
        ];
    }

    if ( empty( $items ) ) {
        echo '<p>No FAQs match this filter.</p>';
        return;
    }

    // Build a set of unique categories present (post-filter) for the <select>
    $all_cats = [];
    foreach ( $items as $it ) {
        foreach ( $it['cats'] as $t ) {
            $all_cats[ $t['slug'] ] = $t['label'];
        }
    }
    ksort( $all_cats, SORT_NATURAL | SORT_FLAG_CASE );

    // Begin output
    ?>
    <section id="<?php echo esc_attr( $anchor ); ?>" class="<?php echo esc_attr( $class_name ); ?>" itemscope itemtype="https://schema.org/FAQPage">
        <div class="kelsie-faq-list__toolbar" aria-label="FAQ filters">
            <label class="kelsie-faq-list__control">
                <span class="kelsie-faq-list__control-label">Category</span>
                <select class="kelsie-faq-list__filter" aria-controls="<?php echo esc_attr( $anchor ); ?>">
                    <option value="">All</option>
                    <?php foreach ( $all_cats as $slug => $label ): ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="kelsie-faq-list__control">
                <span class="kelsie-faq-list__control-label">Search</span>
                <input type="search"
                    class="kelsie-faq-list__search"
                    placeholder="Type to filter…"
                    aria-controls="<?php echo esc_attr( $anchor ); ?>" />
            </label>

            <span class="kelsie-faq-list__count" aria-live="polite"></span>
        </div>

        <div class="kelsie-faq-list__items" role="list">
            <?php
            $i = 0;
            foreach ( $items as $it ):
                $i++;
                $q        = $it['question'] ?: 'Untitled question';
                $a_html   = wpautop( $it['answer'] );
                $cat_attr = '';
                $chips    = '';
                if ( ! empty( $it['cats'] ) ) {
                    $slugs = array_map( fn($t) => $t['slug'], $it['cats'] );
                    $cat_attr = strtolower( implode( '|', array_unique( array_map( 'sanitize_title', $slugs ) ) ) );
                    foreach ( $it['cats'] as $t ) {
                        $chips .= '<span class="kelsie-faq-list__chip">' . esc_html( $t['label'] ) . '</span>';
                    }
                }
                $panel_id   = esc_attr( $anchor . '-item-' . $i );
                $summary_id = esc_attr( $panel_id . '-summary' );
            ?>
            <details class="kelsie-faq-list__item"
                     id="<?php echo $panel_id; ?>"
                     role="listitem"
                     data-cats="<?php echo esc_attr( $cat_attr ); ?>"
                     itemscope
                     itemprop="mainEntity"
                     itemtype="https://schema.org/Question">
                <summary id="<?php echo $summary_id; ?>" class="kelsie-faq-list__question" itemprop="name">
                    <?php echo esc_html( $q ); ?>
                </summary>
                <div class="kelsie-faq-list__answer"
                     itemscope
                     itemprop="acceptedAnswer"
                     itemtype="https://schema.org/Answer">
                    <div class="kelsie-faq-list__answer-inner" itemprop="text">
                        <?php echo wp_kses_post( $a_html ?: '<p>(No answer yet.)</p>' ); ?>
                    </div>
                    <?php if ( $chips ): ?>
                        <div class="kelsie-faq-list__meta">
                            <span class="kelsie-faq-list__label">Category:</span>
                            <?php echo $chips; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>

        <?php
        // Minimal JSON-LD for FAQPage (questions + plain-text answers only)
        $ld = [
            '@context' => 'https://schema.org',
            '@type'    => 'FAQPage',
            'mainEntity' => array_values( array_map( function( $it ) {
                return [
                    '@type' => 'Question',
                    'name'  => wp_strip_all_tags( $it['question'] ),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        // Strip tags for LD; keep simple. Search uses the visible HTML above.
                        'text'  => wp_strip_all_tags( $it['answer'] ),
                    ],
                ];
            }, $items ) ),
        ];
        ?>
        <script type="application/ld+json"><?php echo wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>

        <script>
        (function(){
            var root      = document.getElementById('<?php echo esc_js( $anchor ); ?>');
            if (!root) return;

            var filterEl  = root.querySelector('.kelsie-faq-list__filter');
            var searchEl  = root.querySelector('.kelsie-faq-list__search');
            var countEl   = root.querySelector('.kelsie-faq-list__count');
            var itemsWrap = root.querySelector('.kelsie-faq-list__items');
            if (!itemsWrap) return;

            var items = Array.prototype.slice.call(itemsWrap.querySelectorAll('.kelsie-faq-list__item'));

            function normalize(s){ return (s||'').toLowerCase().trim(); }

            function matches(item, cat, q){
                // category filter
                if (cat) {
                    var catsAttr = (item.getAttribute('data-cats')||'').toLowerCase();
                    if (!catsAttr.split('|').includes(cat)) return false;
                }
                // text search: search in summary + answer
                if (q) {
                    var text = item.textContent.toLowerCase();
                    if (text.indexOf(q) === -1) return false;
                }
                return true;
            }

            function apply(){
                var cat = normalize(filterEl && filterEl.value);
                var q   = normalize(searchEl && searchEl.value);

                var visible = 0;
                items.forEach(function(item){
                    if (matches(item, cat, q)) {
                        item.style.display = '';
                        visible++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                if (countEl) {
                    countEl.textContent = visible + ' FAQ' + (visible === 1 ? '' : 's');
                }
            }

            if (filterEl) filterEl.addEventListener('change', apply);
            if (searchEl) searchEl.addEventListener('input', apply);

            // Initialize count on load
            apply();
        })();
        </script>
    </section>
    <?php
}
