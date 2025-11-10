<?php
if (!defined('ABSPATH')) exit;

// If ACF is missing, show a friendly placeholder in editor; nothing on front.
if (!function_exists('get_field')) {
    if (!empty($is_preview)) {
        echo '<div class="kelsie-faq-list__empty"><em>ACF is inactive. Activate ACF to display FAQs.</em></div>';
    }
    return;
}

$block_id    = 'faq-list-' . ($block['id'] ?? uniqid());
$anchor      = !empty($block['anchor']) ? $block['anchor'] : $block_id;
$class_name  = 'kelsie-faq-list';
if (!empty($block['className'])) $class_name .= ' ' . $block['className'];
if (!empty($block['align']))     $class_name .= ' align' . $block['align'];

$current_post_id = get_the_ID();
$rows = get_field('faq_acf_repeater', $current_post_id);
$source = 'post';
if (empty($rows)) {
    $rows = get_field('faq_acf_repeater', 'option');
    $source = 'option';
}

if ($is_preview && !empty($rows)) {
    echo '<div style="font:12px/1.4 system-ui;opacity:.75;margin-bottom:.5rem;">Rendering FAQs from <strong>' .
         esc_html($source === 'post' ? 'this post' : 'Options Page') .
         '</strong>.</div>';
}
?>
<section id="<?php echo esc_attr($anchor); ?>" class="<?php echo esc_attr($class_name); ?>">
<?php if (!empty($rows)) : ?>
    <div class="kelsie-faq-list__items" role="list">
        <?php $i = 0; foreach ($rows as $row): $i++;
            $q = isset($row['faq_question']) ? wp_strip_all_tags($row['faq_question']) : '';
            $a_html = wpautop($row['faq_answer'] ?? '');
            $cats = !empty($row['faq_category']) && is_array($row['faq_category'])
                ? array_map('sanitize_text_field', $row['faq_category'])
                : [];
            $panel_id = esc_attr($anchor . '-item-' . $i);
            $summary_id = esc_attr($panel_id . '-summary');
        ?>
        <details class="kelsie-faq-list__item" id="<?php echo $panel_id; ?>" role="listitem">
            <summary id="<?php echo $summary_id; ?>" class="kelsie-faq-list__question">
                <?php echo esc_html($q ?: 'Untitled question'); ?>
            </summary>
            <div class="kelsie-faq-list__answer">
                <?php echo $a_html ?: '<p>(No answer yet.)</p>'; ?>
                <?php if ($cats): ?>
                    <div class="kelsie-faq-list__meta">
                        <span class="kelsie-faq-list__label">Category:</span>
                        <?php foreach ($cats as $c): ?>
                            <span class="kelsie-faq-list__chip"><?php echo esc_html($c); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php if (!empty($is_preview)) : ?>
        <div class="kelsie-faq-list__empty"><em>No FAQs found. Add rows on this post or in the Options Page.</em></div>
    <?php endif; ?>
<?php endif; ?>
</section>
