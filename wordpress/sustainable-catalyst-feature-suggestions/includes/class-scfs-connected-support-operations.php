<?php
/**
 * Connected Product Support Operations Platform.
 *
 * Consolidates operational health, governed action planning, product readiness,
 * cross-product coordination, report integrity, and Contact and Engagement
 * handoff readiness without bypassing the specialist modules that remain the
 * source of truth for their records.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Connected_Support_Operations {
    const VERSION = '5.1.0';
    const SCHEMA_VERSION = '1.0';
    const OPTION_KEY = 'scfs_connected_support_operations';
    const ACTION_QUEUE_KEY = 'scfs_connected_operations_actions';
    const SNAPSHOT_KEY = 'scfs_connected_operations_snapshots';
    const CRON_HOOK = 'scfs_connected_support_operations_daily';
    const NONCE_ACTION = 'scfs_connected_support_operations';
    const NONCE_NAME = 'scfs_connected_support_nonce';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_page'), 41);
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('admin_post_scfs_connected_operations_refresh', array($this, 'refresh_action'));
        add_action('admin_post_scfs_connected_operations_queue', array($this, 'queue_action_request'));
        add_action('admin_post_scfs_connected_operations_execute', array($this, 'execute_action_request'));
        add_action('admin_post_scfs_connected_operations_export', array($this, 'export_report_action'));
        add_action('admin_post_scfs_connected_operations_save', array($this, 'save_settings_action'));
        add_action(self::CRON_HOOK, array($this, 'run_daily_snapshot'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('scfs_platform_connected_operations', array($this, 'dashboard_record'));

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('scfs operations status', array($this, 'cli_status'));
            WP_CLI::add_command('scfs operations snapshot', array($this, 'cli_snapshot'));
        }
    }

    public static function activate() {
        $instance = self::instance();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 900, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public function default_settings() {
        return array(
            'snapshot_retention_days' => 90,
            'action_retention_entries' => 150,
            'minimum_operational_score' => 75,
            'require_human_action_approval' => '1',
            'show_private_handoff_readiness' => '1',
            'include_repository_health' => '1',
            'include_cross_product_health' => '1',
        );
    }

    public function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->default_settings());
    }

    public function admin_capability() {
        return apply_filters('scfs_connected_operations_admin_capability', 'manage_options');
    }

    public function can_manage() {
        return current_user_can($this->admin_capability());
    }

    public function schema_record() {
        return array(
            'schema' => 'scfs-connected-product-support-operations/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'platform' => 'Connected Product Support Operations Platform',
            'source_of_truth' => 'wordpress',
            'modules' => array(
                'product_taxonomy',
                'product_onboarding',
                'support_content_operations',
                'repository_release_synchronization',
                'editorial_governance',
                'guided_resolution',
                'support_knowledge_base',
                'known_issues',
                'release_intelligence',
                'feature_suggestions',
                'voting_and_surveys',
                'documentation_intelligence',
                'product_reliability',
                'cross_product_orchestration',
                'contact_engagement_handoff',
            ),
            'capabilities' => array(
                'unified_operations_dashboard',
                'product_operations_dossiers',
                'module_health_inventory',
                'governed_action_queue',
                'daily_operations_snapshots',
                'support_release_gate_evidence',
                'cross_product_operational_dependencies',
                'private_handoff_readiness',
                'integrity_protected_operations_reports',
                'specialist_module_source_of_truth_preservation',
            ),
            'rest_routes' => array(
                '/connected-operations/schema',
                '/connected-operations/dashboard',
                '/connected-operations/products/{product}',
                '/connected-operations/actions',
                '/connected-operations/refresh',
                '/connected-operations/report',
            ),
            'governance' => array(
                'human_review_required' => true,
                'automatic_publication' => false,
                'automatic_incident_declaration' => false,
                'automatic_roadmap_change' => false,
                'automatic_private_case_creation' => false,
                'specialist_module_controls_retained' => true,
            ),
            'privacy' => array(
                'requester_identity_stored' => false,
                'private_case_narrative_stored' => false,
                'private_documents_stored' => false,
                'aggregate_handoff_readiness_only' => true,
            ),
        );
    }

    public function module_records() {
        $records = array(
            'product_taxonomy' => $this->module_record('Product taxonomy', class_exists('SCFS_Product_Integration'), 'Shared products, versions, components, issue types, and releases.'),
            'support_center' => $this->module_record('Public Support Center', class_exists('SCFS_Product_Support_Platform'), 'Unified public documentation, issues, releases, participation, and handoff routing.'),
            'content_operations' => $this->module_record('Content operations', class_exists('SCFS_Support_Content_Operations'), 'Onboarding, import, validation, export, recovery, and freshness operations.'),
            'editorial_governance' => $this->module_record('Editorial governance', class_exists('SCFS_Editorial_Governance'), 'Review queues, standards, approvals, scheduling, expiration, and audit history.'),
            'repository_sync' => $this->module_record('Repository synchronization', class_exists('SCFS_Repository_Release_Synchronization'), 'README, CHANGELOG, release, drift, and link-health inspection.'),
            'reliability_center' => $this->module_record('Product reliability', class_exists('SCFS_Support_Reliability_Center'), 'Resolution, documentation, issue, release, repository, and governance analytics.'),
            'cross_product' => $this->module_record('Cross-product orchestration', class_exists('SCFS_Cross_Product_Support_Orchestration'), 'Platform incidents, dependencies, shared components, and related-product journeys.'),
            'documentation_intelligence' => $this->module_record('Documentation intelligence', class_exists('SCFS_Documentation_Feature_Intelligence'), 'Feedback, failed searches, documentation gaps, and support-demand evidence.'),
            'guided_resolution' => $this->module_record('Guided Resolution', class_exists('SCFS_Guided_Resolution'), 'Product-aware support search and consent-gated unresolved-case continuation.'),
            'private_handoff' => $this->module_record('Contact and Engagement handoff', class_exists('SCFS_Product_Integration'), 'Typed private-support continuation without importing private case content.'),
        );

        if (class_exists('SCFS_Support_Content_Operations')) {
            $records['content_operations']['health'] = SCFS_Support_Content_Operations::instance()->cron_health_record();
        }
        if (class_exists('SCFS_Editorial_Governance')) {
            $records['editorial_governance']['health'] = SCFS_Editorial_Governance::instance()->cron_health();
            $records['editorial_governance']['summary'] = SCFS_Editorial_Governance::instance()->governance_summary();
        }
        if (class_exists('SCFS_Support_Reliability_Center')) {
            $records['reliability_center']['health'] = SCFS_Support_Reliability_Center::instance()->cron_health();
        }
        if (class_exists('SCFS_Cross_Product_Support_Orchestration')) {
            $records['cross_product']['overview'] = SCFS_Cross_Product_Support_Orchestration::instance()->overview_record('');
        }

        return $records;
    }

    private function module_record($label, $ready, $detail) {
        return array(
            'label' => $label,
            'ready' => (bool) $ready,
            'state' => $ready ? 'ready' : 'unavailable',
            'detail' => $detail,
        );
    }

    public function product_records() {
        if (!function_exists('get_terms') || !taxonomy_exists(SCFS_Product_Integration::PRODUCT_TAXONOMY)) {
            return array();
        }
        $products = get_terms(array(
            'taxonomy' => SCFS_Product_Integration::PRODUCT_TAXONOMY,
            'hide_empty' => false,
        ));
        if (is_wp_error($products)) {
            return array();
        }
        $records = array();
        foreach ($products as $product) {
            $records[] = $this->product_record($product);
        }
        usort($records, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        return $records;
    }

    public function product_record($product) {
        $product_id = is_object($product) ? absint($product->term_id) : absint($product);
        $term = is_object($product) ? $product : get_term($product_id, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$term || is_wp_error($term)) {
            return array();
        }
        $slug = sanitize_title($term->slug);
        $record = array(
            'product_id' => $product_id,
            'slug' => $slug,
            'name' => $term->name,
            'readiness' => array(),
            'reliability' => array(),
            'repository' => array(),
            'cross_product' => array(),
            'blockers' => array(),
            'actions' => array(),
        );
        if (class_exists('SCFS_Support_Content_Operations')) {
            $record['readiness'] = SCFS_Support_Content_Operations::instance()->readiness_record($term);
        }
        if (class_exists('SCFS_Support_Reliability_Center')) {
            $record['reliability'] = SCFS_Support_Reliability_Center::instance()->product_record($term);
        }
        if (class_exists('SCFS_Repository_Release_Synchronization') && !empty($this->settings()['include_repository_health'])) {
            $mapping = SCFS_Repository_Release_Synchronization::instance()->mapping($product_id);
            $record['repository'] = array(
                'configured' => !empty($mapping['repository_url']),
                'repository_url' => esc_url_raw($mapping['repository_url'] ?? ''),
                'default_branch' => sanitize_text_field($mapping['default_branch'] ?? 'main'),
                'scheduled_sync' => !empty($mapping['enable_scheduled_sync']),
                'stored_evidence_only' => true,
            );
        }
        if (class_exists('SCFS_Cross_Product_Support_Orchestration') && !empty($this->settings()['include_cross_product_health'])) {
            $record['cross_product'] = SCFS_Cross_Product_Support_Orchestration::instance()->overview_record($slug);
        }
        $readiness_score = absint($record['readiness']['score'] ?? 0);
        $reliability_score = absint($record['reliability']['score'] ?? 0);
        $record['score'] = $this->weighted_score($readiness_score, $reliability_score);
        if ($readiness_score < 60) {
            $record['blockers'][] = 'Support-content readiness needs attention.';
            $record['actions'][] = 'Review onboarding and starter documentation.';
        }
        if ($reliability_score < 60) {
            $record['blockers'][] = 'Product reliability evidence is below the operational threshold.';
            $record['actions'][] = 'Review unresolved searches, issues, release readiness, and documentation feedback.';
        }
        if (empty($record['repository']['configured']) && !empty($this->settings()['include_repository_health'])) {
            $record['actions'][] = 'Configure the product repository mapping.';
        }
        $record['state'] = $record['score'] >= absint($this->settings()['minimum_operational_score']) ? 'operational' : ($record['score'] >= 50 ? 'attention' : 'not_ready');
        return $record;
    }

    private function weighted_score($readiness, $reliability) {
        if ($readiness === 0 && $reliability === 0) {
            return 0;
        }
        if ($readiness === 0) {
            return $reliability;
        }
        if ($reliability === 0) {
            return $readiness;
        }
        return (int) round(($readiness * 0.45) + ($reliability * 0.55));
    }

    public function action_types() {
        return array(
            'validate_content' => 'Validate support content',
            'refresh_reliability' => 'Refresh reliability snapshots',
            'run_editorial_governance' => 'Run editorial governance',
            'inspect_repositories' => 'Inspect configured repositories',
            'refresh_documentation_gaps' => 'Refresh documentation gaps',
        );
    }

    public function action_queue() {
        return array_values((array) get_option(self::ACTION_QUEUE_KEY, array()));
    }

    public function enqueue_action($type, $product_id = 0, $context = array()) {
        $types = $this->action_types();
        $type = sanitize_key($type);
        if (!isset($types[$type])) {
            return new WP_Error('scfs_invalid_operations_action', __('Unsupported operations action.', 'sustainable-catalyst-feature-suggestions'));
        }
        $queue = $this->action_queue();
        $record = array(
            'id' => wp_generate_uuid4(),
            'type' => $type,
            'label' => $types[$type],
            'product_id' => absint($product_id),
            'status' => 'queued',
            'created_at' => gmdate('c'),
            'created_by' => get_current_user_id(),
            'executed_at' => '',
            'result' => array(),
            'context' => is_array($context) ? $context : array(),
            'human_approval_required' => true,
        );
        $queue[] = $record;
        $limit = max(25, min(500, absint($this->settings()['action_retention_entries'])));
        if (count($queue) > $limit) {
            $queue = array_slice($queue, -$limit);
        }
        update_option(self::ACTION_QUEUE_KEY, $queue, false);
        return $record;
    }

    public function execute_action($action_id) {
        $queue = $this->action_queue();
        $found = false;
        foreach ($queue as &$action) {
            if (($action['id'] ?? '') !== $action_id) {
                continue;
            }
            $found = true;
            if (($action['status'] ?? '') !== 'queued') {
                return new WP_Error('scfs_operations_action_already_processed', __('This action has already been processed.', 'sustainable-catalyst-feature-suggestions'));
            }
            $result = $this->dispatch_action($action['type'], absint($action['product_id'] ?? 0));
            $action['executed_at'] = gmdate('c');
            $action['executed_by'] = get_current_user_id();
            if (is_wp_error($result)) {
                $action['status'] = 'failed';
                $action['result'] = array('error' => $result->get_error_message());
            } else {
                $action['status'] = 'completed';
                $action['result'] = $result;
            }
            break;
        }
        unset($action);
        if (!$found) {
            return new WP_Error('scfs_operations_action_not_found', __('Operations action was not found.', 'sustainable-catalyst-feature-suggestions'));
        }
        update_option(self::ACTION_QUEUE_KEY, $queue, false);
        return $queue;
    }

    private function dispatch_action($type, $product_id) {
        switch ($type) {
            case 'validate_content':
                if (!class_exists('SCFS_Support_Content_Operations')) {
                    return new WP_Error('scfs_content_operations_unavailable', 'Content Operations is unavailable.');
                }
                return SCFS_Support_Content_Operations::instance()->validate_content($product_id);
            case 'refresh_reliability':
                if (!class_exists('SCFS_Support_Reliability_Center')) {
                    return new WP_Error('scfs_reliability_unavailable', 'Reliability Center is unavailable.');
                }
                return SCFS_Support_Reliability_Center::instance()->run_daily_snapshot();
            case 'run_editorial_governance':
                if (!class_exists('SCFS_Editorial_Governance')) {
                    return new WP_Error('scfs_governance_unavailable', 'Editorial Governance is unavailable.');
                }
                return SCFS_Editorial_Governance::instance()->run_scheduled_governance();
            case 'inspect_repositories':
                if (!class_exists('SCFS_Repository_Release_Synchronization')) {
                    return new WP_Error('scfs_repository_sync_unavailable', 'Repository Synchronization is unavailable.');
                }
                return SCFS_Repository_Release_Synchronization::instance()->run_scheduled_sync();
            case 'refresh_documentation_gaps':
                if (!class_exists('SCFS_Documentation_Feature_Intelligence')) {
                    return new WP_Error('scfs_documentation_intelligence_unavailable', 'Documentation Intelligence is unavailable.');
                }
                return SCFS_Documentation_Feature_Intelligence::instance()->refresh_documentation_gaps();
        }
        return new WP_Error('scfs_unknown_operations_action', 'Unknown operations action.');
    }

    public function dashboard_record() {
        $modules = $this->module_records();
        $ready = 0;
        foreach ($modules as $module) {
            if (!empty($module['ready'])) {
                $ready++;
            }
        }
        $products = $this->product_records();
        $operational = 0;
        foreach ($products as $product) {
            if (($product['state'] ?? '') === 'operational') {
                $operational++;
            }
        }
        return array(
            'schema' => 'scfs-connected-product-support-operations/' . self::SCHEMA_VERSION,
            'version' => self::VERSION,
            'generated_at' => gmdate('c'),
            'modules' => $modules,
            'module_readiness' => array('ready' => $ready, 'total' => count($modules)),
            'products' => $products,
            'product_readiness' => array('operational' => $operational, 'total' => count($products)),
            'actions' => $this->action_queue(),
            'cron' => $this->cron_health(),
            'governance' => $this->schema_record()['governance'],
            'privacy' => $this->schema_record()['privacy'],
        );
    }

    public function cron_health() {
        $next = wp_next_scheduled(self::CRON_HOOK);
        $last = get_option(self::SNAPSHOT_KEY . '_last_run', '');
        return array(
            'hook' => self::CRON_HOOK,
            'scheduled' => (bool) $next,
            'next_run' => $next ? gmdate('c', $next) : '',
            'last_run' => $last,
        );
    }

    public function run_daily_snapshot() {
        $record = $this->dashboard_record();
        $snapshots = (array) get_option(self::SNAPSHOT_KEY, array());
        $snapshots[gmdate('Y-m-d')] = array(
            'generated_at' => $record['generated_at'],
            'module_readiness' => $record['module_readiness'],
            'product_readiness' => $record['product_readiness'],
            'products' => array_map(function ($product) {
                return array(
                    'slug' => $product['slug'] ?? '',
                    'score' => absint($product['score'] ?? 0),
                    'state' => $product['state'] ?? 'unknown',
                    'blocker_count' => count($product['blockers'] ?? array()),
                );
            }, $record['products']),
        );
        $cutoff = time() - (DAY_IN_SECONDS * max(7, absint($this->settings()['snapshot_retention_days'])));
        foreach ($snapshots as $date => $snapshot) {
            if (strtotime($date . ' 00:00:00 UTC') < $cutoff) {
                unset($snapshots[$date]);
            }
        }
        update_option(self::SNAPSHOT_KEY, $snapshots, false);
        update_option(self::SNAPSHOT_KEY . '_last_run', gmdate('c'), false);
        return $record;
    }

    public function report_payload() {
        $payload = $this->dashboard_record();
        $payload['snapshots'] = (array) get_option(self::SNAPSHOT_KEY, array());
        $canonical = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payload['integrity'] = array(
            'algorithm' => 'sha256',
            'checksum' => hash('sha256', $canonical),
        );
        return $payload;
    }

    public function register_admin_page() {
        $parent = 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE;
        add_submenu_page(
            $parent,
            __('Connected Support Operations', 'sustainable-catalyst-feature-suggestions'),
            __('Connected Operations', 'sustainable-catalyst-feature-suggestions'),
            $this->admin_capability(),
            'scfs-connected-support-operations',
            array($this, 'render_admin_page')
        );
    }

    public function admin_assets($hook) {
        if (strpos((string) $hook, 'scfs-connected-support-operations') === false) {
            return;
        }
        wp_enqueue_style(
            'scfs-connected-support-operations',
            plugins_url('../assets/connected-support-operations.css', __FILE__),
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'scfs-connected-support-operations',
            plugins_url('../assets/connected-support-operations.js', __FILE__),
            array(),
            self::VERSION,
            true
        );
    }

    public function render_admin_page() {
        if (!$this->can_manage()) {
            wp_die(__('You do not have permission to manage connected support operations.', 'sustainable-catalyst-feature-suggestions'));
        }
        $dashboard = $this->dashboard_record();
        $refresh = wp_nonce_url(admin_url('admin-post.php?action=scfs_connected_operations_refresh'), self::NONCE_ACTION);
        $export = wp_nonce_url(admin_url('admin-post.php?action=scfs_connected_operations_export'), self::NONCE_ACTION);
        ?>
        <div class="wrap scfs-connected-operations">
            <h1><?php esc_html_e('Connected Product Support Operations Platform', 'sustainable-catalyst-feature-suggestions'); ?></h1>
            <p><?php esc_html_e('Coordinate operational evidence across public support, documentation, repositories, governance, reliability, cross-product incidents, and private-support handoffs. Specialist modules remain the source of truth.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url($refresh); ?>"><?php esc_html_e('Refresh operations snapshot', 'sustainable-catalyst-feature-suggestions'); ?></a> <a class="button" href="<?php echo esc_url($export); ?>"><?php esc_html_e('Export integrity-protected report', 'sustainable-catalyst-feature-suggestions'); ?></a></p>
            <div class="scfs-connected-operations__metrics">
                <article><span><?php esc_html_e('Modules ready', 'sustainable-catalyst-feature-suggestions'); ?></span><strong><?php echo esc_html($dashboard['module_readiness']['ready'] . '/' . $dashboard['module_readiness']['total']); ?></strong></article>
                <article><span><?php esc_html_e('Products operational', 'sustainable-catalyst-feature-suggestions'); ?></span><strong><?php echo esc_html($dashboard['product_readiness']['operational'] . '/' . $dashboard['product_readiness']['total']); ?></strong></article>
                <article><span><?php esc_html_e('Queued actions', 'sustainable-catalyst-feature-suggestions'); ?></span><strong><?php echo esc_html((string) count(array_filter($dashboard['actions'], function ($action) { return ($action['status'] ?? '') === 'queued'; }))); ?></strong></article>
            </div>
            <h2><?php esc_html_e('Connected modules', 'sustainable-catalyst-feature-suggestions'); ?></h2>
            <div class="scfs-connected-operations__modules">
                <?php foreach ($dashboard['modules'] as $module) : ?>
                    <article class="is-<?php echo esc_attr($module['state']); ?>"><h3><?php echo esc_html($module['label']); ?></h3><p><?php echo esc_html($module['detail']); ?></p><strong><?php echo esc_html($module['ready'] ? __('Ready', 'sustainable-catalyst-feature-suggestions') : __('Unavailable', 'sustainable-catalyst-feature-suggestions')); ?></strong></article>
                <?php endforeach; ?>
            </div>
            <h2><?php esc_html_e('Product operations dossiers', 'sustainable-catalyst-feature-suggestions'); ?></h2>
            <div class="scfs-connected-operations__table-wrap" tabindex="0">
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Product', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('Score', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('State', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('Blockers', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('Recommended actions', 'sustainable-catalyst-feature-suggestions'); ?></th></tr></thead><tbody>
                <?php if (!$dashboard['products']) : ?><tr><td colspan="5"><?php esc_html_e('No product taxonomy records are available yet.', 'sustainable-catalyst-feature-suggestions'); ?></td></tr><?php endif; ?>
                <?php foreach ($dashboard['products'] as $product) : ?><tr><td><strong><?php echo esc_html($product['name']); ?></strong><br><code><?php echo esc_html($product['slug']); ?></code></td><td><?php echo esc_html((string) $product['score']); ?></td><td><?php echo esc_html($product['state']); ?></td><td><?php echo esc_html(implode(' ', $product['blockers'])); ?></td><td><?php echo esc_html(implode(' ', $product['actions'])); ?></td></tr><?php endforeach; ?>
                </tbody></table>
            </div>
            <h2><?php esc_html_e('Governed action queue', 'sustainable-catalyst-feature-suggestions'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scfs-connected-operations__action-form">
                <input type="hidden" name="action" value="scfs_connected_operations_queue">
                <?php wp_nonce_field(self::NONCE_ACTION); ?>
                <label><?php esc_html_e('Action', 'sustainable-catalyst-feature-suggestions'); ?><select name="operation_type"><?php foreach ($this->action_types() as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label><?php esc_html_e('Product term ID (optional)', 'sustainable-catalyst-feature-suggestions'); ?><input type="number" min="0" name="product_id" value="0"></label>
                <button class="button button-primary"><?php esc_html_e('Queue for human execution', 'sustainable-catalyst-feature-suggestions'); ?></button>
            </form>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Created', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('Action', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('Status', 'sustainable-catalyst-feature-suggestions'); ?></th><th><?php esc_html_e('Control', 'sustainable-catalyst-feature-suggestions'); ?></th></tr></thead><tbody>
            <?php if (!$dashboard['actions']) : ?><tr><td colspan="4"><?php esc_html_e('No connected-operations actions have been queued.', 'sustainable-catalyst-feature-suggestions'); ?></td></tr><?php endif; ?>
            <?php foreach (array_reverse($dashboard['actions']) as $action) : ?><tr><td><?php echo esc_html($action['created_at'] ?? ''); ?></td><td><?php echo esc_html($action['label'] ?? $action['type']); ?></td><td><?php echo esc_html($action['status'] ?? ''); ?></td><td><?php if (($action['status'] ?? '') === 'queued') : ?><a class="button" data-scfs-confirm="<?php esc_attr_e('Execute this governed operation now?', 'sustainable-catalyst-feature-suggestions'); ?>" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_connected_operations_execute&action_id=' . rawurlencode($action['id'])), self::NONCE_ACTION)); ?>"><?php esc_html_e('Execute', 'sustainable-catalyst-feature-suggestions'); ?></a><?php else : ?><code><?php echo esc_html(wp_json_encode($action['result'] ?? array())); ?></code><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    public function refresh_action() {
        $this->assert_admin_request();
        $this->run_daily_snapshot();
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-connected-support-operations&refreshed=1'));
        exit;
    }

    public function queue_action_request() {
        $this->assert_admin_request();
        $record = $this->enqueue_action($_POST['operation_type'] ?? '', absint($_POST['product_id'] ?? 0));
        $arg = is_wp_error($record) ? 'queue_error' : 'queued';
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-connected-support-operations&' . $arg . '=1'));
        exit;
    }

    public function execute_action_request() {
        $this->assert_admin_request();
        $this->execute_action(sanitize_text_field($_GET['action_id'] ?? ''));
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-connected-support-operations&executed=1'));
        exit;
    }

    public function save_settings_action() {
        $this->assert_admin_request();
        $settings = array(
            'snapshot_retention_days' => min(730, max(7, absint($_POST['snapshot_retention_days'] ?? 90))),
            'action_retention_entries' => min(500, max(25, absint($_POST['action_retention_entries'] ?? 150))),
            'minimum_operational_score' => min(100, max(0, absint($_POST['minimum_operational_score'] ?? 75))),
            'require_human_action_approval' => '1',
            'show_private_handoff_readiness' => isset($_POST['show_private_handoff_readiness']) ? '1' : '',
            'include_repository_health' => isset($_POST['include_repository_health']) ? '1' : '',
            'include_cross_product_health' => isset($_POST['include_cross_product_health']) ? '1' : '',
        );
        update_option(self::OPTION_KEY, $settings, false);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '&page=scfs-connected-support-operations&updated=1'));
        exit;
    }

    public function export_report_action() {
        $this->assert_admin_request();
        $payload = $this->report_payload();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=scfs-connected-support-operations-' . gmdate('Y-m-d') . '.json');
        header('X-SCFS-Content-SHA256: ' . $payload['integrity']['checksum']);
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function assert_admin_request() {
        if (!$this->can_manage()) {
            wp_die(__('Forbidden', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::NONCE_ACTION);
    }

    public function register_rest_routes() {
        $namespace = Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE;
        register_rest_route($namespace, '/connected-operations/schema', array('methods' => 'GET', 'callback' => array($this, 'rest_schema'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/connected-operations/dashboard', array('methods' => 'GET', 'callback' => array($this, 'rest_dashboard'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/connected-operations/products/(?P<product>[a-z0-9-]+)', array('methods' => 'GET', 'callback' => array($this, 'rest_product'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/connected-operations/actions', array('methods' => 'GET', 'callback' => array($this, 'rest_actions'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/connected-operations/refresh', array('methods' => 'POST', 'callback' => array($this, 'rest_refresh'), 'permission_callback' => array($this, 'rest_permission')));
        register_rest_route($namespace, '/connected-operations/report', array('methods' => 'GET', 'callback' => array($this, 'rest_report'), 'permission_callback' => array($this, 'rest_permission')));
    }

    public function rest_permission() {
        return current_user_can($this->admin_capability());
    }

    public function rest_schema() { return rest_ensure_response($this->schema_record()); }
    public function rest_dashboard() { return rest_ensure_response($this->dashboard_record()); }
    public function rest_actions() { return rest_ensure_response(array('ok' => true, 'version' => self::VERSION, 'actions' => $this->action_queue())); }
    public function rest_refresh() { return rest_ensure_response($this->run_daily_snapshot()); }
    public function rest_report() { return rest_ensure_response($this->report_payload()); }

    public function rest_product($request) {
        $slug = sanitize_title($request['product']);
        $term = get_term_by('slug', $slug, SCFS_Product_Integration::PRODUCT_TAXONOMY);
        if (!$term) {
            return new WP_Error('scfs_product_not_found', __('Product not found.', 'sustainable-catalyst-feature-suggestions'), array('status' => 404));
        }
        return rest_ensure_response($this->product_record($term));
    }

    public function cli_status() {
        WP_CLI::log(wp_json_encode($this->dashboard_record(), JSON_PRETTY_PRINT));
    }

    public function cli_snapshot() {
        $record = $this->run_daily_snapshot();
        WP_CLI::success('Connected support operations snapshot stored for ' . ($record['generated_at'] ?? gmdate('c')) . '.');
    }
}
