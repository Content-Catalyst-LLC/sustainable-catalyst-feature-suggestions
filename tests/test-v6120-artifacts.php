<?php
$root=dirname(__DIR__);foreach(array('RELEASE_NOTES_6.12.0.md','SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.12.0_RELEASE_NOTES.md','docs/help-desk-production-hardening-v6.12.0.md','schemas/scfs-help-desk-production-hardening-v1.schema.json','examples/help-desk-production-hardening-v6.12.0.json','feature_suggestions_manifest-v6.12.0.json','release-manifest-v6.12.0.json','validate_v6_12_0.sh','install_and_push_v6_12_0_macos.sh') as $file){if(!is_file($root.'/'.$file)){fwrite(STDERR,"FAIL artifact: $file
");exit(1);}}$manifest=json_decode(file_get_contents($root.'/feature_suggestions_manifest-v6.12.0.json'),true);if(($manifest['version']??'')!=='6.12.0'||empty($manifest['help_desk_production_hardening']['human_release_authorization_required'])){fwrite(STDERR,"FAIL manifest
");exit(1);}echo "v6.12.0 release artifacts contract passed.
";
