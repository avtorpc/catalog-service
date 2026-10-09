<?php

declare(strict_types=1);
namespace App\Application\Workspace;
use App\Infrastructure\DB\SchemaSqlHelper;
use App\Infrastructure\Workspace\ReferenceCatalog;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,UnauthorizedHttpException,UnprocessableEntityHttpException,ServiceUnavailableHttpException};
final class WorkspaceService
{
 public function __construct(private Connection $db,private SchemaSqlHelper $schema,private ReferenceCatalog $references,
  private HttpClientInterface $http,#[Autowire('%env(AUTH_SERVICE_URL)%')] private string $authUrl) {}
 public function identity(object $claims):array {
  $role=match($claims->roles??null){['ROLE_APPLICANT']=>'applicant',['ROLE_EMPLOYER']=>'employer',default=>null};
  $id=$claims->sub??null;
  if($role===null||!is_string($id)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di',$id)||($claims->exp??0)<=time())throw new UnauthorizedHttpException('Bearer','Войдите в аккаунт.');
  return ['id'=>$id,'role'=>$role];
 }
 public function initialize(array $identity,string $token):array {
  $role=$identity['role'];$table=$this->schema->table($role==='applicant'?'candidate_profiles':'employer_profiles');
  $existing=$this->db->fetchAssociative("SELECT * FROM {$table} WHERE user_uuid=?",[$identity['id']]);
  if($existing)return $this->normalize($existing);
  try{
   $response=$this->http->request('GET',rtrim($this->authUrl,'/').'/user/me',['headers'=>['Authorization'=>'Bearer '.$token],'timeout'=>3,'max_duration'=>5,'max_redirects'=>0]);
   if($response->getStatusCode()===401)throw new UnauthorizedHttpException('Bearer','Войдите в аккаунт.');
   $account=$response->toArray()['data']??null;
  }catch(UnauthorizedHttpException $e){throw $e;}catch(\Throwable){throw new ServiceUnavailableHttpException(5,'Данные аккаунта временно недоступны.');}
  if(!is_array($account)||($account['uuid']??null)!==$identity['id']||($account['roles']??null)!==[$role==='applicant'?'ROLE_APPLICANT':'ROLE_EMPLOYER'])throw new UnauthorizedHttpException('Bearer','Аккаунт не найден.');
  $name=$account['firstName'];$email=$account['email'];
  if($role==='applicant'){
   $this->db->executeStatement("INSERT INTO {$table}(user_uuid,display_name,contact_email) VALUES(?,?,?) ON CONFLICT(user_uuid) DO NOTHING",[$identity['id'],$name,$email]);
  }else{
   $profile=$account['profile']??[];$type=$profile['employerType']??'company';$company=$profile['company']??$name;
   $this->db->executeStatement("INSERT INTO {$table}(user_uuid,contact_name,contact_email,employer_type,public_name) VALUES(?,?,?,?,?) ON CONFLICT(user_uuid) DO NOTHING",[$identity['id'],$name,$email,$type,$company]);
  }
  return $this->normalize($this->db->fetchAssociative("SELECT * FROM {$table} WHERE user_uuid=?",[$identity['id']]));
 }
 private function normalize(array $row):array {
  if(isset($row['assessment_preferences'])&&is_string($row['assessment_preferences']))$row['assessment_preferences']=json_decode($row['assessment_preferences'],true,512,JSON_THROW_ON_ERROR);
  return $row;
 }
 public function dashboard(array $identity,string $token,int $testsPage=1):array {
  $profile=$this->initialize($identity,$token);$id=$identity['id'];
  if($identity['role']==='applicant'){
   $counts=['resumes'=>$this->count('resumes','candidate_id',$id),'applications'=>$this->count('applications','candidate_id',$id),'tests'=>$this->count('tests','candidate_id',$id)];
   $testsPage=max(1,min($testsPage,max(1,(int)ceil($counts['tests']/20))));$offset=($testsPage-1)*20;
   $tests=$this->db->fetchAllAssociative("SELECT t.id,t.status,t.specialization_id,t.declared_grade_id,t.created_at,t.assignment->>'title' AS title,a.status AS attempt_status FROM ".$this->schema->table('tests').' t LEFT JOIN '.$this->schema->table('test_attempts').' a ON a.test_id=t.id WHERE t.candidate_id=? ORDER BY t.created_at DESC,t.id DESC LIMIT 20 OFFSET '.$offset,[$id]);
  }else{
   $counts=['vacancies'=>$this->count('vacancies','employer_id',$id),'applications'=>$this->count('applications','employer_id',$id),'offers'=>$this->count('offers','employer_id',$id)];$tests=[];
  }
  return ['role'=>$identity['role'],'profile'=>$profile,'counts'=>$counts,'tests'=>$tests,'resumes'=>$identity['role']==='applicant'?$this->resumes($identity):[],'tests_page'=>max(1,$testsPage),'tests_pages'=>$identity['role']==='applicant'?max(1,(int)ceil($counts['tests']/20)):1];
 }
 private function count(string $table,string $owner,string $id):int {return (int)$this->db->fetchOne('SELECT count(*) FROM '.$this->schema->table($table).' WHERE '.$owner.'=?',[$id]);}
 public function resumes(array $identity):array {
  $this->applicant($identity);
  $rows=$this->db->fetchAllAssociative('SELECT id,title,content,status,visibility,pdf_original_name,pdf_size,created_at,updated_at FROM '.$this->schema->table('resumes').' WHERE candidate_id=? ORDER BY updated_at DESC',[$identity['id']]);
  $tests=$this->db->fetchAllAssociative('SELECT competency_ids,status FROM '.$this->schema->table('tests').' WHERE candidate_id=? ORDER BY created_at DESC',[$identity['id']]);$competencies=array_column($this->references->items('competencies'),null,'id');
  foreach($rows as &$row){
   $row['content']=json_decode($row['content'],true,64,JSON_THROW_ON_ERROR);$row['id']=(string)$row['id'];$row['pdf'] = $row['pdf_original_name'] ? ['name'=>$row['pdf_original_name'],'size'=>(int)$row['pdf_size']] : null;unset($row['pdf_original_name'],$row['pdf_size']);$row['skill_results']=[];
   foreach(($row['content']['competencyIds']??[]) as $skill){$result=['competency_id'=>$skill,'name'=>$competencies[$skill]['name']??('Навык №'.$skill),'status'=>'not_tested','label'=>'Тест не пройден'];foreach($tests as $test){$ids=json_decode($test['competency_ids'],true);if(in_array((int)$skill,$ids,true)){$result=['competency_id'=>$skill,'name'=>$competencies[$skill]['name']??('Навык №'.$skill),'status'=>$test['status'],'label'=>match($test['status']){'ready'=>'Тестовое задание готово','generating'=>'Тест готовится','generation_failed'=>'Генерация не удалась',default=>'Тест создан'}];break;}}$row['skill_results'][]=$result;}
  }
  return $rows;
 }
 public function attachResumePdf(array $identity,string $id,\Symfony\Component\HttpFoundation\File\UploadedFile $file,ResumePdfStorage $storage):array { $this->applicant($identity);$old=$this->db->fetchOne('SELECT pdf_storage_name FROM '.$this->schema->table('resumes').' WHERE id=? AND candidate_id=?',[$id,$identity['id']]);if($old===false)throw new \InvalidArgumentException('Резюме не найдено.');$pdf=$storage->save($file);$updated=$this->db->executeStatement('UPDATE '.$this->schema->table('resumes').' SET pdf_original_name=?,pdf_storage_name=?,pdf_mime_type=?,pdf_size=?,updated_at=CURRENT_TIMESTAMP,version=version+1 WHERE id=? AND candidate_id=?',[$pdf['original_name'],$pdf['storage_name'],$pdf['mime_type'],$pdf['size'],$id,$identity['id']]);if($updated!==1){$storage->remove($pdf['storage_name']);throw new \InvalidArgumentException('Резюме не найдено.');}$storage->remove((string)$old);return $this->resumes($identity); }
 public function resumePdf(array $identity,string $id,string $baseDir):array { $this->applicant($identity);$row=$this->db->fetchAssociative('SELECT pdf_storage_name,pdf_original_name,pdf_mime_type FROM '.$this->schema->table('resumes').' WHERE id=? AND candidate_id=?',[$id,$identity['id']]);if(!$row||!$row['pdf_storage_name'])throw new \InvalidArgumentException('PDF у этого резюме не прикреплён.');$path=rtrim($baseDir,'/').'/resumes/'.$row['pdf_storage_name'];if(!is_file($path))throw new \RuntimeException('Файл PDF не найден в хранилище.');return ['path'=>$path,'name'=>$row['pdf_original_name'],'mime'=>$row['pdf_mime_type']?:'application/pdf']; }
 public function createResume(array $identity,array $input):array {
  $this->applicant($identity);$title=trim((string)($input['title']??''));if($title===''||mb_strlen($title)>150)throw new UnprocessableEntityHttpException('Укажите название резюме.');$content=$input['content']??[];if(!is_array($content))throw new UnprocessableEntityHttpException('Некорректные данные резюме.');$allowed=['specialty','city','experience','salary','about','skills','competencyIds','technologyIds'];$content=array_intersect_key($content,array_flip($allowed));foreach(['skills','competencyIds','technologyIds'] as $field)if(isset($content[$field])&&!is_array($content[$field]))throw new UnprocessableEntityHttpException('Навыки должны быть списком.');$json=json_encode($content,JSON_THROW_ON_ERROR);$resumeId=$input['id']??null;if($resumeId!==null){if(!is_string($resumeId)||!preg_match('/^[0-9a-f-]{36}$/i',$resumeId))throw new UnprocessableEntityHttpException('Некорректный идентификатор резюме.');$updated=$this->db->executeStatement('UPDATE '.$this->schema->table('resumes').' SET title=?,content=CAST(? AS JSONB),updated_at=CURRENT_TIMESTAMP,version=version+1 WHERE id=? AND candidate_id=?',[$title,$json,$resumeId,$identity['id']]);if($updated!==1)throw new UnprocessableEntityHttpException('Резюме не найдено.');}else $this->db->executeStatement('INSERT INTO '.$this->schema->table('resumes').'(candidate_id,title,content) VALUES(?,?,CAST(? AS JSONB))',[$identity['id'],$title,$json]);return $this->resumes($identity);
 }
 public function saveProfile(array $identity,array $input,string $token):array {
  $this->initialize($identity,$token);
  $applicant=$identity['role']==='applicant';
  $fields=$applicant?['display_name'=>100,'headline'=>150,'city'=>100,'bio'=>5000]:['contact_name'=>100,'public_name'=>150,'city'=>100,'description'=>5000,'website'=>500];
  $allowed=[...array_keys($fields),$applicant?'visibility':'employer_type'];
  if(array_diff(array_keys($input),$allowed))throw new UnprocessableEntityHttpException('В форме есть неизвестные поля.');
  $updates=[];$params=[];
  foreach($fields as $field=>$max){if(!array_key_exists($field,$input))continue;
   if(!is_string($input[$field])||mb_strlen(trim($input[$field]))>$max)throw new UnprocessableEntityHttpException('Проверьте длину полей профиля.');
   $value=trim($input[$field]);
   if(in_array($field,['display_name','contact_name','public_name'],true)&&$value==='')throw new UnprocessableEntityHttpException('Укажите имя и название работодателя.');
   if($field==='website'&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!in_array(parse_url($value,PHP_URL_SCHEME),['http','https'],true)))throw new UnprocessableEntityHttpException('Укажите адрес сайта, начинающийся с http:// или https://.');
   $updates[]=$field.'=?';$params[]=$value;
  }
  $enum=$applicant?'visibility':'employer_type';
  if(array_key_exists($enum,$input)){
   if(!in_array($input[$enum],$applicant?['private','public']:['company','entrepreneur','private'],true))throw new UnprocessableEntityHttpException('Некорректное значение профиля.');
   $updates[]=$enum.'=?';$params[]=$input[$enum];
  }
  if($updates){$params[]=$identity['id'];$this->db->executeStatement('UPDATE '.$this->schema->table($applicant?'candidate_profiles':'employer_profiles').' SET '.implode(',',$updates).',updated_at=CURRENT_TIMESTAMP WHERE user_uuid=?',$params);}
  return $this->dashboard($identity,$token);
 }
 public function options(array $identity,?int $specialization):array {
  $this->applicant($identity);
  $specs=$this->references->items('specializations');$grades=$this->references->items('grades');$technologies=$this->references->items('technologies');
  $criteria=$this->references->items('criteria');$links=$this->references->items('criterion-competencies');$specLinks=$this->references->items('specialization-competencies');$competencies=$this->references->items('competencies');
  if($specialization!==null&&!in_array($specialization,array_column($specs,'id'),true))throw new UnprocessableEntityHttpException('Специализация недоступна.');
  $byCriterion=array_column($criteria,null,'id');$byComp=[];
  foreach($links as $link)if(isset($byCriterion[$link['criterion_id']]))$byComp[$link['competency_id']][]=$byCriterion[$link['criterion_id']];
  $ids=[];foreach($specLinks as $link)if($link['specialization_id']===$specialization)$ids[]=$link['competency_id'];
  $available=[];
  foreach($competencies as $item)if(in_array($item['id'],$ids,true)&&!empty($byComp[$item['id']])){
   $item['criteria']=array_map(fn($c)=>['id'=>$c['id'],'code'=>$c['code'],'version'=>$c['version'],'name'=>$c['name']],$byComp[$item['id']]);$available[]=$item;
  }
  return ['specializations'=>$specs,'grades'=>$grades,'technologies'=>$technologies,'competencies'=>$available,'specializationId'=>$specialization];
 }
 public function savePreferences(array $identity,array $input,string $token):array {
  $this->applicant($identity);$this->initialize($identity,$token);
  if(array_diff(array_keys($input),['specializationId','gradeId','competencyIds','technologyIds']))throw new UnprocessableEntityHttpException('В выборе есть неизвестные поля.');
  $spec=$input['specializationId']??null;$grade=$input['gradeId']??null;$comps=$input['competencyIds']??null;$tech=$input['technologyIds']??null;
  if(!is_int($spec)||!is_int($grade)||!is_array($comps)||!array_is_list($comps)||count($comps)<1||count($comps)>3||!is_array($tech)||!array_is_list($tech))throw new UnprocessableEntityHttpException('Выберите специализацию, грейд и от одной до трёх компетенций.');
  foreach([...$comps,...$tech] as $id)if(!is_int($id)||$id<1)throw new UnprocessableEntityHttpException('Некорректный выбор.');
  if(count(array_unique($comps))!==count($comps)||count(array_unique($tech))!==count($tech))throw new UnprocessableEntityHttpException('Выбранные значения не должны повторяться.');
  $options=$this->options($identity,$spec);
  if(!in_array($grade,array_column($options['grades'],'id'),true)||array_diff($comps,array_column($options['competencies'],'id'))||array_diff($tech,array_column($options['technologies'],'id')))throw new UnprocessableEntityHttpException('Выбранные значения недоступны для специализации.');
  $candidates=array_column($options['competencies'],null,'id');
  if(!$this->matching($comps,$candidates,[]))throw new UnprocessableEntityHttpException('Нет полного набора неповторяющихся критериев. Измените набор компетенций.');
  $this->db->executeStatement('UPDATE '.$this->schema->table('candidate_profiles').' SET assessment_preferences=CAST(? AS JSONB),updated_at=CURRENT_TIMESTAMP WHERE user_uuid=?',[json_encode($input,JSON_THROW_ON_ERROR),$identity['id']]);
  return $input;
 }
 private function matching(array $ids,array $candidates,array $used):bool {
  if(!$ids)return true;$id=array_shift($ids);
  foreach($candidates[$id]['criteria'] as $criterion){$code=$criterion['code'];if(isset($used[$code]))continue;$next=$used;$next[$code]=true;if($this->matching($ids,$candidates,$next))return true;}
  return false;
 }
 private function applicant(array $identity):void {if($identity['role']!=='applicant')throw new AccessDeniedHttpException('Квалификационное тестирование доступно соискателям.');}
}
