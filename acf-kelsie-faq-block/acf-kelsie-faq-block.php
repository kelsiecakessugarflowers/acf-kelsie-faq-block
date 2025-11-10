<?php
/**
 * Plugin Name: Kelsie ACF FAQ Block
 * Description: ACF block for FAQ repeater with optional Rank Math schema.
 * Version:     1.0.1
 * Author:      Kelsie Cakes
 */

if (!defined('ABSPATH')) exit;

add_action('admin_init', function () {
    // Show notice only to admins if ACF is missing.
    if (!class_exists('ACF') && current_user_can('activate_plugins')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Kelsie ACF FAQ Block:</strong> Advanced Custom Fields (ACF) is inactive or missing. The block will render a basic placeholder until ACF is active.</p></div>';
        });
    }
});

add_action('init', function () {
    // Register styles used by block.json
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

    // Always register the block type (safe even without ACF; our renderer will guard).
    register_block_type(__DIR__ . '/blocks/kelsie-faq');
});

// Optional ACF Options Page (only if ACF exists)
add_action('acf/init', function () {
    if (function_exists('acf_add_options_page')) {
        if (!acf_get_options_pages()) {
            acf_add_options_page([
                'page_title' => 'ACF FAQ Repeater Group',
                'menu_title' => 'ACF FAQ Repeater Group',
                'menu_slug'  => 'acf-faq-repeater-group',
                'capability' => 'manage_options',
                'redirect'   => false,
                'position'   => 59,
            ]);
        }
    }
});

/**
 * Rank Math schema integration — add only if Rank Math is active.
 * (Adding the filter without Rank Math is harmless, but this avoids extra work.)
 */
add_action('plugins_loaded', function () {
    if (!defined('RANK_MATH_VERSION')) {
        return;
    }

    add_filter('rank_math/json_ld', function ($data, $jsonld) {
        if (!is_singular()) return $data;

        global $post;
        if (!$post || !function_exists('has_block') || !has_block('kelsiecakes/faq-list', $post)) {
            return $data;
        }

        // Require ACF to build schema
        if (!function_exists('have_rows')) {
            return $data;
        }

        $source = null;
        if (have_rows('faq_acf_repeater', $post->ID)) {
            $source = ['faq_acf_repeater', $post->ID];
        } elseif (have_rows('faq_acf_repeater', 'option')) {
            $source = ['faq_acf_repeater', 'option'];
        } else {
            return $data;
        }

        $faq = ['@type' => 'FAQPage', 'mainEntity' => []];

        while (have_rows($source[0], $source[1])) {
            the_row();
            $q = trim(wp_strip_all_tags(get_sub_field('faq_question')));
            $a = trim(wp_strip_all_tags(wpautop(get_sub_field('faq_answer'))));
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
            $data['kelsie_faq'] = $faq;
        }

        return $data;
    }, 20, 2);
});
