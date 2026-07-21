<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-api-integrations.php');foreach(array('scfs_help_desk_integrations','scfs_help_desk_integration_credentials','scfs_help_desk_webhook_subscriptions','scfs_help_desk_webhook_deliveries','scfs_help_desk_webhook_attempts','scfs_help_desk_webhook_dead_letters','scfs_help_desk_external_links','scfs_help_desk_integration_checkpoints','scfs_help_desk_integration_audit_events') as $table){if(strpos($class,$table)===false){fwrite(STDERR,"FAIL table: $table
");exit(1);}}foreach(array('secret_reference','secret_sha256','payload_sha256','response_sha256','evidence_sha256','state_sha256') as $field){if(strpos($class,$field)===false){fwrite(STDERR,"FAIL field: $field
");exit(1);}}echo "v6.12.0 additive integration schema contract passed.
";
