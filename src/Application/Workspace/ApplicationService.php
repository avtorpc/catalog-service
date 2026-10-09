<?php

declare(strict_types=1);
namespace App\Application\Workspace;
use Doctrine\DBAL\Connection;
use App\Infrastructure\DB\SchemaSqlHelper;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,NotFoundHttpException,UnprocessableEntityHttpException};
final class ApplicationService
{
 public function __construct(private Connection $db,private SchemaSqlHelper $schema,private WorkspaceService $workspace) {}
 public function create(array $who,array $input,string $token):array {
  if($who['role']!=='applicant')throw new AccessDeniedHttpException('Отклик доступен соискателю.');
  if(array_diff(array_keys($input),['vacancyId','resumeTitle','resumeText','coverLetter']))throw new UnprocessableEntityHttpException('Неизвестные поля отклика.');
  $id=$input['vacancyId']??'';if(!is_string($id)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$id))throw new NotFoundHttpException('Вакансия не найдена.');
  $text=[];foreach(['resumeTitle'=>150,'resumeText'=>10000,'coverLetter'=>5000] as $key=>$max){$v=$input[$key]??'';if(!is_string($v)||mb_strlen($v)>$max||($key!=='coverLetter'&&trim($v)===''))throw new UnprocessableEntityHttpException('Заполните название и текст резюме, проверьте длину полей.');$text[$key]=trim($v);}
  $profile=$this->workspace->initialize($who,$token);
  $this->db->transactional(function()use($who,$id,$text,$profile){
   // Serialize duplicate applications for this applicant/vacancy before creating a resume.
   $this->db->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',[$who['id'].':'.$id])->free();
   if($this->db->fetchOne('SELECT id FROM '.$this->schema->table('applications').' WHERE candidate_id=? AND vacancy_id=?',[$who['id'],$id]))return;
   $v=$this->db->fetchAssociative('SELECT * FROM '.$this->schema->table('vacancies')." WHERE id=? AND status='published' AND published_at IS NOT NULL FOR SHARE",[$id]);
   if(!$v)throw new NotFoundHttpException('Вакансия не опубликована или снята с публикации.');
   $snapshot=['title'=>$text['resumeTitle'],'text'=>$text['resumeText'],'display_name'=>$profile['display_name'],'contact_email'=>$profile['contact_email'],'headline'=>$profile['headline'],'city'=>$profile['city'],'coverLetter'=>$text['coverLetter']];
   $json=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
   $resume=$this->db->fetchOne('INSERT INTO '.$this->schema->table('resumes').'(candidate_id,title,content) VALUES(?,?,CAST(? AS jsonb)) RETURNING id',[$who['id'],$text['resumeTitle'],$json]);
   $this->db->executeStatement('INSERT INTO '.$this->schema->table('applications').'(candidate_id,employer_id,vacancy_id,resume_id,resume_snapshot) VALUES(?,?,?,?,CAST(? AS jsonb))',[$who['id'],$v['employer_id'],$id,$resume,$json]);
  });
  $appId=$this->db->fetchOne('SELECT id FROM '.$this->schema->table('applications').' WHERE candidate_id=? AND vacancy_id=?',[$who['id'],$id]);return $this->context($who,$appId);
 }
 public function startChat(array $who,array $input,string $token):array {
  if($who['role']!=='applicant')throw new AccessDeniedHttpException('Отклик доступен только соискателю. Войдите в аккаунт соискателя.');
  $id=$input['vacancyId']??null;
  if(array_keys($input)!==['vacancyId']||!is_string($id)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$id))throw new UnprocessableEntityHttpException('Некорректная вакансия.');
  $profile=$this->workspace->initialize($who,$token);
  $appId=$this->db->transactional(function()use($who,$id,$profile){
   $this->db->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',[$who['id'].':'.$id])->free();
   $existing=$this->db->fetchOne('SELECT id FROM '.$this->schema->table('applications').' WHERE candidate_id=? AND vacancy_id=?',[$who['id'],$id]);
   if($existing)return $existing;
   $v=$this->db->fetchAssociative('SELECT employer_id FROM '.$this->schema->table('vacancies')." WHERE id=? AND status='published' AND published_at IS NOT NULL FOR SHARE",[$id]);
   if(!$v)throw new NotFoundHttpException('Вакансия снята с публикации или недоступна.');
   // A profile snapshot is not a fabricated resume. A conversation may start before a resume exists.
   $snapshot=['kind'=>'profile','display_name'=>$profile['display_name'],'contact_email'=>$profile['contact_email'],'headline'=>$profile['headline'],'city'=>$profile['city'],'text'=>$profile['bio'],'coverLetter'=>''];
   return $this->db->fetchOne('INSERT INTO '.$this->schema->table('applications').'(candidate_id,employer_id,vacancy_id,resume_id,resume_snapshot) VALUES(?,?,?,NULL,CAST(? AS jsonb)) RETURNING id',[$who['id'],$v['employer_id'],$id,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
  });return $this->context($who,$appId);
 }
 public function context(array $who,string $id):array {
  if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$id))throw new NotFoundHttpException('Отклик не найден.');
  $row=$this->db->fetchAssociative($this->select().' WHERE a.id=? AND a.'.($who['role']==='applicant'?'candidate_id':'employer_id').'=?',[$id,$who['id']]);
  if(!$row)throw new NotFoundHttpException('Отклик не найден.');return $this->normalize($row);
 }
 public function list(array $who,int $page=1):array {
  $page=max(1,$page);$where='a.'.($who['role']==='applicant'?'candidate_id':'employer_id').'=?';$count=(int)$this->db->fetchOne('SELECT count(*) FROM '.$this->schema->table('applications').' a WHERE '.$where,[$who['id']]);$pages=max(1,(int)ceil($count/100));$page=min($page,$pages);
  $rows=$this->db->fetchAllAssociative($this->select().' WHERE '.$where.' ORDER BY a.updated_at DESC,a.id LIMIT 100 OFFSET '.(($page-1)*100),[$who['id']]);return ['items'=>array_map($this->normalize(...),$rows),'pages'=>$pages,'page'=>$page];
 }
 public function review(array $who,string $id):array {
  if($who['role']!=='employer')throw new AccessDeniedHttpException('Статус меняет работодатель.');$this->context($who,$id);
  $this->db->executeStatement('UPDATE '.$this->schema->table('applications')." SET status='reviewing',updated_at=now() WHERE id=? AND employer_id=? AND status='submitted'",[$id,$who['id']]);return $this->context($who,$id);
 }
 public function update(array $who,string $id,array $input):array {
  if($who['role']!=='employer')throw new AccessDeniedHttpException('Статус меняет работодатель.');$this->context($who,$id);
  if(array_diff(array_keys($input),['status','score','comment'])||!$input)throw new UnprocessableEntityHttpException('Некорректные поля.');
  $set=[];$params=[];
  if(array_key_exists('status',$input)){if(!in_array($input['status'],['submitted','reviewing','invited','rejected'],true))throw new UnprocessableEntityHttpException('Некорректный статус.');$set[]='status=?';$params[]=$input['status'];}
  if(array_key_exists('score',$input)){if(!is_int($input['score'])||$input['score']<0||$input['score']>100)throw new UnprocessableEntityHttpException('Оценка должна быть от 0 до 100.');$set[]='employer_score=?';$params[]=$input['score'];$set[]="status=CASE WHEN status='submitted' THEN 'reviewing' ELSE status END";}
  if(array_key_exists('comment',$input)){if(!is_string($input['comment'])||mb_strlen($input['comment'])>5000)throw new UnprocessableEntityHttpException('Комментарий слишком длинный.');$set[]='employer_comment=?';$params[]=trim($input['comment']);}
  // Avoid assigning status twice when the caller explicitly chooses it.
  if(array_key_exists('status',$input))$set=array_values(array_filter($set,fn($s)=>!str_starts_with($s,'status=CASE')));
  $params[]=$id;$params[]=$who['id'];$this->db->executeStatement('UPDATE '.$this->schema->table('applications').' SET '.implode(',',$set).',updated_at=now() WHERE id=? AND employer_id=?',$params);return $this->context($who,$id);
 }
 private function select():string {return 'SELECT a.*,v.title AS vacancy_title,e.public_name AS company,p.display_name AS candidate_name FROM '.$this->schema->table('applications').' a JOIN '.$this->schema->table('vacancies').' v ON v.id=a.vacancy_id JOIN '.$this->schema->table('employer_profiles').' e ON e.user_uuid=a.employer_id JOIN '.$this->schema->table('candidate_profiles').' p ON p.user_uuid=a.candidate_id';}
 private function normalize(array $r):array {foreach(['resume_snapshot','assignment_snapshot','answer'] as $k)if(is_string($r[$k]))$r[$k]=json_decode($r[$k],true,512,JSON_THROW_ON_ERROR);return $r;}
}
