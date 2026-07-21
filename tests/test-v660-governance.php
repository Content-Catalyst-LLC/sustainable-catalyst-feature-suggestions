<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-knowledge-assisted-resolution.php');
foreach(array("'automatic_customer_send' => '0'","'automatic_duplicate_merge' => '0'","'automatic_publication' => '0'","'require_agent_approval' => '1'",'Only approved customer-safe guidance can be sent') as $needle){if(strpos($class,$needle)===false){fwrite(STDERR,"FAIL governance: $needle\n");exit(1);}}
echo "v6.11.0 agent authority contract passed.\n";
