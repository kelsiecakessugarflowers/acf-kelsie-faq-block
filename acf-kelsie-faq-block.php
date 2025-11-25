<?php
/**
 * Plugin Name: Kelsie ACF FAQ Block
 * Description: ACF block for FAQ repeater with optional Rank Math schema for FAQ page and using inside blocks.
 * Version:     1.0.6
 * Author:      Kelsie Cakes
 */

if (!defined('ABSPATH')) exit;

/** ---------------------------
 *  CONFIG (edit in one place)
 * --------------------------- */
define('KELSIE_BLOCK_DIR', __DIR__ . '/blocks/kelsie-faq');
define('KELSIE_BLOCK_NAME', 'kelsiecakes/faq-list');    // block.json "name"

define('KELSIE_FAQ_REPEATER', 'faq_acf_repeater');      // repeater
define('KELSIE_FAQ_QUESTION', 'faq_question');          // sub field (Text)
define('KELSIE_FAQ_ANSWER',   'faq_answer');            // sub field (WYSIWYG)
define('KELSIE_FAQ_CATEGORY', 'faq_category');          // sub field (Checkbox)
define('KELSIE_FAQ_TAX', 'faq-category');      // actual taxonomy slug

// Review repeater fields
define('KELSIE_REVIEW_REPEATER', 'kelsie_reviews');
define('KELSIE_REVIEW_BODY', 'review_body');
define('KELSIE_REVIEW_NAME', 'reviewer_name');
define('KELSIE_REVIEW_ID', 'review_id');
define('KELSIE_REVIEW_SOURCE', 'review_original_location');
define('KELSIE_REVIEW_RATING', 'review_rating');
define('KELSIE_REVIEW_TITLE', 'review_title');



define('KELSIE_OPTIONS_ID',   'option');                // ACF Options Page id
define('KELSIE_SCHEMA_KEY',   'kelsie_faq');            // array key in Rank Math graph

add_action('admin_init', function () {
    if (!class_exists('ACF') && current_user_can('activate_plugins')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Kelsie ACF FAQ Block:</strong> ACF is inactive. The block will show a placeholder until ACF is active.</p></div>';
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

    // Safe even if ACF is off; render.php guards itself.
    register_block_type(
    KELSIE_BLOCK_DIR,
    [
        'render_callback' => 'kelsie_render_faq_block',
    ]
);
});

add_action( 'init', function () {
    register_taxonomy(
        'faq-category',
        [], // no attached post type
        [
            'label'        => 'FAQ Categories',
            'public'       => false,
            'show_ui'      => true,
            'hierarchical' => true,
            'show_in_rest' => true,
        ]
    );
});

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_kelsie_reviews',
        'title' => 'Review Items',
        'fields' => [
            [
                'key' => 'field_kelsie_reviews',
                'label' => 'Reviews',
                'name' => KELSIE_REVIEW_REPEATER,
                'type' => 'repeater',
                'layout' => 'row',
                'button_label' => 'Add Review',
                'sub_fields' => [
                    [
                        'key' => 'field_kelsie_review_body',
                        'label' => 'Review Body',
                        'name' => KELSIE_REVIEW_BODY,
                        'type' => 'textarea',
                        'required' => 1,
                        'rows' => 4,
                    ],
                    [
                        'key' => 'field_kelsie_reviewer_name',
                        'label' => 'Reviewer Name',
                        'name' => KELSIE_REVIEW_NAME,
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'key' => 'field_kelsie_review_id',
                        'label' => 'Review ID',
                        'name' => KELSIE_REVIEW_ID,
                        'type' => 'text',
                        'required' => 0,
                        'wrapper' => [
                            'class' => 'acf-hide',
                        ],
                    ],
                    [
                        'key' => 'field_kelsie_review_source',
                        'label' => 'Original Location (URL)',
                        'name' => KELSIE_REVIEW_SOURCE,
                        'type' => 'url',
                        'required' => 0,
                        'wrapper' => [
                            'class' => 'acf-hide',
                        ],
                    ],
                    [
                        'key' => 'field_kelsie_review_rating',
                        'label' => 'Rating (1–5)',
                        'name' => KELSIE_REVIEW_RATING,
                        'type' => 'number',
                        'required' => 0,
                        'min' => 1,
                        'max' => 5,
                        'step' => 1,
                        'wrapper' => [
                            'class' => 'acf-hide',
                        ],
                    ],
                    [
                        'key' => 'field_kelsie_review_title',
                        'label' => 'Review Title (optional)',
                        'name' => KELSIE_REVIEW_TITLE,
                        'type' => 'text',
                        'required' => 0,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'block',
                    'operator' => '==',
                    'value' => KELSIE_BLOCK_NAME,
                ],
            ],
        ],
        'position' => 'acf_after_title',
        'hide_on_screen' => ['the_content'],
    ]);
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
        if (have_rows(KELSIE_FAQ_REPEATER, $post->ID)) {
            $source = [KELSIE_FAQ_REPEATER, $post->ID];
        } elseif (have_rows(KELSIE_FAQ_REPEATER, KELSIE_OPTIONS_ID)) {
            $source = [KELSIE_FAQ_REPEATER, KELSIE_OPTIONS_ID];
        } else {
            return $data;
        }

        $faq = ['@type' => 'FAQPage', 'mainEntity' => []];

        while (have_rows($source[0], $source[1])) {
            the_row();
            $q = trim(wp_strip_all_tags(get_sub_field(KELSIE_FAQ_QUESTION)));
            $a = trim(wp_strip_all_tags(wpautop(get_sub_field(KELSIE_FAQ_ANSWER))));
            if ($q && $a) {
                $faq['mainEntity'][] = [
                    '@type' => 'Question',
                    'name'  => $q,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => $a,
                    ],
                ];
            }
        }

        if (!empty($faq['mainEntity'])) {
            $data[KELSIE_SCHEMA_KEY] = $faq; // append, don’t overwrite
        }

        return $data;
    }, 20, 2);
});
