<?php
/** Disposable DB: a chat must not require or manufacture a resume. */
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Application\Workspace\{ApplicationService,WorkspaceService};
use App\Infrastructure\Workspace\ReferenceCatalog;
use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\{DriverManager,Tools\DsnParser};
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,NotFoundHttpException,UnprocessableEntityHttpException};
function check(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function rejects(callable $f,string $class):void{try{$f();}catch(Throwable $e){if($e instanceof $class)return;throw $e;}throw new RuntimeException('Expected '.$class);}
$params=(new DsnParser(['postgresql'=>'pdo_pgsql']))->parse(getenv('DATABASE_URL'));$admin=DriverManager::getConnection($params);$name='quick_chat_'.bin2hex(random_bytes(5));$db=null;
try{
 $admin->executeStatement('CREATE DATABASE '.$name);$params['dbname']=$name;$db=DriverManager::getConnection($params);$db->executeStatement('CREATE SCHEMA catalog');$db->executeStatement('SET search_path TO catalog,public');
 foreach(json_decode(file_get_contents(__DIR__.'/../resources/migrations/20261008/schema.json'),true) as $sql)$db->executeStatement($sql);
 require __DIR__.'/../migrations/Version20261009190000.php';$migration=new DoctrineMigrations\Version20261009190000($db,new Psr\Log\NullLogger());$migration->up(new Doctrine\DBAL\Schema\Schema());foreach($migration->getSql() as $sql)$db->executeStatement($sql->getStatement());
 $a=['id'=>'00000000-0000-4000-8000-000000000001','role'=>'applicant'];$e=['id'=>'00000000-0000-4000-8000-000000000002','role'=>'employer'];$x=['id'=>'00000000-0000-4000-8000-000000000003','role'=>'applicant'];
 foreach([$a,$x] as $who)$db->insert('catalog.candidate_profiles',['user_uuid'=>$who['id'],'display_name'=>'Candidate','contact_email'=>'fixture@example.invalid','bio'=>'Profile biography']);
 $db->insert('catalog.employer_profiles',['user_uuid'=>$e['id'],'contact_name'=>'Employer','contact_email'=>'fixture@example.invalid','employer_type'=>'company','public_name'=>'Company']);
 $v=$db->fetchOne("INSERT INTO catalog.vacancies(employer_id,title,status,published_at) VALUES(?,'Fixture','published',now()) RETURNING id",[$e['id']]);$draft=$db->fetchOne("INSERT INTO catalog.vacancies(employer_id,title) VALUES(?,'Draft') RETURNING id",[$e['id']]);
 $http=new MockHttpClient(fn()=>throw new RuntimeException('Unexpected external request'));$workspace=new WorkspaceService($db,new SchemaSqlHelper('catalog'),new ReferenceCatalog($http,'http://dictionary'),$http,'http://auth');$service=new ApplicationService($db,new SchemaSqlHelper('catalog'),$workspace);
 $first=$service->startChat($a,['vacancyId'=>$v],'');check($first['resume_id']===null&&$first['resume_snapshot']['kind']==='profile','Fictitious resume');check($first['resume_snapshot']['text']==='Profile biography','Profile not snapshotted');check($first['employer_id']===$e['id'],'Wrong employer');
 check($service->startChat($a,['vacancyId'=>$v],'')['id']===$first['id'],'Duplicate application');check((int)$db->fetchOne('SELECT count(*) FROM catalog.resumes')===0,'Resume was manufactured');
 rejects(fn()=>$service->startChat($e,['vacancyId'=>$v],''),AccessDeniedHttpException::class);rejects(fn()=>$service->startChat($x,['vacancyId'=>$draft],''),NotFoundHttpException::class);rejects(fn()=>$service->startChat($a,['vacancyId'=>'broken'],''),UnprocessableEntityHttpException::class);rejects(fn()=>$service->startChat($a,['vacancyId'=>$v,'employerId'=>$x['id']],''),UnprocessableEntityHttpException::class);
 $db->executeStatement("UPDATE catalog.vacancies SET status='draft',published_at=NULL WHERE id=?",[$v]);check($service->startChat($a,['vacancyId'=>$v],'')['id']===$first['id'],'Existing chat unavailable after unpublishing');rejects(fn()=>$service->startChat($x,['vacancyId'=>$v],''),NotFoundHttpException::class);
 rejects(fn()=>$service->context($x,$first['id']),NotFoundHttpException::class);
 echo "PASS: profile-only application, no fictitious resume, stable replay, correct employer, participant isolation, draft/publication rules, validation\n";
}finally{if($db)$db->close();$admin->executeStatement('DROP DATABASE IF EXISTS '.$name.' WITH(FORCE)');$admin->close();}
