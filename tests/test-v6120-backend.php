<?php
$root=dirname(__DIR__);$module=file_get_contents($root.'/backend/app/help_desk_production_hardening.py');$main=file_get_contents($root.'/backend/app/main.py');foreach(array('RateLimitEvidence','AbuseSignalEvidence','PrivacyOperationEvidence','BackupSnapshotEvidence','RecoveryDrillEvidence','SecurityHeaderEvidence','ProductionGateEvidence','verify_hardening_report') as $needle){if(strpos($module,$needle)===false){fwrite(STDERR,"FAIL backend: $needle
");exit(1);}}foreach(array('/v1/help-desk/production-hardening/capabilities','/rate-limits/evaluate','/abuse/evaluate','/privacy/evaluate','/backups/evaluate','/recovery/evaluate','/security-headers/evaluate','/production-gates/evaluate','/reports/verify') as $route){if(strpos($main,$route)===false){fwrite(STDERR,"FAIL backend route: $route
");exit(1);}}echo "v6.12.0 hardening backend contract passed.
";
