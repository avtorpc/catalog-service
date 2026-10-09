<?php
require dirname(__DIR__).'/vendor/autoload.php';
use Doctrine\DBAL\{DriverManager,Tools\DsnParser};
$db=DriverManager::getConnection((new DsnParser(['postgresql'=>'pdo_pgsql']))->parse(getenv('DATABASE_URL')));
$file='/tmp/mesto-chat-fixture.json';
function uuid(){ $h=bin2hex(random_bytes(16));return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-8'.substr($h,17,3).'-'.substr($h,20);}
if(($argv[1]??'')==='setup'){
 if(file_exists($file))throw new RuntimeException('Fixture exists; clean it first');
 $a=uuid();$e=uuid();$x=uuid();$v=uuid();$f=['candidate'=>$a,'employer'=>$e,'stranger'=>$x,'vacancy'=>$v,'candidateEmail'=>'chat-candidate-'.$a.'@example.invalid','employerEmail'=>'chat-employer-'.$e.'@example.invalid','strangerEmail'=>'chat-stranger-'.$x.'@example.invalid'];
 file_put_contents($file,json_encode($f));
 $db->transactional(function()use($db,$f){foreach(['candidate'=>'applicant','employer'=>'employer','stranger'=>'applicant'] as $k=>$role)$db->insert('auth.users',['user_uuid'=>$f[$k],'email'=>$f[$k.'Email'],'first_name'=>$k==='employer'?'Тестовый работодатель':'Тестовый соискатель','last_name'=>'','verification_channel_id'=>'email','password_hash'=>password_hash('Fixture password 123',PASSWORD_BCRYPT),'role_code'=>$role,'profile'=>$role==='employer'?'{"employerType":"company","company":"Компания чата"}':'{}']);
 $db->insert('catalog.employer_profiles',['user_uuid'=>$f['employer'],'contact_name'=>'Тестовый работодатель','contact_email'=>$f['employerEmail'],'employer_type'=>'company','public_name'=>'Компания чата']);
 $db->insert('catalog.vacancies',['id'=>$f['vacancy'],'employer_id'=>$f['employer'],'title'=>'Проверка чата · PHP-разработчик','content'=>json_encode(['company'=>'Компания чата','specialty'=>'Backend','experience'=>'3–6 лет','format'=>'remote','employment'=>'full','cities'=>'Москва','salary_negotiable'=>true,'description'=>'Тестовая вакансия для проверки переписки','tasks'=>'Разрабатывать API','skills'=>'PHP, Symfony','requirements'=>'Опыт разработки','contact_name'=>'Работодатель','contact_email'=>$f['employerEmail']]),'status'=>'published','published_at'=>date('c')]);});
 echo json_encode($f,JSON_UNESCAPED_UNICODE);
}elseif(($argv[1]??'')==='cleanup'){
 $f=json_decode(file_get_contents($file),true);$db->transactional(function()use($db,$f){
  $apps=$db->fetchFirstColumn('SELECT id FROM catalog.applications WHERE candidate_id=? AND employer_id=?',[$f['candidate'],$f['employer']]);foreach($apps as $id){$db->executeStatement('DELETE FROM node.messages WHERE conversation_id IN(SELECT id FROM node.conversations WHERE application_id=?)',[$id]);$db->executeStatement('DELETE FROM node.conversations WHERE application_id=?',[$id]);}
  $db->executeStatement('DELETE FROM catalog.applications WHERE candidate_id=? AND employer_id=?',[$f['candidate'],$f['employer']]);$db->executeStatement('DELETE FROM catalog.resumes WHERE candidate_id=?',[$f['candidate']]);$db->executeStatement('DELETE FROM catalog.vacancies WHERE id=? AND employer_id=?',[$f['vacancy'],$f['employer']]);$db->executeStatement('DELETE FROM catalog.employer_profiles WHERE user_uuid=?',[$f['employer']]);
  foreach(['candidate','employer','stranger'] as $k){$db->executeStatement('DELETE FROM catalog.candidate_profiles WHERE user_uuid=?',[$f[$k]]);$id=$db->fetchOne('SELECT id FROM auth.users WHERE user_uuid=? AND email=?',[$f[$k],$f[$k.'Email']]);if($id){$db->executeStatement('DELETE FROM auth.refresh_tokens WHERE user_id=?',[$id]);$db->executeStatement('DELETE FROM auth.users WHERE id=?',[$id]);}}
 });unlink($file);echo 'Fixture removed';
}
