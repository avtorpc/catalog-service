<?php
/** Real temporary PostgreSQL database, with a deterministic HTTP double instead of paid API calls. */
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Application\Workspace\{AssessmentService,AssessmentContract,WorkspaceService};
use App\Infrastructure\Workspace\{ReferenceCatalog,GigaChatClient};
use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\{DriverManager,Tools\DsnParser};
use Symfony\Component\HttpClient\{MockHttpClient,Response\MockResponse};
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
function verify(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
function rejects(callable $call,string $class): void {try{$call();}catch(Throwable $e){if($e instanceof $class)return;throw $e;}throw new RuntimeException('Invalid action accepted: '.$class);}
$url=getenv('DATABASE_URL');$params=(new DsnParser(['postgresql'=>'pdo_pgsql','postgres'=>'pdo_pgsql']))->parse($url);$admin=DriverManager::getConnection($params);
$name='assessment_contract_'.bin2hex(random_bytes(5));$db=null;$kernel=null;
try{
 $admin->executeStatement('CREATE DATABASE '.$name);$params['dbname']=$name;$db=DriverManager::getConnection($params);
 $newUrl=preg_replace('~/[^/?]+(?=\?|$)~','/'.$name,$url,1);
 $_SERVER['DATABASE_URL']=$_ENV['DATABASE_URL']=$newUrl;putenv('DATABASE_URL='.$newUrl);
 $_SERVER['DB_SCHEMA']=$_ENV['DB_SCHEMA']='catalog';putenv('DB_SCHEMA=catalog');
 $kernel=new App\Kernel('prod',false);$app=new Application($kernel);$app->setAutoExit(false);$app->setCatchExceptions(false);
 $in=new ArrayInput(['command'=>'doctrine:migrations:migrate','--no-interaction'=>true]);$in->setInteractive(false);verify($app->run($in,new BufferedOutput())===0,'Migration failed');
 $a=['id'=>'00000000-0000-4000-8000-000000000001','role'=>'applicant'];$b=['id'=>'00000000-0000-4000-8000-000000000002','role'=>'applicant'];$e=['id'=>'00000000-0000-4000-8000-000000000003','role'=>'employer'];
 $selection=['specializationId'=>1,'gradeId'=>1,'competencyIds'=>[1,2],'technologyIds'=>[1]];
 foreach([$a,$b] as $identity)$db->insert('catalog.candidate_profiles',['user_uuid'=>$identity['id'],'display_name'=>'Fixture','contact_email'=>'fixture@example.invalid','assessment_preferences'=>json_encode($selection)]);
 $refs=['specializations'=>[['id'=>1,'code'=>'backend','name'=>'Backend']], 'grades'=>[['id'=>1,'code'=>'junior','name'=>'Junior']],
 'technologies'=>[['id'=>1,'code'=>'php','name'=>'PHP']], 'competencies'=>[['id'=>1,'code'=>'logic','name'=>'Логика'],['id'=>2,'code'=>'api','name'=>'API']],
 'criteria'=>[['id'=>1,'code'=>'correct','version'=>1,'name'=>'Корректность','description'=>'Правильный результат','check_templates'=>['Успех','Ошибка']],['id'=>2,'code'=>'contract','version'=>1,'name'=>'Контракт','description'=>'Контракт API','check_templates'=>['Успех','Ошибка']]],
 'criterion-competencies'=>[['criterion_id'=>1,'competency_id'=>1],['criterion_id'=>2,'competency_id'=>1],['criterion_id'=>1,'competency_id'=>2]],
 'specialization-competencies'=>[['specialization_id'=>1,'competency_id'=>1],['specialization_id'=>1,'competency_id'=>2]]];
 $wrongFunction=false;$providerCalls=0;$oauthCalls=0;$unauthorized=false;$fail=false;$invalid=false;$lastPayload=null;
 $http=new MockHttpClient(function(string $method,string $uri,array $options)use($refs,&$wrongFunction,&$providerCalls,&$oauthCalls,&$unauthorized,&$fail,&$invalid,&$lastPayload){
  if(str_starts_with($uri,'http://dictionary/')){verify(!isset($options['cafile']),'GigaChat CA leaked to internal client');return new MockResponse(json_encode(['success'=>true,'data'=>['items'=>$refs[basename($uri)]]]));}
  verify(($options['cafile']??null)==='/opt/gigachat/ca-bundle.pem','Client-specific CA missing');
  verify(($options['max_redirects']??null)===0,'Credential redirects allowed');
  if($uri==='https://ngw.devices.sberbank.ru:9443/api/v2/oauth'){
   ++$oauthCalls;parse_str($options['body'],$form);verify($form['scope']==='GIGACHAT_API_PERS','Wrong OAuth scope');
   $headers=implode(' ', $options['headers']);verify(str_contains($headers,'Basic synthetic-test-key')&&preg_match('/RqUID: [0-9a-f-]{36}/i',$headers)===1,'OAuth headers missing');
   return new MockResponse(json_encode(['access_token'=>'synthetic-bearer-'.$oauthCalls,'expires_at'=>(time()+1800)*1000]));
  }
  verify($uri==='https://api.giga.chat/v1/chat/completions','Wrong provider endpoint');++$providerCalls;
  if($unauthorized){$unauthorized=false;return new MockResponse('{}',['http_code'=>401]);}
  verify(($options['verify_peer']??true)&&($options['verify_host']??true),'TLS disabled');
  $request=json_decode($options['body'],true,64,JSON_THROW_ON_ERROR);
  verify($request['stream']===false&&isset($request['functions'][0]['parameters'])&&$request['function_call']['name']===$request['functions'][0]['name'],'GigaChat forced result schema missing');
  if($request['function_call']['name']==='generate_task')verify($request['functions'][0]['parameters']['properties']['reason']['type']==='string'&&$request['functions'][0]['parameters']['properties']['assignment']['type']==='object','Unsupported nullable function parameter');
  $payload=json_decode($request['messages'][1]['content'],true,64,JSON_THROW_ON_ERROR);$lastPayload=$payload;
  verify(!str_contains($request['messages'][1]['content'],'fixture@example.invalid'),'Personal account data sent to model');
  if($fail)return new MockResponse(json_encode(['error'=>['type'=>'insufficient_quota','code'=>is_string($fail)?$fail:null,'message'=>'Provider diagnostic must not be exposed']]),['http_code'=>403]);
  if($payload['operation']==='generate_task'){
   $result=['compatible'=>true,'reason'=>'','title'=>'Fixture task','assignment'=>['text'=>'Учебная задача: успешный и ошибочный запрос.','deliverables'=>['Код'],'constraints'=>[]],'required_technology_codes'=>['php'],'criteria_checks'=>[]];
   foreach($payload['selected_pairs'] as $i=>$pair)$result['criteria_checks'][]=$pair+['checks'=>[['check_id'=>'success_'.$i,'condition'=>'Успех','expected_evidence'=>['Код']],['check_id'=>'error_'.$i,'condition'=>'Ошибка','expected_evidence'=>['Код']]]];
   if($invalid)$result['criteria_checks'][0]['criterion_code']='injected';
  }else{
   $result=['summary'=>'Предварительный анализ','criteria'=>[]];
   foreach($payload['criteria_snapshot'] as $c){$checks=[];foreach($c['checks'] as $i=>$check)$checks[]=['check_id'=>$check['check_id'],'status'=>$i===0?'passed':'unknown','explanation'=>'Анализ подхода','evidence'=>'Фрагмент ответа'];$result['criteria'][]=['criterion_code'=>$c['code'],'criterion_version'=>$c['version'],'checks'=>$checks];}
  }
  return new MockResponse(json_encode(['choices'=>[['finish_reason'=>'function_call','message'=>['role'=>'assistant','content'=>'','function_call'=>['name'=>$wrongFunction?'unexpected_function':$payload['operation'],'arguments'=>$result]]]]]));
 });
 $ref=new ReferenceCatalog($http,'http://dictionary');$sql=new SchemaSqlHelper('catalog');$workspace=new WorkspaceService($db,$sql,$ref,$http,'http://auth');
 $ai=new GigaChatClient($http,'synthetic-test-key','GIGACHAT_API_PERS','test-model','/opt/gigachat/ca-bundle.pem');$contract=new AssessmentContract();$service=new AssessmentService($db,$sql,$workspace,$ref,$ai,$contract);
 $key=['requestKey'=>'00000000-0000-4000-8000-000000000011'];
 $task=$service->create($a,$key,'');$id=$task['id'];verify($task['status']==='generating','Task not queued');
 verify($service->create($a,$key,'')['id']===$id,'Request replay duplicated task');
 verify($service->create($a,['requestKey'=>'00000000-0000-4000-8000-000000000012'],'')['id']===$id,'Parallel generation duplicated task');
 rejects(fn()=>$service->get($b,$id),Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
 rejects(fn()=>$service->get($e,$id),Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
 rejects(fn()=>$service->answer($a,$id,['text'=>'Draft'],false),Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
 verify($service->work(),'Worker did not claim generation');$task=$service->get($a,$id);verify($task['status']==='ready','Task not generated');
 verify($providerCalls===1&&count(array_unique(array_column($task['criteria'],'code')))===2,'Unique matching or provider count incorrect');
 $frozen=$task['criteria'];$service->answer($a,$id,['text'=>'Draft'],false);verify($service->get($a,$id)['attempt']['answer']['text']==='Draft','Draft not persisted');
 rejects(fn()=>$service->answer($a,$id,['text'=>'   '],true),Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class);
 rejects(fn()=>$service->answer($a,$id,['text'=>'x','criteria'=>[]],true),Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException::class);
 $task=$service->answer($a,$id,['text'=>'Final code'],true);verify($task['attempt']['status']==='evaluating','Answer not submitted');
 $service->answer($a,$id,['text'=>'Overwrite after submission'],true);verify($service->get($a,$id)['attempt']['answer']['text']==='Final code','Replay overwrote immutable answer');
 rejects(fn()=>$service->answer($a,$id,['text'=>'Overwrite'],false),Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
 $fail=true;$unauthorized=true;$service->work();verify($oauthCalls===2,'Revoked bearer did not refresh once');verify($service->get($a,$id)['attempt']['status']==='evaluation_failed','Provider failure not persisted');
 $fail=false;$service->retry($a,$id,'evaluate');$service->work();$task=$service->get($a,$id);
 verify($task['attempt']['status']==='awaiting_review'&&$task['attempt']['evaluation']['criteria'][0]['score']===null,'Unknown treated as a numeric score');
 verify($lastPayload['criteria_snapshot']===$frozen&&$lastPayload['candidate_answer']['text']==='Final code','Evaluation did not use frozen data');
 verify(!$task['attempt']['evaluation']['code_executed']&&!$task['attempt']['evaluation']['grade_confirmed'],'False verification claim');
 $result=['summary'=>'Test','criteria'=>[]];foreach($frozen as $c){$checks=[];foreach($c['checks'] as $i=>$check)$checks[]=['check_id'=>$check['check_id'],'status'=>$i===0?'passed':'failed','explanation'=>'Test','evidence'=>'Answer'];$result['criteria'][]=['criterion_code'=>$c['code'],'criterion_version'=>$c['version'],'checks'=>$checks];}
 verify($contract->evaluation($result,$frozen)['criteria'][0]['score']===1,'Server scale wrong');
 $bad=$result;$bad['criteria'][0]['checks'][1]['check_id']='injected';rejects(fn()=>$contract->evaluation($bad,$frozen),UnexpectedValueException::class);
 $invalid=true;$task2=$service->create($a,['requestKey'=>'00000000-0000-4000-8000-000000000013'],'');$snapshot=$task2['criteria'];$before=$providerCalls;$service->work();
 verify($providerCalls===$before+2&&$service->get($a,$task2['id'])['status']==='generation_failed','Malformed generation was accepted or retried indefinitely');
 $invalid=false;$service->retry($a,$task2['id'],'generate');$service->work();verify(array_column($service->get($a,$task2['id'])['criteria'],'code')===array_column($snapshot,'code'),'Retry reselected frozen criteria');
 $task3=$service->create($b,['requestKey'=>'00000000-0000-4000-8000-000000000014'],'');
 $db->executeStatement("UPDATE catalog.assessment_jobs SET status='processing',lease='00000000-0000-4000-8000-000000000090',updated_at=CURRENT_TIMESTAMP-INTERVAL '6 minutes' WHERE test_id=?",[$task3['id']]);
 $service->work();verify($service->get($b,$task3['id'])['status']==='ready','Interrupted worker job not reclaimed');
 $fail='credit_balance_exhausted';$task4=$service->create($a,['requestKey'=>'00000000-0000-4000-8000-000000000015'],'');$service->work();$quotaTask=$service->get($a,$task4['id']);
 verify($quotaTask['status']==='generation_failed'&&$quotaTask['error_code']==='GIGACHAT_HTTP_403','Safe provider diagnostic lost');
 verify(!str_contains($quotaTask['error'],'Provider diagnostic')&&!str_contains($quotaTask['error'],'API'),'Provider internals leaked into candidate message');$fail=false;
 $wrongFunction=true;$wrapped=$service->create($a,['requestKey'=>'00000000-0000-4000-8000-000000000016'],'');$service->work();verify($service->get($a,$wrapped['id'])['status']==='generation_failed','Unexpected function was accepted');$wrongFunction=false;$service->retry($a,$wrapped['id'],'generate');$service->work();verify($service->get($a,$wrapped['id'])['status']==='ready','Valid structured result was rejected');
 verify($oauthCalls===2,'Unexpired bearer not reused');
 verify(!$service->work(),'Completed job reprocessed');
 echo "PASS: isolated DB migrations, owner/role checks, idempotency, random complete matching, durable generation, immutable criteria/answer, draft, evaluation/retry, unknown and 0/1/2 scoring, JSON validation, stale-job recovery, GigaChat schema, scoped CA, OAuth reuse/401 refresh, safe provider error handling\n";
}finally{if($kernel)$kernel->shutdown();if($db)$db->close();$admin->executeStatement('DROP DATABASE IF EXISTS '.$name.' WITH (FORCE)');$admin->close();}
