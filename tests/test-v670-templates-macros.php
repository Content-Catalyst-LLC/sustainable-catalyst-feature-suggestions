<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-workflow-automation.php');
foreach(array('render_template','allowed_variables_json','customer_safe','draft_only','automatic_send','request-more-information','internal-escalation-review','triage-review','approval_policy') as $needle){if(strpos($class,$needle)===false){fwrite(STDERR,"FAIL template/macro: $needle\n");exit(1);}}echo "v6.9.0 template and macro contract passed.\n";
