<?php
require __DIR__.'/email-newlines.php';
$GLOBALS['email_meta'][42]=['_mi_activity_id'=>9];
$GLOBALS['email_meta'][9]=['_mi_group_logo_url'=>'https://example.invalid/logo.png'];
expect(MI_Modello_Email::stile_risolto(42)['logo_enabled']===true,'Automatic group logo');
foreach([['HIDE','INHERIT',false],['HIDE','SHOW',true],['SHOW','HIDE',false],['SHOW','INHERIT',true],['INHERIT','HIDE',false],['INHERIT','INHERIT',true]] as [$group,$event,$expected]){
 $GLOBALS['email_meta'][9]['_mi_email_style']=MI_Modello_Email::sanitizza_stile(MI_Modello_Email::logo_override([],$group));
 $GLOBALS['email_meta'][42]['_mi_email_style']=MI_Modello_Email::sanitizza_stile(MI_Modello_Email::logo_override([],$event));
 $result=MI_Modello_Email::stile_risolto(42);
 expect($result['logo_enabled']===$expected,'Inheritance '.$group.'/'.$event);
 expect(($result['logo_url']!=='')===$expected,'Hidden logo URL retained');
}
expect(MI_Modello_Email::logo_override([],null,['logo_enabled'=>false])===['logo_enabled'=>false],'Older form lost explicit hide');
expect(MI_Modello_Email::logo_override([],'INHERIT',['logo_enabled'=>false])===[],'Inheritance not restored');
$_POST=['mi_modello_email_nonce'=>'ok','mi_email_event_logo_mode'=>'HIDE'];
MI_Modello_Email::salva(42,(object)['ID'=>42]);
expect(get_post_meta(42,'_mi_email_style')['logo_enabled']===false,'Editor did not save explicit hide');
echo "PASS email logo: three states, group/event precedence, persistence and old forms.\n";
