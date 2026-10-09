<?php
/** Disposable PostgreSQL database; no AI, email or real account calls. */
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Application\Workspace\{VacancyService,WorkspaceService,PublicVacancyService};
use App\Infrastructure\Workspace\ReferenceCatalog;
use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\{DriverManager,Tools\DsnParser};
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,ConflictHttpException,NotFoundHttpException,UnprocessableEntityHttpException};
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function rejects(callable $call,string $class):void {try{$call();}catch(Throwable $e){if($e instanceof $class)return;throw $e;}throw new RuntimeException('Expected '.$class);}
$params=(new DsnParser(['postgresql'=>'pdo_pgsql','postgres'=>'pdo_pgsql']))->parse(getenv('DATABASE_URL'));
$admin=DriverManager::getConnection($params);$name='vacancy_contract_'.bin2hex(random_bytes(5));$db=null;
try {
 $admin->executeStatement('CREATE DATABASE '.$name);$params['dbname']=$name;$db=DriverManager::getConnection($params);
 $db->executeStatement('CREATE SCHEMA catalog');$db->executeStatement('SET search_path TO catalog,public');
 foreach(json_decode(file_get_contents(__DIR__.'/../resources/migrations/20261008/schema.json'),true,512,JSON_THROW_ON_ERROR) as $sql)$db->executeStatement($sql);
 $owner=['id'=>'00000000-0000-4000-8000-000000000001','role'=>'employer'];$other=['id'=>'00000000-0000-4000-8000-000000000002','role'=>'employer'];
 foreach([$owner,$other] as $user)$db->insert('catalog.employer_profiles',['user_uuid'=>$user['id'],'public_name'=>'Fixture','contact_name'=>'Fixture','contact_email'=>'fixture@example.invalid','employer_type'=>'company']);
 $http=new MockHttpClient(fn()=>throw new RuntimeException('Unexpected external request'));
 $workspace=new WorkspaceService($db,new SchemaSqlHelper('catalog'),new ReferenceCatalog($http,'http://dictionary'),$http,'http://auth');
 $service=new VacancyService($db,new SchemaSqlHelper('catalog'),$workspace);
 $input=['id'=>'00000000-0000-4000-8000-000000000011','version'=>0,'title'=>'PHP-разработчик','content'=>['format'=>'remote','salary_from'=>'100000','salary_to'=>'150000','contact_email'=>'fixture@example.invalid']];
 $row=$service->save($owner,$input,'');check($row['status']==='draft'&&(int)$row['version']===1&&$row['optional_assignment']===null,'Initial draft');
 $service->save($owner,$input,'');check((int)$db->fetchOne('SELECT count(*) FROM catalog.vacancies')===1,'Replay duplicated vacancy');
 rejects(fn()=>$service->get($other,$input['id']),NotFoundHttpException::class);
 rejects(fn()=>$service->save($other,$input+[],''),NotFoundHttpException::class);
 rejects(fn()=>$service->save(['id'=>$owner['id'],'role'=>'applicant'],$input,''),AccessDeniedHttpException::class);
 foreach([['salary_from'=>'-1'],['salary_from'=>'150000','salary_to'=>'100000'],['contact_email'=>'broken'],['format'=>'unknown'],['unexpected'=>'value']] as $bad){$invalid=$input;$invalid['content']=array_replace($input['content'],$bad);rejects(fn()=>$service->save($owner,$invalid,''),UnprocessableEntityHttpException::class);}
 $edit=$input;$edit['version']=1;$edit['title']='Обновлённая вакансия';$saved=$service->save($owner,$edit,'');check((int)$saved['version']===2&&$saved['title']===$edit['title'],'Edit lost');
 rejects(fn()=>$service->save($owner,$edit,''),ConflictHttpException::class);
 $edit['version']=2;$edit['content']['salary_negotiable']=true;$saved=$service->save($owner,$edit,'');check($saved['content']['salary_from']===''&&$saved['content']['salary_to']==='','Negotiable salary not cleared');
 $public=new PublicVacancyService($db,new SchemaSqlHelper('catalog'));
 check($public->list()===[],'Draft exposed publicly');
 rejects(fn()=>$public->get($input['id']),NotFoundHttpException::class);
 rejects(fn()=>$service->publication($owner,$input['id'],['version'=>3],true),UnprocessableEntityHttpException::class);
 rejects(fn()=>$service->publication($other,$input['id'],['version'=>3],true),NotFoundHttpException::class);
 $edit['version']=3;$edit['content']=array_replace($edit['content'],['specialty'=>'Backend','experience'=>'1–3 года','employment'=>'full','description'=>'Описание','tasks'=>'REST API','skills'=>'PHP, Symfony','requirements'=>'Знание PHP','company'=>'Fixture company','contact_name'=>'Fixture contact']);
 $complete=$service->save($owner,$edit,'');
 $published=$service->publication($owner,$input['id'],['version'=>4],true);
 check($published['status']==='published'&&$published['published_at']!==null,'Publish failed');
 $publicRow=$public->get($input['id']);check(count($public->list())===1&&$publicRow['id']===$input['id']&&$publicRow['format']==='Удалённо','Public catalog failed');
 check(!array_key_exists('employer_id',$publicRow)&&!array_key_exists('contact_email',$publicRow)&&!str_contains(json_encode($publicRow),'fixture@example.invalid'),'Private account data exposed');
 rejects(fn()=>$service->publication($owner,$input['id'],['version'=>4],false),ConflictHttpException::class);
 $unpublished=$service->publication($owner,$input['id'],['version'=>5],false);check($unpublished['status']==='draft'&&$unpublished['published_at']===null&&$public->list()===[],'Unpublish failed');
 rejects(fn()=>$public->get($input['id']),NotFoundHttpException::class);
 rejects(fn()=>$public->get('1'),NotFoundHttpException::class);
 $dashboard=$workspace->dashboard($owner,'');check($dashboard['counts']['vacancies']===1&&count($dashboard['vacancies'])===1,'Owner dashboard');check($workspace->dashboard($other,'')['vacancies']===[],'Other owner leaked');
 for($i=20;$i<41;$i++){$extra=$input;$extra['id']=sprintf('00000000-0000-4000-8000-%012d',$i);$service->save($owner,$extra,'');}
 $first=$workspace->dashboard($owner,'',1,1);$second=$workspace->dashboard($owner,'',1,2);check(count($first['vacancies'])===20&&count($second['vacancies'])===2&&$second['vacancies_pages']===2,'Pagination failed');
 $db->executeStatement("UPDATE catalog.vacancies SET status='published' WHERE id=?",[$input['id']]);$edit['version']=3;rejects(fn()=>$service->save($owner,$edit,''),ConflictHttpException::class);
 echo "PASS: drafts, replay, ownership, roles, validation, version conflicts, pagination, assignment placeholder, publication validation, public visibility, unpublish, privacy\n";
} finally {
 if($db)$db->close();$admin->executeStatement('DROP DATABASE IF EXISTS '.$name);$admin->close();
}
