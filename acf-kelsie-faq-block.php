<?php
/**
 * Plugin Name: Kelsie ACF Reviews Block
 * Description: ACF block for review repeater with optional Rank Math schema.
 * Version:     1.1.0
 * Author:      Kelsie Cakes
 */

if (!defined('ABSPATH')) exit;

/** ---------------------------
 *  CONFIG (edit in one place)
 * --------------------------- */
define('KELSIE_BLOCK_DIR', __DIR__ . '/blocks/kelsie-reviews');
define('KELSIE_BLOCK_NAME', 'kelsiecakes/reviews-list');    // block.json "name"

define('KELSIE_REVIEW_REPEATER', 'reviews_acf_repeater'); // repeater
define('KELSIE_REVIEW_TITLE',    'review_title');        // sub field (Text)
define('KELSIE_REVIEW_BODY',     'review_body');         // sub field (Textarea/WYSIWYG)
define('KELSIE_REVIEW_NAME',     'reviewer_name');       // sub field (Text)
define('KELSIE_REVIEW_LOCATION', 'reviewer_location');   // sub field (Text)
define('KELSIE_REVIEW_RATING',   'review_rating');       // sub field (Number)



define('KELSIE_OPTIONS_ID',   'option');                // ACF Options Page id
define('KELSIE_SCHEMA_KEY',   'kelsie_reviews');        // array key in Rank Math graph

add_action('admin_init', function () {
    if (!class_exists('ACF') && current_user_can('activate_plugins')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Kelsie ACF Reviews Block:</strong> ACF is inactive. The block will show a placeholder until ACF is active.</p></div>';
        });
    }
});

add_action('init', function () {
    // Styles referenced by block.json
    $style_path        = plugin_dir_path(__FILE__) . 'assets/style.css';
    $editor_style_path = plugin_dir_path(__FILE__) . 'assets/editor.css';

    wp_register_style(
        'kelsie-faq-block',
        plugins_url('assets/style.css', __FILE__),
        [],
        file_exists($style_path) ? filemtime($style_path) : null
    );

    wp_register_style(
        'kelsie-faq-block-editor',
        plugins_url('assets/editor.css', __FILE__),
        [],
        file_exists($editor_style_path) ? filemtime($editor_style_path) : null
    );

    $block_json = trailingslashit(KELSIE_BLOCK_DIR) . 'block.json';

    if (file_exists($block_json)) {
        // Safe even if ACF is off; render.php guards itself.
        register_block_type(
            KELSIE_BLOCK_DIR,
            [
                'render_callback' => 'kelsie_render_faq_block',
            ]
        );
    }
});


/** ---------------------------
 *  Rank Math integration (optional)
 * --------------------------- */
add_action('plugins_loaded', function () {
    if (!defined('RANK_MATH_VERSION')) return;

    add_filter('rank_math/json_ld', function ($data, $jsonld) {
        if (!is_singular()) return $data;

        global $post;
        if (!$post || !function_exists('has_block') || !has_block(KELSIE_BLOCK_NAME, $post)) {
            return $data;
        }
        if (!function_exists('have_rows')) return $data; // ACF off

        // Prefer per-post rows; fall back to Options Page.
        $source = null;
        if (have_rows(KELSIE_REVIEW_REPEATER, $post->ID)) {
            $source = [KELSIE_REVIEW_REPEATER, $post->ID];
        } elseif (have_rows(KELSIE_REVIEW_REPEATER, KELSIE_OPTIONS_ID)) {
            $source = [KELSIE_REVIEW_REPEATER, KELSIE_OPTIONS_ID];
        } else {
            return $data;
        }

        $reviews = [];

        while (have_rows($source[0], $source[1])) {
            the_row();

            $title    = trim(wp_strip_all_tags(get_sub_field(KELSIE_REVIEW_TITLE)));
            $body_raw = get_sub_field(KELSIE_REVIEW_BODY);
            $body     = is_string($body_raw) ? trim(wp_strip_all_tags($body_raw)) : '';
            $name     = trim(wp_strip_all_tags(get_sub_field(KELSIE_REVIEW_NAME)));
            $location = trim(wp_strip_all_tags(get_sub_field(KELSIE_REVIEW_LOCATION)));
            $rating   = get_sub_field(KELSIE_REVIEW_RATING);

            $rating_value = null;
            if (is_numeric($rating)) {
                $rating_value = max(0, min(5, (float) $rating));
            }

            if ($title || $body || $name || $location || !is_null($rating_value)) {
                $author_label = $name;
                if ($location) {
                    $author_label = $author_label ? sprintf('%s (%s)', $author_label, $location) : $location;
                }

                $review = ['@type' => 'Review'];

                if ($title) {
                    $review['name'] = $title;
                }

                if ($body) {
                    $review['reviewBody'] = $body;
                }

                if ($author_label) {
                    $review['author'] = [
                        '@type' => 'Person',
                        'name'  => $author_label,
                    ];
                }

                if (!is_null($rating_value)) {
                    $review['reviewRating'] = [
                        '@type'      => 'Rating',
                        'ratingValue'=> $rating_value,
                        'bestRating' => 5,
                        'worstRating'=> 1,
                    ];
                }

                $reviews[] = $review;
            }
        }

        if (!empty($reviews)) {
            $list = [
                '@type' => 'ItemList',
                'itemListElement' => [],
            ];

            $position = 1;
            foreach ($reviews as $review) {
                $list['itemListElement'][] = [
                    '@type'   => 'ListItem',
                    'position'=> $position++,
                    'item'    => $review,
                ];
            }

            $data[KELSIE_SCHEMA_KEY] = $list; // append, don’t overwrite
        }

        return $data;
    }, 20, 2);
});
