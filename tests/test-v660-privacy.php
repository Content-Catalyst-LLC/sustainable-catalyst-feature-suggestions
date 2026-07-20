<?php
$root=dirname(__DIR__); $class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-knowledge-assisted-resolution.php');
$checks=array('no private message persistence'=>strpos($class,"'private_message_content_persisted' => false")!==false,'no requester identity persistence'=>strpos($class,"'requester_identity_persisted' => false")!==false,'agent approved customer payload'=>strpos($class,"decision_state IN ('approved','sent')")!==false,'similar case identities excluded'=>strpos($class,"'requester_ref_included' => false")!==false,'email promotion guard'=>strpos($class,'scfs_private_identity_detected')!==false);
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: $label\n");exit(1);}}
echo "v6.8.0 privacy boundary contract passed.\n";
