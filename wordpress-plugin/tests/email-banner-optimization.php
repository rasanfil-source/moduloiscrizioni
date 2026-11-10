<?php
require __DIR__.'/php-behavior.php';
define('WEEK_IN_SECONDS',604800);
$fixture=dirname(__DIR__,2).'/.tmp/banner-email-'.bin2hex(random_bytes(5));mkdir($fixture,0777,true);
function wp_upload_dir(){return ['basedir'=>$GLOBALS['fixture'],'baseurl'=>'https://example.invalid/uploads','error'=>false];}
function attachment_url_to_postid($url){return $url==='https://example.invalid/original.jpg'?1:0;}
function get_attached_file($id){return $GLOBALS['fixture'].'/original.jpg';}
function wp_getimagesize($file){return getimagesize($file);}
function get_transient($key){return $GLOBALS['banner_cache'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['banner_cache'][$key]=$value;}
function trailingslashit($path){return rtrim($path,'/').'/';}
function wp_mkdir_p($path){return is_dir($path)||mkdir($path,0777,true);}
function wp_safe_remote_get($url,$args){$GLOBALS['remote_calls']++;return ['body'=>file_get_contents(get_attached_file(1))];}
function wp_remote_retrieve_response_code($response){return 200;}
function wp_remote_retrieve_body($response){return $response['body'];}
function wp_tempnam($name){return tempnam($GLOBALS['fixture'],'download-');}
function wp_delete_file($file){unlink($file);}
class BannerImageEditor {
 private $image,$quality=90;
 function __construct($path){$this->image=imagecreatefromjpeg($path);}
 function get_size(){return ['width'=>imagesx($this->image),'height'=>imagesy($this->image)];}
 function set_quality($quality){$this->quality=$quality;return true;}
 function resize($w,$h,$crop){$size=$this->get_size();$scale=min(1,$w/$size['width'],$h/$size['height']);$next=imagecreatetruecolor((int)round($size['width']*$scale),(int)round($size['height']*$scale));imagecopyresampled($next,$this->image,0,0,0,0,imagesx($next),imagesy($next),$size['width'],$size['height']);$this->image=$next;return true;}
 function save($path,$mime){imagejpeg($this->image,$path,$this->quality);clearstatcache(true,$path);return ['path'=>$path];}
}
function wp_get_image_editor($path){return new BannerImageEditor($path);}
$im=imagecreatetruecolor(1600,900);mt_srand(42);
for($y=0;$y<900;$y++)for($x=0;$x<1600;$x++)imagesetpixel($im,$x,$y,mt_rand(0,0xffffff));
imagejpeg($im,$fixture.'/original.jpg',95);$before=hash_file('sha256',$fixture.'/original.jpg');
$optimized=MI_Modello_Email::optimized_banner_url('https://example.invalid/original.jpg');
$file=$fixture.'/mi-email-banners/'.basename($optimized);$size=getimagesize($file);
expect(is_file($file)&&$size[0]<=960&&$size[1]<=640,'image not reduced');
expect(filesize($file)<filesize($fixture.'/original.jpg'),'image not compressed');
expect(hash_file('sha256',$fixture.'/original.jpg')===$before,'original image modified');
$remote_calls=0;$remote=MI_Modello_Email::optimized_banner_url('https://remote.example.invalid/banner.jpg');
expect(str_contains($remote,'/mi-email-banners/'),'external banner not optimized');
expect(MI_Modello_Email::optimized_banner_url('https://remote.example.invalid/banner.jpg')===$remote&&$remote_calls===1,'remote banner downloaded again');
expect(count(glob($fixture.'/download-*'))===0,'temporary download retained');
echo 'PASS: banner '.filesize($fixture.'/original.jpg').' → '.filesize($file)." bytes; original preserved; local and remote copies cached.\n";
