<?php
$root=dirname(__DIR__);$class=file_get_contents($root.'/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-production-hardening.php');foreach(array('/help-desk/production-hardening/schema','/overview','/rate-limits/evaluate','/security-events','/privacy-requests','/audit-exports','/backups','/recovery-drills','/production-gates/evaluate','/health') as $route){if(strpos($class,$route)===false){fwrite(STDERR,"FAIL route: $route
");exit(1);}}foreach(array('scfs help-desk hardening status','scfs help-desk hardening security-events','scfs help-desk hardening privacy-requests','scfs help-desk hardening backups','scfs help-desk hardening recovery-drills','scfs help-desk hardening production-gates','scfs help-desk hardening snapshot') as $command){if(strpos($class,$command)===false){fwrite(STDERR,"FAIL CLI: $command
");exit(1);}}echo "v6.12.0 hardening API and CLI contract passed.
";
