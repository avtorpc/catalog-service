<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Doctrine\DBAL\{DriverManager,Tools\DsnParser};
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
$url=getenv('DATABASE_URL');$params=(new DsnParser(['postgresql'=>'pdo_pgsql','postgres'=>'pdo_pgsql']))->parse($url);$admin=DriverManager::getConnection($params);
$name='catalog_schema_contract_'.bin2hex(random_bytes(5));$db=null;$kernel=null;
function check(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);}
function reject(Doctrine\DBAL\Connection $db,string $sql,array $params=[]):void {
 try{$db->executeStatement($sql,$params);}catch(Doctrine\DBAL\Exception\DriverException $e){if(str_starts_with($e->getSQLState()??'','23'))return;throw $e;}
 throw new RuntimeException('Database accepted an invalid relationship or state');
}
try{
 $admin->executeStatement('CREATE DATABASE '.$name);$params['dbname']=$name;$db=DriverManager::getConnection($params);
 $newUrl=preg_replace('~/[^/?]+(?=\?|$)~','/'.$name,$url,1);
 $_SERVER['DATABASE_URL']=$_ENV['DATABASE_URL']=$newUrl;putenv('DATABASE_URL='.$newUrl);
 $_SERVER['DB_SCHEMA']=$_ENV['DB_SCHEMA']='catalog';putenv('DB_SCHEMA=catalog');
 $kernel=new App\Kernel('prod',false);$app=new Application($kernel);$app->setAutoExit(false);$app->setCatchExceptions(false);
 foreach([1,2] as $run){$input=new ArrayInput(['command'=>'doctrine:migrations:migrate','--no-interaction'=>true]);$input->setInteractive(false);$out=new BufferedOutput();check($app->run($input,$out)===0,'Migration failed');}
 $expected=['applications','assessment_jobs','bookmarks','candidate_profiles','employer_profiles','offers','resumes','saved_filters','test_attempts','tests','vacancies'];
 check($db->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname='catalog' AND tablename NOT LIKE 'doctrine_migration_versions%' ORDER BY tablename")===$expected,'Wrong table set');
 $a='00000000-0000-4000-8000-000000000001';$b='00000000-0000-4000-8000-000000000002';$e='00000000-0000-4000-8000-000000000003';$f='00000000-0000-4000-8000-000000000004';
 foreach([$a,$b] as $id)$db->insert('catalog.candidate_profiles',['user_uuid'=>$id,'display_name'=>'Candidate','contact_email'=>$id.'@example.invalid']);
 foreach([$e,$f] as $id)$db->insert('catalog.employer_profiles',['user_uuid'=>$id,'contact_name'=>'Employer','contact_email'=>$id.'@example.invalid','employer_type'=>'company','public_name'=>'Company']);
 $resume=$db->fetchOne("INSERT INTO catalog.resumes(candidate_id,title) VALUES (?,'Draft') RETURNING id",[$a]);
 $vacancy=$db->fetchOne("INSERT INTO catalog.vacancies(employer_id,title) VALUES (?,'Draft') RETURNING id",[$e]);
 reject($db,'INSERT INTO catalog.applications(candidate_id,employer_id,vacancy_id,resume_id,resume_snapshot) VALUES (?,?,?,?,?)',[$b,$e,$vacancy,$resume,'{}']);
 $application=$db->fetchOne('INSERT INTO catalog.applications(candidate_id,employer_id,vacancy_id,resume_id,resume_snapshot) VALUES (?,?,?,?,?) RETURNING id',[$a,$e,$vacancy,$resume,'{}']);
 reject($db,'INSERT INTO catalog.applications(candidate_id,employer_id,vacancy_id,resume_id,resume_snapshot) VALUES (?,?,?,?,?)',[$a,$e,$vacancy,$resume,'{}']);
 reject($db,'INSERT INTO catalog.offers(candidate_id,employer_id,vacancy_id) VALUES (?,?,?)',[$a,$f,$vacancy]);
 $offer=$db->fetchOne('INSERT INTO catalog.offers(candidate_id,employer_id) VALUES (?,?) RETURNING id',[$a,$e]);
 reject($db,'UPDATE catalog.applications SET employer_score=101 WHERE id=?',[$application]);
 $test=$db->fetchOne("INSERT INTO catalog.tests(candidate_id,request_key,specialization_id,declared_grade_id,competency_ids,technology_context,criteria_snapshot,status,assignment) VALUES (?,'key',1,1,'[1]','[]','[{}]','ready','{}') RETURNING id",[$a]);
 reject($db,"INSERT INTO catalog.test_attempts(candidate_id,test_id,status) VALUES (?,?,'in_progress')",[$b,$test]);
 $db->executeStatement("INSERT INTO catalog.test_attempts(candidate_id,test_id,status) VALUES (?,?,'in_progress')",[$a,$test]);
 reject($db,"INSERT INTO catalog.test_attempts(candidate_id,test_id,status) VALUES (?,?,'in_progress')",[$a,$test]);
 reject($db,'UPDATE catalog.tests SET assignment=NULL WHERE id=?',[$test]);
 reject($db,'UPDATE catalog.tests SET criteria_snapshot=CAST(? AS JSONB) WHERE id=?',['[]',$test]);
 reject($db,"INSERT INTO catalog.saved_filters(user_uuid,catalog_type) VALUES (?,'wrong')",[$a]);
 echo "PASS: 11 tables, full Doctrine install/repeat, document ownership, unique applications, offer context, assessment ownership, one attempt, snapshots and score constraints\n";
}finally{if($kernel!==null)$kernel->shutdown();if($db!==null)$db->close();$admin->executeStatement('DROP DATABASE IF EXISTS '.$name.' WITH (FORCE)');$admin->close();}
