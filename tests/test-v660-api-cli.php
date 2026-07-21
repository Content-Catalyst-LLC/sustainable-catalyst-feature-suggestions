<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-knowledge-assisted-resolution.php');
foreach(array('/help-desk/knowledge-resolution/schema','/help-desk/knowledge-resolution/case/(?P<id>','/run','/decision','/promotion','/health','scfs help-desk resolution status','scfs help-desk resolution run','scfs help-desk resolution case','scfs help-desk resolution refresh-index') as $needle){if(strpos($class,$needle)===false){fwrite(STDERR,"FAIL API/CLI: $needle\n");exit(1);}}
echo "v6.9.0 REST and CLI contract passed.\n";
