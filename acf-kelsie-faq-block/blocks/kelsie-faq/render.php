{\rtf1\ansi\ansicpg1252\cocoartf2822
\cocoatextscaling0\cocoaplatform0{\fonttbl\f0\fswiss\fcharset0 Helvetica;}
{\colortbl;\red255\green255\blue255;}
{\*\expandedcolortbl;;}
\paperw5760\paperh8640\margl1440\margr1440\vieww11520\viewh8400\viewkind0
\pard\tx720\tx1440\tx2160\tx2880\tx3600\tx4320\tx5040\tx5760\tx6480\tx7200\tx7920\tx8640\pardirnatural\partightenfactor0

\f0\fs24 \cf0 <?php\
/**\
 * Render template for: kelsiecakes/faq-list\
 *\
 * Uses ACF repeater: faq_acf_repeater (subfields: faq_question, faq_answer, faq_category).\
 * Priority: current post > options page ('option').\
 *\
 * @var array $block\
 * @var string $content\
 * @var bool $is_preview\
 * @var int|string $post_id\
 */\
\
if (!defined('ABSPATH')) exit;\
\
// Build wrapper attributes\
$block_id    = 'faq-list-' . ($block['id'] ?? uniqid());\
$anchor      = !empty($block['anchor']) ? $block['anchor'] : $block_id;\
$class_name  = 'kelsie-faq-list';\
if (!empty($block['className'])) $class_name .= ' ' . $block['className'];\
if (!empty($block['align']))     $class_name .= ' align' . $block['align'];\
\
// Determine source: current post first, then option\
$current_post_id = get_the_ID();\
$rows = get_field('faq_acf_repeater', $current_post_id);\
\
$source = 'post';\
if (empty($rows)) \{\
    $rows = get_field('faq_acf_repeater', 'option');\
    $source = 'option';\
\}\
\
// Preview hint (editor only)\
if ($is_preview && !empty($rows)) : ?>\
    <div style="font:12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; opacity:.75; margin-bottom:.5rem;">\
        Rendering FAQs from <strong><?php echo esc_html($source === 'post' ? 'this post' : 'Options Page'); ?></strong>.\
    </div>\
<?php endif; ?>\
\
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">\
<?php\
if (!empty($rows)) :\
\
    // Collect structured data (FAQPage)\
    $faq_ld = [\
        '@context' => 'https://schema.org',\
        '@type'    => 'FAQPage',\
        'mainEntity' => []\
    ];\
\
    // Ensure unique IDs for <details>/<summary> a11y\
    $i = 0; ?>\
\
    <div class="kelsie-faq-list__items" role="list">\
        <?php foreach ($rows as $row): \
            $i++;\
            $q = isset($row['faq_question']) ? wp_strip_all_tags($row['faq_question']) : '';\
            $a_raw = isset($row['faq_answer']) ? $row['faq_answer'] : '';\
            $a_html = wpautop($a_raw);\
            $a_text = trim(wp_strip_all_tags($a_html));\
\
            $cats = [];\
            if (!empty($row['faq_category']) && is_array($row['faq_category'])) \{\
                $cats = array_map('sanitize_text_field', $row['faq_category']);\
            \}\
\
            $panel_id = esc_attr($anchor . '-item-' . $i);\
            $summary_id = esc_attr($panel_id . '-summary');\
\
            // Add to JSON-LD\
            if ($q && $a_text) \{\
                $faq_ld['mainEntity'][] = [\
                    '@type' => 'Question',\
                    'name'  => $q,\
                    'acceptedAnswer' => [\
                        '@type' => 'Answer',\
                        'text'  => $a_text\
                    ]\
                ];\
            \}\
            ?>\
            <details class="kelsie-faq-list__item" id="<?php echo $panel_id; ?>" role="listitem">\
                <summary id="<?php echo $summary_id; ?>" class="kelsie-faq-list__question">\
                    <?php echo esc_html($q ?: 'Untitled question'); ?>\
                </summary>\
                <div class="kelsie-faq-list__answer">\
                    <?php echo $a_html ?: '<p>(No answer yet.)</p>'; ?>\
                    <?php if ($cats): ?>\
                        <div class="kelsie-faq-list__meta">\
                            <span class="kelsie-faq-list__label">Category:</span>\
                            <?php foreach ($cats as $c): ?>\
                                <span class="kelsie-faq-list__chip"><?php echo esc_html($c); ?></span>\
                            <?php endforeach; ?>\
                        </div>\
                    <?php endif; ?>\
                </div>\
            </details>\
        <?php endforeach; ?>\
    </div>\
\
   \
\
<?php else: ?>\
    <div class="kelsie-faq-list__empty">\
        <?php if ($is_preview): ?>\
            <em>No FAQs found. Add rows to the \'93FAQ ACF Repeater\'94 on this post or in the Options Page.</em>\
        <?php endif; ?>\
    </div>\
<?php endif; ?>\
</section>\
}