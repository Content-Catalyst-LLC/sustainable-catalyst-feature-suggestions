<?php
$root=dirname(__DIR__); $class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-knowledge-assisted-resolution.php');
$tables=array('scfs_help_desk_resolution_runs','scfs_help_desk_resolution_recommendations','scfs_help_desk_resolution_actions','scfs_help_desk_resolution_promotions','scfs_help_desk_resolution_signatures');
foreach($tables as $table){if(strpos($class,$table)===false){fwrite(STDERR,"FAIL: missing $table\n");exit(1);}}
foreach(array('source_fingerprint','decision_state','customer_safe','integrity_hash','private_evidence_excluded') as $field){if(strpos($class,$field)===false){fwrite(STDERR,"FAIL: missing $field\n");exit(1);}}
echo "v6.8.0 additive schema contract passed.\n";
