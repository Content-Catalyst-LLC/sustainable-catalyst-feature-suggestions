<?php
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$kb = SCFS_Knowledge_Base_Foundation::instance();
$query = new WP_Query(array(
    'post_type' => SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE,
    'post_status' => 'publish',
    'posts_per_page' => 30,
    'orderby' => 'modified',
    'order' => 'DESC',
));
?>
<main id="primary" class="site-main scfs-kb-archive-page">
    <div class="scfs-kb-archive-shell">
        <section class="scfs-kb">
            <header class="scfs-kb-hero">
                <p class="scfs-kb-eyebrow"><?php esc_html_e('Product Support and Feedback', 'sustainable-catalyst-feature-suggestions'); ?></p>
                <h1><?php esc_html_e('Known Issues', 'sustainable-catalyst-feature-suggestions'); ?></h1>
                <p><?php esc_html_e('Verified product problems, current status, safe workarounds, and release relationships.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            </header>
            <p><a class="scfs-kb-back" href="<?php echo esc_url(get_post_type_archive_link(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE)); ?>">← <?php esc_html_e('Back to Support Knowledge Base', 'sustainable-catalyst-feature-suggestions'); ?></a></p>
            <div class="scfs-kb-issue-archive">
                <?php if (!$query->have_posts()) : ?>
                    <div class="scfs-kb-empty"><h2><?php esc_html_e('No published known issues', 'sustainable-catalyst-feature-suggestions'); ?></h2></div>
                <?php else : foreach ($query->posts as $issue) :
                    $status = get_post_meta($issue->ID, '_scfs_issue_status', true) ?: 'investigating';
                    $severity = get_post_meta($issue->ID, '_scfs_issue_severity', true) ?: 'moderate';
                    $symptom = get_post_meta($issue->ID, '_scfs_issue_symptom', true);
                    ?>
                    <article class="scfs-kb-issue-card scfs-kb-issue-card-full">
                        <div class="scfs-kb-badge-row">
                            <span class="scfs-kb-badge scfs-kb-status-<?php echo esc_attr($status); ?>"><?php echo esc_html(isset($kb->issue_statuses()[$status]) ? $kb->issue_statuses()[$status] : ucwords(str_replace('_', ' ', $status))); ?></span>
                            <span class="scfs-kb-badge scfs-kb-severity-<?php echo esc_attr($severity); ?>"><?php echo esc_html(isset($kb->issue_severities()[$severity]) ? $kb->issue_severities()[$severity] : ucwords(str_replace('_', ' ', $severity))); ?></span>
                        </div>
                        <h2><a href="<?php echo esc_url(get_permalink($issue)); ?>"><?php echo esc_html(get_the_title($issue)); ?></a></h2>
                        <?php if ($symptom) : ?><p><?php echo esc_html(wp_trim_words($symptom, 42)); ?></p><?php endif; ?>
                        <a class="scfs-kb-read" href="<?php echo esc_url(get_permalink($issue)); ?>"><?php esc_html_e('View issue details', 'sustainable-catalyst-feature-suggestions'); ?> →</a>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>
    </div>
</main>
<?php get_footer(); ?>
