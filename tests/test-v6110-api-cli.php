<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-api-integrations.php');foreach(array('/help-desk/integrations/schema','/help-desk/integrations','/credentials','/subscriptions','/events/dispatch','/deliveries','/retry','/dead-letters','/external-links','/health') as $route){if(strpos($class,$route)===false){fwrite(STDERR,"FAIL route: $route
");exit(1);}}foreach(array('scfs help-desk integrations status','scfs help-desk integrations list','scfs help-desk integrations deliveries','scfs help-desk integrations retry','scfs help-desk integrations dispatch','scfs help-desk integrations dead-letters') as $command){if(strpos($class,$command)===false){fwrite(STDERR,"FAIL CLI: $command
");exit(1);}}echo "v6.12.0 integration API and CLI contract passed.
";
