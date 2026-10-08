<?php
/** Explicit non-generation network check. Never print credentials or raw provider responses. */
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
$http=Symfony\Component\HttpClient\HttpClient::create();
$tls=['cafile'=>getenv('GIGACHAT_CA_FILE'),'verify_peer'=>true,'verify_host'=>true,'max_redirects'=>0,'timeout'=>15,'max_duration'=>20];
$step='oauth';
try {
 $response=$http->request('POST','https://ngw.devices.sberbank.ru:9443/api/v2/oauth',$tls+['headers'=>['Authorization'=>'Basic '.getenv('GIGACHAT_AUTH_KEY'),'RqUID'=>'a09836d5-ef91-40a7-89b5-6c9438b68bb8','Accept'=>'application/json'],'body'=>['scope'=>getenv('GIGACHAT_SCOPE')]]);
 $status=$response->getStatusCode();echo 'oauth HTTP '.$status."\n";if($status!==200)exit(1);
 $body=$response->toArray(false);$token=$body['access_token']??null;
 if(!is_string($token)||$token==='')throw new RuntimeException('Invalid OAuth token shape');
 $step='models';$response=$http->request('GET','https://api.giga.chat/v1/models',$tls+['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']]);
 $status=$response->getStatusCode();echo 'models HTTP '.$status."\n";if($status!==200)exit(1);
 $models=$response->toArray(false)['data']??[];
 $available=in_array(getenv('GIGACHAT_MODEL'),array_column($models,'id'),true);
 echo 'Configured model available: '.($available?'yes':'no')."\n";
 if(!$available){foreach($models as $model){$id=$model['id']??'';if(preg_match('/^GigaChat[-A-Za-z0-9.:]{0,50}$/D',$id))echo 'Available model: '.$id."\n";}}
 if(in_array('--chat',$argv,true)){
  $step='chat';$response=$http->request('POST','https://api.giga.chat/v1/chat/completions',$tls+['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json'],'json'=>['model'=>getenv('GIGACHAT_MODEL'),'messages'=>[['role'=>'user','content'=>'Ответь одним словом: готово']],'stream'=>false,'max_tokens'=>16]]);
  $status=$response->getStatusCode();echo 'Short chat HTTP '.$status."\n";if($status!==200)exit(1);$chat=$response->toArray(false);echo 'Short chat finish reason: '.(($chat['choices'][0]['finish_reason']??null)==='stop'?'stop':'other')."\n";
 }
 exit($available?0:1);
} catch(Throwable $error) {
 $category='unknown';
 foreach(['certificate'=>'TLS certificate','resolve'=>'DNS','timed out'=>'timeout','timeout'=>'timeout','connect'=>'connection','JSON'=>'invalid JSON','option'=>'HTTP client option'] as $pattern=>$label)if(stripos($error->getMessage(),$pattern)!==false){$category=$label;break;}
 echo $step.' failed: '.get_class($error).' ('.$category.")\n";exit(1);
}
