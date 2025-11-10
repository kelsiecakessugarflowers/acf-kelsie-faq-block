<?php
/**
 * Plugin Name: Kelsie ACF FAQ Block
 * Description: ACF block that renders the “FAQ ACF Repeater” field group (post-level first, then Options Page fallback).
 * Version:     1.0.0
 * Author:      Kelsie Cakes
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    // Register styles referred to by block.json "style" and "editorStyle"
    $style_path = plugin_dir_path(__FILE__) . 'assets/style.css';
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

    // Register the block via its block.json folder.
    register_block_type(__DIR__ . '/blocks/kelsie-faq');
});

/**
 * Optional: Ensure an Options Page exists for global FAQs if you aren’t using ACF UI to add it.
 * (Safe to keep; no effect if you’ve already created one with ACF UI.)
 */
add_action('acf/init', function () {
    if (function_exists('acf_add_options_page')) {
        if (!acf_get_options_pages()) {
            acf_add_options_page([
                'page_title' => 'ACF FAQ Repeater Group',
                'menu_title' => 'ACF FAQ Repeater Group',
                'menu_slug'  => 'acf-faq-repeater-group',
                'capability' => 'manage_options',
                'redirect'   => false,
                'position'   => 59
            ]);
        }
    }
});



// === Rank Math: build FAQPage schema from your ACF repeater ===
// Docs: https://rankmath.com/kb/automate-faq-schema-with-acf-repeater-fields/
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    // Only on singular content to avoid polluting archives, etc.
    if (!is_singular()) {
        return $data;
    }

    // Only add schema if the block is actually present on the page.
    // (Prevents accidental site-wide FAQ schema when global options have rows.)
    global $post;
    if (! $post || ! function_exists('has_block') || ! has_block('kelsiecakes/faq-list', $post)) {
        return $data;
    }

    // Prefer per-post rows; fall back to Options Page.
    $rows = function_exists('have_rows') && have_rows('faq_acf_repeater', $post->ID)
        ? 'post'
        : ( function_exists('have_rows') && have_rows('faq_acf_repeater', 'option') ? 'option' : null );

    if (!$rows) {
        return $data;
    }

    // Build the FAQPage node and append to Rank Math's graph.
    $faq = [
        '@type'       => 'FAQPage',
        'mainEntity'  => [],
    ];

    if ($rows === 'post') {
        while (have_rows('faq_acf_repeater', $post->ID)) {
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
    } else {
        while (have_rows('faq_acf_repeater', 'option')) {
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
    }

    if (!empty($faq['mainEntity'])) {
        // Use a custom key so we don't overwrite another plugin key.
        $data['kelsie_faq'] = $faq;
    }

    return $data;
}, 20, 2);
