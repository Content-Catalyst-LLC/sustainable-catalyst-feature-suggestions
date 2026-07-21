<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-api-integrations.php');foreach(array('github','repository','monitoring','contact-engagement','institutional','create_external_link','external_reference','relationship_type','automatic_external_issue_creation') as $needle){if(strpos($class,$needle)===false){fwrite(STDERR,"FAIL external link: $needle
");exit(1);}}echo "v6.12.0 external relationship contract passed.
";
