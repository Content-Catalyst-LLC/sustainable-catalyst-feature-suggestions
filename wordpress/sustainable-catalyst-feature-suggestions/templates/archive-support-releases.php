<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<main id="primary" class="site-main scfs-kb-archive-page">
    <div class="scfs-kb-archive-shell">
        <?php echo do_shortcode('[scfs_product_support_center default_view="releases"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</main>
<?php get_footer(); ?>
