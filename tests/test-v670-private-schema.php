<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-workflow-automation.php');
foreach(array('scfs_help_desk_workflow_rules','scfs_help_desk_workflow_runs','scfs_help_desk_workflow_actions','scfs_help_desk_response_templates','scfs_help_desk_agent_macros','scfs_help_desk_workflow_approvals','scfs_help_desk_followups') as $table){if(strpos($class,$table)===false){fwrite(STDERR,"FAIL table: $table\n");exit(1);}}
foreach(array('source_fingerprint','integrity_hash','approval_required','scheduled_for','decision_reason','allowed_variables_json') as $field){if(strpos($class,$field)===false){fwrite(STDERR,"FAIL field: $field\n");exit(1);}}echo "v6.7.0 additive workflow schema contract passed.\n";
