<?php
$root=dirname(__DIR__);foreach(array('RELEASE_NOTES_7.0.0.md','SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V7.0.0_RELEASE_NOTES.md','docs/connected-help-desk-platform-v7.0.0.md','schemas/scfs-connected-help-desk-platform-v1.schema.json','examples/connected-help-desk-platform-v7.0.0.json','feature_suggestions_manifest-v7.0.0.json','release-manifest-v7.0.0.json','validate_v7_0_0.sh','install_and_push_v7_0_0_macos.sh') as $file){if(!is_file($root.'/'.$file)){fwrite(STDERR,"FAIL artifact: $file
");exit(1);}}$manifest=json_decode(file_get_contents($root.'/feature_suggestions_manifest-v7.0.0.json'),true);if(($manifest['version']??'')!=='7.0.0'||empty($manifest['connected_help_desk_platform']['human_command_authorization_required'])){fwrite(STDERR,"FAIL manifest
");exit(1);}echo "v7.0.0 release artifacts contract passed.
";
