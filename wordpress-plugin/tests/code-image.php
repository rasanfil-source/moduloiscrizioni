<?php
define('ABSPATH', __DIR__);
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-code-image.php';
function check_code($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([str_repeat('A',106),str_repeat('é',53)] as $payload) check_code(str_contains(MI_Code_Image::svg('QR',$payload),'<svg'),'Valid QR boundary rejected');
foreach([str_repeat('A',107),str_repeat('é',54)] as $payload){
 try{MI_Code_Image::svg('QR',$payload);throw new RuntimeException('Oversized QR silently accepted');}catch(LengthException $expected){}
}
class WP_Error {public $code;function __construct($code,$message){$this->code=$code;}}
function is_email($value){return true;}
class MI_Modello_Email {const EMAIL_SEGRETERIA='test@example.invalid';static function ripara_istantanea_codifica($value){return $value;}}
class MI_Workspace_Client {static function request(...$args){throw new RuntimeException('Invalid QR was sent to Google');}}
require __DIR__.'/../modulo-iscrizioni/includes/class-mi-spedizione-email.php';
$send=new ReflectionMethod(MI_Spedizione_Email::class,'invia_istantanea');
$result=$send->invoke(null,'test@example.invalid',['attivo'=>true,'oggetto'=>'Test','identificativo'=>['modalita'=>'QR','payload_qr'=>str_repeat('X',107)]],'test',false);
check_code($result instanceof WP_Error && $result->code==='mi_email_qr_too_long','QR error not handled by email workflow');
echo "QR: limiti in byte e mancato invio di identificativi troncati verificati.\n";
