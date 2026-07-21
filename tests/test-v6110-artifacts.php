<?php
$root=dirname(__DIR__);foreach(array('RELEASE_NOTES_6.11.0.md','SUSTAINABLE_CATALYST_PRODUCT_SUPPORT_AND_FEEDBACK_PLATFORM_V6.11.0_RELEASE_NOTES.md','docs/help-desk-api-integrations-v6.11.0.md','schemas/scfs-help-desk-api-integrations-v1.schema.json','examples/help-desk-api-integrations-v6.11.0.json','feature_suggestions_manifest-v6.11.0.json','release-manifest-v6.11.0.json','validate_v6_11_0.sh','install_and_push_v6_11_0_macos.sh') as $file){if(!is_file($root.'/'.$file)){fwrite(STDERR,"FAIL artifact: $file
");exit(1);}}echo "v6.11.0 release artifact contract passed.
";
