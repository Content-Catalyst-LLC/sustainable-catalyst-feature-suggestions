<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-knowledge-assisted-resolution.php');
foreach(array('public_candidates','similar_case_candidates','run_resolution','decide_recommendation','request_promotion','extend_customer_payload','knowledge_resolution_completed','knowledge_recommendation_') as $needle){if(strpos($class,$needle)===false){fwrite(STDERR,"FAIL flow: $needle\n");exit(1);}}
echo "v6.11.0 resolution workflow contract passed.\n";
