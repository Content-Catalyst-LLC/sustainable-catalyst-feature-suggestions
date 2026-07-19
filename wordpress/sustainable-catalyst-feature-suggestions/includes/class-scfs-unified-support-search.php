<?php
/**
 * Unified Support Search and Guided Resolution.
 *
 * Connects Support Discovery with Guided Resolution so one public query can
 * return current known issues, publication-grade Support Articles, release
 * context, public improvement records, and a consent-gated private handoff.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Unified_Support_Search {
    const VERSION = '5.4.0';
    const SCHEMA = 'scfs-unified-support-search/1.0';
    const JOURNEY_SCHEMA = 'scfs-support-resolution-journey/1.0';
    const SHORTCODE = 'scfs_unified_support_search';
    const LEGACY_SHORTCODE = 'scfs_unified_guided_resolution';

    private static $instance = null;
    private static $render_count = 0;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_shortcodes'), 32);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_shortcodes() {
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_shortcode(self::LEGACY_SHORTCODE, array($this, 'render_shortcode'));
    }

    public function register_assets() {
        $style_path = dirname(__DIR__) . '/assets/unified-support-search.css';
        $script_path = dirname(__DIR__) . '/assets/unified-support-search.js';
        $style_version = is_readable($style_path) ? (string) filemtime($style_path) : self::VERSION;
        $script_version = is_readable($script_path) ? (string) filemtime($script_path) : self::VERSION;

        wp_register_style(
            'scfs-unified-support-search',
            plugins_url('../assets/unified-support-search.css', __FILE__),
            array('scfs-guided-resolution', 'scfs-knowledge-base'),
            $style_version
        );
        wp_register_script(
            'scfs-unified-support-search',
            plugins_url('../assets/unified-support-search.js', __FILE__),
            array(),
            $script_version,
            true
        );
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'journey_schema' => self::JOURNEY_SCHEMA,
            'version' => self::VERSION,
            'workflow' => array(
                'describe_need',
                'check_current_known_issues',
                'follow_verified_guidance',
                'review_release_context',
                'consider_related_improvements',
                'continue_to_private_support',
            ),
            'result_groups' => array('known_issues', 'support_articles', 'releases', 'public_suggestions'),
            'ranking_layers' => array('guided_resolution', 'support_discovery', 'taxonomy_context', 'editorial_priority'),
            'shortcodes' => array(self::SHORTCODE, self::LEGACY_SHORTCODE, SCFS_Guided_Resolution::SHORTCODE),
            'legacy_compatibility' => array(
                'guided_resolution_shortcode_preserved' => true,
                'guided_resolution_rest_routes_preserved' => true,
                'guided_resolution_query_parameters_preserved' => true,
            ),
            'privacy' => array(
                'public_content_only' => true,
                'ip_address_stored' => false,
                'private_case_stored_in_plugin' => false,
                'handoff_requires_consent' => true,
                'automatic_case_creation' => false,
            ),
            'human_review_required' => true,
        );
    }

    private function request_context($source = 'shortcode') {
        $query = isset($_REQUEST['scfs_support_query'])
            ? sanitize_text_field(wp_unslash($_REQUEST['scfs_support_query']))
            : (isset($_REQUEST['scfs_resolution_query']) ? sanitize_text_field(wp_unslash($_REQUEST['scfs_resolution_query'])) : '');
        $error_message = isset($_REQUEST['scfs_support_error'])
            ? sanitize_textarea_field(wp_unslash($_REQUEST['scfs_support_error']))
            : (isset($_REQUEST['scfs_resolution_error']) ? sanitize_textarea_field(wp_unslash($_REQUEST['scfs_resolution_error'])) : '');
        $product = isset($_REQUEST['scfs_support_product'])
            ? sanitize_title(wp_unslash($_REQUEST['scfs_support_product']))
            : (isset($_REQUEST['scfs_resolution_product']) ? sanitize_title(wp_unslash($_REQUEST['scfs_resolution_product'])) : '');
        $product_version = isset($_REQUEST['scfs_support_version'])
            ? sanitize_title(wp_unslash($_REQUEST['scfs_support_version']))
            : (isset($_REQUEST['scfs_resolution_version']) ? sanitize_title(wp_unslash($_REQUEST['scfs_resolution_version'])) : '');
        $component = isset($_REQUEST['scfs_support_component'])
            ? sanitize_title(wp_unslash($_REQUEST['scfs_support_component']))
            : (isset($_REQUEST['scfs_resolution_component']) ? sanitize_title(wp_unslash($_REQUEST['scfs_resolution_component'])) : '');

        return array(
            'query' => $query,
            'error_message' => $error_message,
            'product' => $product,
            'product_version' => $product_version,
            'component' => $component,
            'source' => sanitize_key($source),
        );
    }

    private function normalize_context($context) {
        $context = wp_parse_args((array) $context, array(
            'query' => '',
            'error_message' => '',
            'product' => '',
            'product_version' => '',
            'component' => '',
            'source' => 'rest',
        ));
        return array(
            'query' => sanitize_text_field($context['query']),
            'error_message' => sanitize_textarea_field($context['error_message']),
            'product' => sanitize_title($context['product']),
            'product_version' => sanitize_title($context['product_version']),
            'component' => sanitize_title($context['component']),
            'source' => sanitize_key($context['source']),
        );
    }

    /**
     * Merge the v5.2.9 publication-discovery score into guided-resolution
     * Support Article results. The legacy Guided Resolution result structure is
     * retained and merely enriched with transparent discovery signals.
     */
    private function enrich_support_articles($groups, $context) {
        if (empty($groups['support_articles']) || !class_exists('SCFS_Support_Discovery')) {
            return $groups;
        }
        $discovery = SCFS_Support_Discovery::instance();
        $query = trim($context['query'] . ' ' . $context['error_message']);
        foreach ($groups['support_articles'] as &$item) {
            $post = !empty($item['id']) ? get_post(absint($item['id'])) : null;
            if (!($post instanceof WP_Post)) {
                continue;
            }
            $match = $discovery->score_article($post, $query);
            $item['guided_resolution_score'] = (float) $item['score'];
            $item['discovery_score'] = (int) $match['score'];
            $item['discovery_reasons'] = (array) $match['reasons'];
            $item['matched_tokens'] = (array) $match['matched_tokens'];
            $item['score'] = round((float) $item['score'] + ((float) $match['score'] * 0.35), 2);
            $item['match_reasons'] = array_values(array_unique(array_merge(
                (array) $item['match_reasons'],
                array_map(static function($reason) {
                    return 'Discovery: ' . ucfirst((string) $reason);
                }, (array) $match['reasons'])
            )));
        }
        unset($item);
        usort($groups['support_articles'], array(SCFS_Guided_Resolution::instance(), 'sort_results'));
        return $groups;
    }

    /**
     * Enrich Known Issue and Release result groups with the operational
     * relationships introduced in v5.4.0. Existing result keys and ranking
     * remain intact for backward compatibility.
     */
    private function enrich_operational_intelligence($groups) {
        if (!class_exists('SCFS_Known_Issue_Release_Intelligence')) {
            return $groups;
        }
        $intelligence = SCFS_Known_Issue_Release_Intelligence::instance();
        foreach ((array) ($groups['known_issues'] ?? array()) as &$item) {
            $record = !empty($item['id']) ? $intelligence->issue_record(absint($item['id']), true) : array();
            if (!$record) continue;
            $item['affected_versions'] = wp_list_pluck($record['affected_versions'], 'name');
            $item['target_releases'] = wp_list_pluck($record['target_releases'], 'title');
            $item['fixed_releases'] = wp_list_pluck($record['fixed_releases'], 'title');
            $item['related_support_articles'] = wp_list_pluck($record['related_articles'], 'title');
            $item['operational_health'] = $record['health'];
            if ($record['fixed_releases']) {
                $item['match_reasons'][] = __('Fixed-release evidence is available', 'sustainable-catalyst-feature-suggestions');
            } elseif ($record['target_releases']) {
                $item['match_reasons'][] = __('A target release is linked', 'sustainable-catalyst-feature-suggestions');
            }
        }
        unset($item);
        foreach ((array) ($groups['releases'] ?? array()) as &$item) {
            $record = !empty($item['id']) ? $intelligence->release_record(absint($item['id']), true) : array();
            if (!$record) continue;
            $item['open_issue_count'] = count($record['open_issues']);
            $item['resolved_issue_count'] = count($record['resolved_issues']);
            $item['verification_state'] = $record['verification_state'];
            $item['last_verified_at'] = $record['last_verified_at'];
            $item['changelog_url'] = $record['changelog_url'];
            $item['operational_health'] = $record['health'];
        }
        unset($item);
        return $groups;
    }

    private function top_result($groups) {
        $all = array();
        foreach (array('known_issues', 'support_articles', 'releases', 'public_suggestions') as $group) {
            foreach ((array) ($groups[$group] ?? array()) as $item) {
                $all[] = $item;
            }
        }
        usort($all, array(SCFS_Guided_Resolution::instance(), 'sort_results'));
        return $all ? $all[0] : array();
    }

    private function build_journey($resolution) {
        $groups = $resolution['groups'];
        $state = $resolution['resolution_state'];
        $steps = array(
            array(
                'key' => 'known_issues',
                'label' => __('Check current known issues', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Confirm whether the symptom is already tracked and whether a workaround or fix is available.', 'sustainable-catalyst-feature-suggestions'),
                'count' => count((array) $groups['known_issues']),
            ),
            array(
                'key' => 'support_articles',
                'label' => __('Follow verified guidance', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Use the highest-confidence publication-grade Support Article and verify the expected result.', 'sustainable-catalyst-feature-suggestions'),
                'count' => count((array) $groups['support_articles']),
            ),
            array(
                'key' => 'releases',
                'label' => __('Review release context', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Check supported versions, changes, limitations, and release-specific recovery guidance.', 'sustainable-catalyst-feature-suggestions'),
                'count' => count((array) $groups['releases']),
            ),
            array(
                'key' => 'public_suggestions',
                'label' => __('Consider related improvements', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Review public ideas only after current issues and available guidance have been considered.', 'sustainable-catalyst-feature-suggestions'),
                'count' => count((array) $groups['public_suggestions']),
            ),
            array(
                'key' => 'private_support',
                'label' => __('Continue to private support when unresolved', 'sustainable-catalyst-feature-suggestions'),
                'description' => __('Transfer only the public search context through the existing consent-gated handoff.', 'sustainable-catalyst-feature-suggestions'),
                'count' => 0,
            ),
        );
        $first_available = null;
        foreach ($steps as $index => &$step) {
            if ($step['key'] === 'private_support') {
                $step['status'] = in_array($state, array('no_match', 'low_confidence'), true) ? 'recommended' : 'available';
                continue;
            }
            if ($step['count'] > 0 && $first_available === null) {
                $first_available = $index;
                $step['status'] = 'start_here';
            } elseif ($step['count'] > 0) {
                $step['status'] = 'available';
            } else {
                $step['status'] = 'not_found';
            }
        }
        unset($step);
        return array(
            'schema' => self::JOURNEY_SCHEMA,
            'state' => $state,
            'steps' => $steps,
            'start_step' => $first_available === null ? 'private_support' : $steps[$first_available]['key'],
            'human_review_required' => true,
        );
    }

    private function build_summary($resolution) {
        $top = $this->top_result($resolution['groups']);
        $state = $resolution['resolution_state'];
        if (!$top) {
            return array(
                'headline' => __('No matching public guidance was found', 'sustainable-catalyst-feature-suggestions'),
                'message' => __('Try a shorter symptom or remove a filter. Private support remains available through the consent-gated handoff.', 'sustainable-catalyst-feature-suggestions'),
                'top_result' => array(),
            );
        }
        $kind_labels = array(
            'known_issue' => __('current Known Issue', 'sustainable-catalyst-feature-suggestions'),
            'support_article' => __('Support Article', 'sustainable-catalyst-feature-suggestions'),
            'release' => __('release record', 'sustainable-catalyst-feature-suggestions'),
            'public_suggestion' => __('public improvement idea', 'sustainable-catalyst-feature-suggestions'),
        );
        $kind = isset($kind_labels[$top['kind']]) ? $kind_labels[$top['kind']] : __('support record', 'sustainable-catalyst-feature-suggestions');
        $headline = $state === 'strong_match'
            ? sprintf(__('Start with this %s', 'sustainable-catalyst-feature-suggestions'), $kind)
            : sprintf(__('Review this possible %s match', 'sustainable-catalyst-feature-suggestions'), $kind);
        return array(
            'headline' => $headline,
            'message' => wp_trim_words(wp_strip_all_tags((string) $top['summary']), 34),
            'top_result' => $top,
        );
    }

    public function search($context, $track = true) {
        $context = $this->normalize_context($context);
        $resolution = SCFS_Guided_Resolution::instance()->search($context, $track);
        $resolution['groups'] = $this->enrich_support_articles($resolution['groups'], $context);
        $resolution['groups'] = $this->enrich_operational_intelligence($resolution['groups']);

        $all = array();
        foreach ($resolution['groups'] as $items) {
            $all = array_merge($all, (array) $items);
        }
        usort($all, array(SCFS_Guided_Resolution::instance(), 'sort_results'));
        $resolution['result_count'] = count($all);
        $resolution['top_score'] = $all ? (float) $all[0]['score'] : 0.0;
        $resolution['confidence'] = min(0.99, round($resolution['top_score'] / 190, 4));
        if (!$all) {
            $resolution['resolution_state'] = 'no_match';
        } elseif ($resolution['confidence'] >= 0.72) {
            $resolution['resolution_state'] = 'strong_match';
        } elseif ($resolution['confidence'] >= 0.42) {
            $resolution['resolution_state'] = 'possible_match';
        } else {
            $resolution['resolution_state'] = 'low_confidence';
        }

        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'query' => $context['query'],
            'error_message_present' => $context['error_message'] !== '',
            'product_context' => $resolution['product_context'],
            'result_count' => $resolution['result_count'],
            'top_score' => $resolution['top_score'],
            'confidence' => $resolution['confidence'],
            'resolution_state' => $resolution['resolution_state'],
            'groups' => $resolution['groups'],
            'summary' => $this->build_summary($resolution),
            'journey' => $this->build_journey($resolution),
            'search_id' => $resolution['search_id'],
            'legacy_guided_resolution_schema' => $resolution['schema'],
            'personal_data_stored' => false,
            'human_review_required' => true,
        );
    }

    private function render_term_select($name, $taxonomy, $placeholder, $selected) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'));
        if (is_wp_error($terms)) {
            $terms = array();
        }
        echo '<label><span>' . esc_html($placeholder) . '</span><select name="' . esc_attr($name) . '"><option value="">' . esc_html($placeholder) . '</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }
        echo '</select></label>';
    }

    private function reset_url() {
        return remove_query_arg(array(
            'scfs_support_query', 'scfs_support_error', 'scfs_support_product', 'scfs_support_version', 'scfs_support_component',
            'scfs_resolution_query', 'scfs_resolution_error', 'scfs_resolution_product', 'scfs_resolution_version', 'scfs_resolution_component',
        ));
    }

    private function render_search_form($context) {
        echo '<form class="scfs-unified-search__form" method="get" role="search" data-scfs-unified-search-form>';
        if (isset($_GET['scfs_support_view'])) {
            echo '<input type="hidden" name="scfs_support_view" value="' . esc_attr(sanitize_key(wp_unslash($_GET['scfs_support_view']))) . '">';
        }
        echo '<label class="scfs-unified-search__query"><span>' . esc_html__('What are you trying to do or fix?', 'sustainable-catalyst-feature-suggestions') . '</span><input type="search" name="scfs_support_query" value="' . esc_attr($context['query']) . '" placeholder="Example: Decision Studio export fails after upgrading" autocomplete="off"></label>';
        echo '<label class="scfs-unified-search__error"><span>' . esc_html__('Exact error or visible symptom', 'sustainable-catalyst-feature-suggestions') . '</span><textarea name="scfs_support_error" rows="3" placeholder="Paste only a short, non-sensitive fragment. Remove keys, passwords, personal information, and private logs.">' . esc_textarea($context['error_message']) . '</textarea></label>';
        echo '<div class="scfs-unified-search__filters">';
        $this->render_term_select('scfs_support_product', SCFS_Product_Integration::PRODUCT_TAXONOMY, __('All products', 'sustainable-catalyst-feature-suggestions'), $context['product']);
        $this->render_term_select('scfs_support_version', SCFS_Product_Integration::VERSION_TAXONOMY, __('All versions', 'sustainable-catalyst-feature-suggestions'), $context['product_version']);
        $this->render_term_select('scfs_support_component', SCFS_Product_Integration::COMPONENT_TAXONOMY, __('All components', 'sustainable-catalyst-feature-suggestions'), $context['component']);
        echo '</div><div class="scfs-unified-search__actions"><button type="submit">' . esc_html__('Search and build a resolution path', 'sustainable-catalyst-feature-suggestions') . '</button><a href="' . esc_url($this->reset_url()) . '">' . esc_html__('Start over', 'sustainable-catalyst-feature-suggestions') . '</a></div>';
        echo '<p class="scfs-unified-search__privacy">' . esc_html__('Search only public support records. Do not enter secrets, personal data, confidential correspondence, or private logs.', 'sustainable-catalyst-feature-suggestions') . '</p>';
        echo '</form>';
    }

    private function render_summary($result) {
        $summary = $result['summary'];
        echo '<section class="scfs-unified-search__summary scfs-unified-search__summary--' . esc_attr($result['resolution_state']) . '" aria-live="polite">';
        echo '<div><p class="scfs-unified-search__label">' . esc_html__('Recommended starting point', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html($summary['headline']) . '</h3><p>' . esc_html($summary['message']) . '</p></div>';
        echo '<dl><div><dt>' . esc_html__('Confidence', 'sustainable-catalyst-feature-suggestions') . '</dt><dd>' . esc_html(number_format_i18n($result['confidence'] * 100, 0) . '%') . '</dd></div><div><dt>' . esc_html__('Records', 'sustainable-catalyst-feature-suggestions') . '</dt><dd>' . esc_html((string) $result['result_count']) . '</dd></div></dl>';
        echo '</section>';
    }

    private function render_journey($result) {
        echo '<section class="scfs-unified-search__journey" aria-labelledby="scfs-unified-journey-title"><header><p class="scfs-unified-search__label">' . esc_html__('Resolution path', 'sustainable-catalyst-feature-suggestions') . '</p><h3 id="scfs-unified-journey-title">' . esc_html__('Work through the evidence in this order', 'sustainable-catalyst-feature-suggestions') . '</h3></header><ol>';
        foreach ($result['journey']['steps'] as $index => $step) {
            echo '<li class="scfs-unified-search__journey-step scfs-unified-search__journey-step--' . esc_attr($step['status']) . '"><span class="scfs-unified-search__step-number">' . esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) . '</span><div><div class="scfs-unified-search__step-title"><h4>' . esc_html($step['label']) . '</h4>';
            if ($step['key'] !== 'private_support') {
                echo '<span>' . esc_html(sprintf(_n('%d record', '%d records', $step['count'], 'sustainable-catalyst-feature-suggestions'), $step['count'])) . '</span>';
            } else {
                echo '<span>' . esc_html(ucwords(str_replace('_', ' ', $step['status']))) . '</span>';
            }
            echo '</div><p>' . esc_html($step['description']) . '</p></div></li>';
        }
        echo '</ol></section>';
    }

    private function result_group_labels() {
        return array(
            'known_issues' => __('Current Known Issues', 'sustainable-catalyst-feature-suggestions'),
            'support_articles' => __('Verified Support Articles', 'sustainable-catalyst-feature-suggestions'),
            'releases' => __('Release Context', 'sustainable-catalyst-feature-suggestions'),
            'public_suggestions' => __('Related Public Improvements', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    private function render_result_card($item) {
        echo '<article class="scfs-unified-search__result scfs-unified-search__result--' . esc_attr($item['kind']) . '"><div class="scfs-unified-search__result-meta"><span>' . esc_html(ucwords(str_replace('_', ' ', $item['kind']))) . '</span><strong>' . esc_html(number_format_i18n((float) $item['score'], 0)) . '</strong></div><h4>';
        if (!empty($item['url'])) {
            echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a>';
        } else {
            echo esc_html($item['title']);
        }
        echo '</h4><p>' . esc_html(wp_trim_words(wp_strip_all_tags((string) $item['summary']), 34)) . '</p>';
        if (!empty($item['status']) || !empty($item['severity'])) {
            echo '<p class="scfs-unified-search__status">' . esc_html(implode(' · ', array_filter(array(
                !empty($item['status']) ? ucwords(str_replace('_', ' ', $item['status'])) : '',
                !empty($item['severity']) ? ucwords($item['severity']) : '',
            )))) . '</p>';
        }
        if ($item['kind'] === 'known_issue' && (!empty($item['affected_versions']) || !empty($item['fixed_releases']) || !empty($item['target_releases']))) {
            $details = array();
            if (!empty($item['affected_versions'])) $details[] = sprintf(__('Affected: %s', 'sustainable-catalyst-feature-suggestions'), implode(', ', (array) $item['affected_versions']));
            if (!empty($item['fixed_releases'])) $details[] = sprintf(__('Fixed in: %s', 'sustainable-catalyst-feature-suggestions'), implode(', ', (array) $item['fixed_releases']));
            elseif (!empty($item['target_releases'])) $details[] = sprintf(__('Target: %s', 'sustainable-catalyst-feature-suggestions'), implode(', ', (array) $item['target_releases']));
            echo '<p class="scfs-unified-search__operational-context">' . esc_html(implode(' · ', $details)) . '</p>';
        }
        if ($item['kind'] === 'release' && (isset($item['open_issue_count']) || isset($item['resolved_issue_count']))) {
            echo '<p class="scfs-unified-search__operational-context">' . esc_html(sprintf(__('Open issues: %1$d · Resolved: %2$d', 'sustainable-catalyst-feature-suggestions'), absint($item['open_issue_count'] ?? 0), absint($item['resolved_issue_count'] ?? 0))) . '</p>';
        }
        if (!empty($item['match_reasons'])) {
            echo '<ul class="scfs-unified-search__reasons">';
            foreach (array_slice((array) $item['match_reasons'], 0, 4) as $reason) {
                echo '<li>' . esc_html($reason) . '</li>';
            }
            echo '</ul>';
        }
        echo '</article>';
    }

    private function render_results($result) {
        echo '<div class="scfs-unified-search__groups">';
        foreach ($this->result_group_labels() as $key => $label) {
            $items = (array) $result['groups'][$key];
            if (!$items) {
                continue;
            }
            echo '<section class="scfs-unified-search__group" data-scfs-result-group="' . esc_attr($key) . '"><header><h3>' . esc_html($label) . '</h3><span>' . esc_html((string) count($items)) . '</span></header><div class="scfs-unified-search__result-grid">';
            foreach ($items as $item) {
                $this->render_result_card($item);
            }
            echo '</div></section>';
        }
        echo '</div>';
    }

    private function render_handoff($result) {
        echo '<aside class="scfs-unified-search__handoff"><div><p class="scfs-unified-search__label">' . esc_html__('Still unresolved?', 'sustainable-catalyst-feature-suggestions') . '</p><h3>' . esc_html__('Continue through the existing private support boundary', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Only this public search context and the records you viewed can be transferred. Contact details and private materials are collected by the separate Contact and Engagement Platform.', 'sustainable-catalyst-feature-suggestions') . '</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="scfs_guided_resolution_handoff">';
        wp_nonce_field(SCFS_Guided_Resolution::NONCE_ACTION, 'scfs_resolution_nonce');
        echo '<input type="hidden" name="search_id" value="' . esc_attr((string) $result['search_id']) . '"><input type="hidden" name="query" value="' . esc_attr($result['query']) . '">';
        foreach ($result['product_context'] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        echo '<label><span>' . esc_html__('What remains unresolved?', 'sustainable-catalyst-feature-suggestions') . '</span><textarea name="unresolved_reason" rows="3"></textarea></label><label class="scfs-unified-search__consent"><input required type="checkbox" name="consent" value="1"> <span>' . esc_html__('I consent to transfer this non-sensitive support-search context to the private Contact and Engagement workflow.', 'sustainable-catalyst-feature-suggestions') . '</span></label><button type="submit">' . esc_html__('Continue to private support', 'sustainable-catalyst-feature-suggestions') . '</button></form></aside>';
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => __('Search product support', 'sustainable-catalyst-feature-suggestions'),
            'intro' => __('Describe a task, symptom, or exact error fragment once. The platform will search current issues, verified guidance, releases, and related public improvements, then organize the evidence into a resolution path.', 'sustainable-catalyst-feature-suggestions'),
            'compact' => '0',
            'show_handoff' => '1',
        ), $atts, self::SHORTCODE);
        wp_enqueue_style('scfs-unified-support-search');
        wp_enqueue_script('scfs-unified-support-search');

        $context = $this->request_context('shortcode');
        $has_query = $context['query'] !== '' || $context['error_message'] !== '';
        $result = $has_query ? $this->search($context, true) : null;
        self::$render_count++;
        $title_id = 'scfs-unified-support-search-title-' . self::$render_count;

        ob_start();
        echo '<section class="scfs-unified-search' . ($atts['compact'] === '1' ? ' scfs-unified-search--compact' : '') . '" aria-labelledby="' . esc_attr($title_id) . '" data-scfs-unified-search>';
        echo '<header class="scfs-unified-search__hero"><p class="scfs-unified-search__eyebrow">' . esc_html__('Unified support search', 'sustainable-catalyst-feature-suggestions') . '</p><h2 id="' . esc_attr($title_id) . '">' . esc_html($atts['title']) . '</h2><p>' . esc_html($atts['intro']) . '</p></header>';
        $this->render_search_form($context);
        if ($result) {
            $this->render_summary($result);
            $this->render_journey($result);
            $this->render_results($result);
            if ($atts['show_handoff'] === '1') {
                $this->render_handoff($result);
            }
        } else {
            echo '<div class="scfs-unified-search__start"><div><span>01</span><h3>' . esc_html__('Describe the need', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Use plain language or a short error fragment.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div><span>02</span><h3>' . esc_html__('Review the evidence', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Known issues appear before general documentation.', 'sustainable-catalyst-feature-suggestions') . '</p></div><div><span>03</span><h3>' . esc_html__('Follow the path', 'sustainable-catalyst-feature-suggestions') . '</h3><p>' . esc_html__('Verify the result before moving to the next step.', 'sustainable-catalyst-feature-suggestions') . '</p></div></div>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/unified-support/schema', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_schema'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/unified-support/search', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'rest_search'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route($namespace, '/unified-support/journey', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'rest_search'),
            'permission_callback' => '__return_true',
        ));
    }

    public function rest_schema() {
        return rest_ensure_response($this->schema_record());
    }

    public function rest_search($request) {
        $context = array(
            'query' => sanitize_text_field((string) $request->get_param('query')),
            'error_message' => sanitize_textarea_field((string) $request->get_param('error_message')),
            'product' => sanitize_title((string) $request->get_param('product')),
            'product_version' => sanitize_title((string) $request->get_param('product_version')),
            'component' => sanitize_title((string) $request->get_param('component')),
            'source' => 'rest',
        );
        if ($context['query'] === '' && $context['error_message'] === '') {
            return new WP_Error('scfs_unified_support_query_required', __('A query or error message is required.', 'sustainable-catalyst-feature-suggestions'), array('status' => 400));
        }
        return rest_ensure_response($this->search($context, true));
    }
}
