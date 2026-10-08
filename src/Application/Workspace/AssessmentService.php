<?php

declare(strict_types=1);
namespace App\Application\Workspace;

use App\Infrastructure\DB\SchemaSqlHelper;
use App\Infrastructure\Workspace\{ReferenceCatalog,GigaChatClient};
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,NotFoundHttpException,ConflictHttpException,UnprocessableEntityHttpException,ServiceUnavailableHttpException};

final class AssessmentService
{
    public function __construct(private Connection $db, private SchemaSqlHelper $schema, private WorkspaceService $workspace,
        private ReferenceCatalog $references, private GigaChatClient $ai, private AssessmentContract $contract) {}

    public function create(array $identity, array $input, string $token): array
    {
        $this->applicant($identity);
        $key = $input['requestKey'] ?? null;
        if (array_keys($input) !== ['requestKey'] || !is_string($key) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $key))
            throw new UnprocessableEntityHttpException('Обновите страницу и повторите получение задания.');
        $this->workspace->initialize($identity, $token);
        return $this->db->transactional(function () use ($identity, $key) {
            $profile = $this->db->fetchAssociative('SELECT * FROM '.$this->table('candidate_profiles').' WHERE user_uuid=? FOR UPDATE', [$identity['id']]);
            $existing = $this->db->fetchOne('SELECT id FROM '.$this->table('tests').' WHERE candidate_id=? AND request_key=?', [$identity['id'],$key]);
            if ($existing) return $this->get($identity, (int)$existing);
            // Multiple simultaneous clicks must not enqueue multiple provider calls for one candidate.
            $pending = $this->db->fetchOne('SELECT id FROM '.$this->table('tests')." WHERE candidate_id=? AND status='generating' ORDER BY id DESC LIMIT 1", [$identity['id']]);
            if ($pending) return $this->get($identity, (int)$pending);
            if (!$this->ai->configured()) throw new ServiceUnavailableHttpException(null,'Получение задания временно недоступно: подключение к GigaChat не настроено.');
            $selection = json_decode($profile['assessment_preferences'], true, 64, JSON_THROW_ON_ERROR);
            if (!$selection) throw new UnprocessableEntityHttpException('Сначала сохраните выбор тестирования.');
            $this->workspace->savePreferences($identity, $selection, '');
            [$payload,$snapshot,$technologies] = $this->selection($selection);
            $id = $this->db->fetchOne('INSERT INTO '.$this->table('tests')."(candidate_id,request_key,specialization_id,declared_grade_id,competency_ids,technology_context,criteria_snapshot,generation_metadata,status)
                VALUES(?,?,?,?,CAST(? AS JSONB),CAST(? AS JSONB),CAST(? AS JSONB),CAST(? AS JSONB),'generating') RETURNING id",
                [$identity['id'],$key,$selection['specializationId'],$selection['gradeId'],$this->json($selection['competencyIds']),$this->json($technologies),$this->json($snapshot),
                    $this->json(['provider'=>'gigachat','model'=>$this->ai->model(),'prompt_version'=>'mesto-generation-v1','generation_request'=>$payload])]);
            $this->db->executeStatement('INSERT INTO '.$this->table('assessment_jobs')."(test_id,operation) VALUES(?,'generate')", [$id]);
            return $this->get($identity, (int)$id);
        });
    }

    public function get(array $identity, int $id): array
    {
        $this->applicant($identity);
        $task = $this->owned($identity, $id);
        $attempt = $this->db->fetchAssociative('SELECT status,answer,evaluation,started_at,submitted_at FROM '.$this->table('test_attempts').' WHERE test_id=? AND candidate_id=?', [$id,$identity['id']]);
        if ($attempt) foreach (['answer','evaluation'] as $field) if ($attempt[$field] !== null) $attempt[$field] = json_decode($attempt[$field],true,64,JSON_THROW_ON_ERROR);
        $meta = json_decode($task['generation_metadata'],true,64,JSON_THROW_ON_ERROR);
        return ['id'=>(int)$task['id'],'status'=>$task['status'],'created_at'=>$task['created_at'],
            'assignment'=>$task['assignment'] === null ? null : json_decode($task['assignment'],true,64,JSON_THROW_ON_ERROR),
            'criteria'=>json_decode($task['criteria_snapshot'],true,64,JSON_THROW_ON_ERROR),
            'selection'=>$meta['generation_request']['selection'] ?? [],'scale'=>AssessmentContract::SCALE,
            'error'=>$meta['error'] ?? null,'error_code'=>$meta['error_code'] ?? null,'attempt'=>$attempt ?: null];
    }

    public function answer(array $identity, int $id, array $input, bool $submit): array
    {
        $this->applicant($identity);
        if (array_diff(array_keys($input), ['text']) || !is_string($input['text'] ?? null) || mb_strlen($input['text']) > 30000)
            throw new UnprocessableEntityHttpException('Ответ должен быть текстом длиной не более 30 000 символов.');
        if ($submit && trim($input['text']) === '') throw new UnprocessableEntityHttpException('Введите решение перед отправкой на оценку.');
        return $this->db->transactional(function () use ($identity,$id,$input,$submit) {
            $task = $this->owned($identity,$id,true);
            if ($task['status'] !== 'ready') throw new ConflictHttpException('Сначала дождитесь готового задания.');
            $attempt = $this->db->fetchAssociative('SELECT * FROM '.$this->table('test_attempts').' WHERE test_id=? AND candidate_id=? FOR UPDATE',[$id,$identity['id']]);
            if ($attempt && $attempt['status'] !== 'in_progress') {
                if ($submit) return $this->get($identity,$id); // Lost submission response: immutable answer, same attempt.
                throw new ConflictHttpException('Отправленный ответ уже нельзя редактировать.');
            }
            $answer = $this->json(['text'=>$input['text']]);
            if (!$attempt) $this->db->executeStatement('INSERT INTO '.$this->table('test_attempts')."(test_id,candidate_id,status,answer) VALUES(?,?,'in_progress',CAST(? AS JSONB))",[$id,$identity['id'],$answer]);
            else $this->db->executeStatement('UPDATE '.$this->table('test_attempts').' SET answer=CAST(? AS JSONB) WHERE id=?',[$answer,$attempt['id']]);
            if ($submit) {
                if (!$this->ai->configured()) throw new ServiceUnavailableHttpException(null,'Оценка временно недоступна.');
                $this->db->executeStatement('UPDATE '.$this->table('test_attempts')." SET status='evaluating',submitted_at=CURRENT_TIMESTAMP WHERE test_id=?",[$id]);
                $this->db->executeStatement('INSERT INTO '.$this->table('assessment_jobs')."(test_id,operation) VALUES(?,'evaluate')",[$id]);
            }
            return $this->get($identity,$id);
        });
    }

    public function retry(array $identity, int $id, string $operation): array
    {
        $this->applicant($identity);
        return $this->db->transactional(function () use ($identity,$id,$operation) {
            $task = $this->owned($identity,$id,true);
            $attempt = $this->db->fetchAssociative('SELECT status FROM '.$this->table('test_attempts').' WHERE test_id=? FOR UPDATE',[$id]);
            if ($operation === 'generate') {
                if ($task['status'] !== 'generation_failed') return $this->get($identity,$id);
                $metadata = json_decode($task['generation_metadata'],true,64,JSON_THROW_ON_ERROR);
                if (!empty($metadata['incompatible'])) throw new ConflictHttpException('Измените выбор и получите новое задание: сочетание несовместимо.');
                unset($metadata['error'],$metadata['error_code']);
                $metadata['provider'] = 'gigachat'; $metadata['model'] = $this->ai->model();
                $this->db->executeStatement('UPDATE '.$this->table('tests')." SET status='generating',generation_metadata=CAST(? AS JSONB) WHERE id=?",[$this->json($metadata),$id]);
            } else {
                if (!$attempt || $attempt['status'] !== 'evaluation_failed') return $this->get($identity,$id);
                $this->db->executeStatement('UPDATE '.$this->table('test_attempts')." SET status='evaluating',evaluation=NULL WHERE test_id=?",[$id]);
            }
            $this->db->executeStatement('UPDATE '.$this->table('assessment_jobs')." SET status='pending',lease=NULL,executions=0,updated_at=CURRENT_TIMESTAMP WHERE test_id=? AND operation=?",[$id,$operation]);
            return $this->get($identity,$id);
        });
    }

    /** Claims are short transactions; no database locks are held during network requests. */
    public function work(): bool
    {
        $job = $this->db->transactional(function () {
            $job = $this->db->fetchAssociative('SELECT * FROM '.$this->table('assessment_jobs')." WHERE status='pending' OR (status='processing' AND updated_at<CURRENT_TIMESTAMP-INTERVAL '5 minutes') ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1");
            if (!$job) return null;
            $job['lease'] = $this->uuid();
            $this->db->executeStatement('UPDATE '.$this->table('assessment_jobs')." SET status='processing',lease=?,executions=executions+1,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$job['lease'],$job['id']]);
            return $job;
        });
        if (!$job) return false;
        try {
            if ((int)$job['executions'] >= 2) throw new \RuntimeException('Worker interrupted repeatedly');
            $task = $this->db->fetchAssociative('SELECT * FROM '.$this->table('tests').' WHERE id=?',[$job['test_id']]);
            $snapshot = json_decode($task['criteria_snapshot'],true,64,JSON_THROW_ON_ERROR);
            $metadata = json_decode($task['generation_metadata'],true,64,JSON_THROW_ON_ERROR);
            if ($job['operation'] === 'generate') {
                $payload = $metadata['generation_request'];
                $result = $this->modelResult(AssessmentContract::GENERATION_INSTRUCTION,$payload,
                    fn($r) => $this->contract->generation($r,$snapshot,array_column($payload['selection']['technologies'],'code')));
            } else {
                $attempt = $this->db->fetchAssociative('SELECT answer FROM '.$this->table('test_attempts').' WHERE test_id=?',[$job['test_id']]);
                $payload = ['operation'=>'evaluate_task','prompt_version'=>'mesto-evaluation-v1','assignment'=>json_decode($task['assignment'],true,64,JSON_THROW_ON_ERROR),
                    'criteria_snapshot'=>$snapshot,'scale'=>AssessmentContract::SCALE,'candidate_answer'=>json_decode($attempt['answer'],true,64,JSON_THROW_ON_ERROR),
                    'execution_evidence'=>[],'code_execution_available'=>false];
                $result = $this->modelResult(AssessmentContract::EVALUATION_INSTRUCTION,$payload,fn($r) => $this->contract->evaluation($r,$snapshot));
                $result['provider'] = 'gigachat'; $result['model'] = $this->ai->model(); $result['prompt_version'] = 'mesto-evaluation-v1';
            }
            $this->finish($job, function () use ($job,$result,$metadata) {
                if ($job['operation'] === 'generate') {
                    if (!$result['compatible']) {
                        $metadata['error'] = $result['reason']; $metadata['incompatible'] = true;
                        $this->db->executeStatement('UPDATE '.$this->table('tests')." SET status='generation_failed',generation_metadata=CAST(? AS JSONB) WHERE id=?",[$this->json($metadata),$job['test_id']]);
                    } else {
                        $metadata['provider'] = 'gigachat'; $metadata['model'] = $this->ai->model();
                        $metadata['generated_at'] = gmdate('c');
                        $this->db->executeStatement('UPDATE '.$this->table('tests')." SET status='ready',assignment=CAST(? AS JSONB),criteria_snapshot=CAST(? AS JSONB),generation_metadata=CAST(? AS JSONB) WHERE id=?",[$this->json($result['assignment']),$this->json($result['snapshot']),$this->json($metadata),$job['test_id']]);
                    }
                } else $this->db->executeStatement('UPDATE '.$this->table('test_attempts').' SET status=?,evaluation=CAST(? AS JSONB) WHERE test_id=?',[$result['status'],$this->json($result),$job['test_id']]);
            });
        } catch (\Throwable $error) {
            // Never propagate HTTP exceptions that can contain Authorization headers or candidate answers.
            $code = preg_match('/^GIGACHAT_[A-Z0-9_]{1,100}$/D',$error->getMessage()) ? $error->getMessage() : ($error instanceof \UnexpectedValueException ? 'MODEL_CONTRACT_INVALID' : 'PROVIDER_CONNECTION_OR_WORKER_ERROR');
            $message = 'Не удалось получить или проверить ответ ИИ. Задание и ответ сохранены; можно повторить запрос.';
            $this->finish($job, function () use ($job,$message,$code) {
                if ($job['operation'] === 'generate') $this->db->executeStatement('UPDATE '.$this->table('tests')." SET status='generation_failed',generation_metadata=jsonb_set(jsonb_set(generation_metadata,'{error}',to_jsonb(CAST(? AS TEXT))),'{error_code}',to_jsonb(CAST(? AS TEXT))) WHERE id=?",[$message,$code,$job['test_id']]);
                else $this->db->executeStatement('UPDATE '.$this->table('test_attempts')." SET status='evaluation_failed',evaluation=CAST(? AS JSONB) WHERE test_id=?",[$this->json(['error'=>$message,'error_code'=>$code]),$job['test_id']]);
            }, true);
        }
        return true;
    }

    private function finish(array $job, callable $save, bool $failed = false): void
    {
        $this->db->transactional(function () use ($job,$save,$failed) {
            $current = $this->db->fetchAssociative('SELECT status,lease FROM '.$this->table('assessment_jobs').' WHERE id=? FOR UPDATE',[$job['id']]);
            if ($current['status'] !== 'processing' || $current['lease'] !== $job['lease']) return;
            $save();
            $this->db->executeStatement('UPDATE '.$this->table('assessment_jobs').' SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$failed?'failed':'done',$job['id']]);
        });
    }

    private function modelResult(string $instruction, array $payload, callable $validate): array
    {
        for ($try=0;$try<2;++$try) {
            try { return $validate($this->ai->json($instruction,$payload)); }
            catch (\UnexpectedValueException $e) {
                if ($try === 1) throw $e;
                $payload['validation_feedback'] = 'Предыдущий ответ не соответствовал формату. Верни полный JSON строго по инструкции, точно все коды, версии и две проверки каждого критерия.';
            }
        }
        throw new \LogicException();
    }

    private function selection(array $selection): array
    {
        $options = $this->workspace->options(['role'=>'applicant'],$selection['specializationId']);
        $spec = array_column($options['specializations'],null,'id')[$selection['specializationId']];
        $grade = array_column($options['grades'],null,'id')[$selection['gradeId']];
        $competencies = array_column($options['competencies'],null,'id');
        $criteria = array_column($this->references->items('criteria'),null,'id');
        $pairs = $this->matching($selection['competencyIds'],$competencies,[]);
        if ($pairs === null) throw new UnprocessableEntityHttpException('Нет полного набора неповторяющихся критериев. Измените выбор.');
        $snapshot = []; $selectedPairs = [];
        foreach ($pairs as $competencyId=>$criterionId) {
            $c = $criteria[$criterionId]; $comp = $competencies[$competencyId];
            $snapshot[] = ['code'=>$c['code'],'version'=>$c['version'],'name'=>$c['name'],'description'=>$c['description'],
                'check_templates'=>$c['check_templates'],'competency_code'=>$comp['code'],'competency_name'=>$comp['name'],'scale'=>AssessmentContract::SCALE];
            $selectedPairs[] = ['competency_code'=>$comp['code'],'criterion_code'=>$c['code'],'criterion_version'=>$c['version']];
        }
        $technologyMap = array_column($options['technologies'],null,'id');
        $technologies = array_map(fn($id) => ['code'=>$technologyMap[$id]['code'],'name'=>$technologyMap[$id]['name'],'role'=>'assessed'],$selection['technologyIds']);
        $payload = ['operation'=>'generate_task','prompt_version'=>'mesto-generation-v1','selection'=>['specialization_code'=>$spec['code'],
            'specialization_name'=>$spec['name'],'declared_grade_code'=>$grade['code'],'declared_grade_name'=>$grade['name'],
            'competency_codes'=>array_map(fn($id) => $competencies[$id]['code'],$selection['competencyIds']),'technologies'=>$technologies],
            'criteria'=>$snapshot,'selected_pairs'=>$selectedPairs,'selection_policy'=>'random_complete_unique_matching_v1','scale'=>AssessmentContract::SCALE,
            'constraints'=>['max_competencies'=>3,'checks_per_criterion'=>2,'allow_new_criteria'=>false,'allow_grade_assignment'=>false,'require_explicit_candidate_conditions'=>true,'allow_unselected_required_technologies'=>false]];
        return [$payload,$snapshot,$technologies];
    }

    private function matching(array $ids, array $competencies, array $used): ?array
    {
        if (!$ids) return [];
        $id = array_shift($ids); $criteria = $competencies[$id]['criteria']; shuffle($criteria);
        foreach ($criteria as $c) {
            if (isset($used[$c['code']])) continue;
            $next = $used; $next[$c['code']] = true;
            $rest = $this->matching($ids,$competencies,$next);
            if ($rest !== null) return [$id=>$c['id']] + $rest;
        }
        return null;
    }
    private function owned(array $identity, int $id, bool $lock = false): array
    {
        $task = $this->db->fetchAssociative('SELECT * FROM '.$this->table('tests').' WHERE id=? AND candidate_id=?'.($lock?' FOR UPDATE':''),[$id,$identity['id']]);
        if (!$task) throw new NotFoundHttpException('Задание не найдено.');
        return $task;
    }
    private function applicant(array $identity): void { if ($identity['role'] !== 'applicant') throw new AccessDeniedHttpException('Задания доступны только соискателям.'); }
    private function table(string $name): string { return $this->schema->table($name); }
    private function json(array $value): string { return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    private function uuid(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$s=bin2hex($b);return substr($s,0,8).'-'.substr($s,8,4).'-'.substr($s,12,4).'-'.substr($s,16,4).'-'.substr($s,20); }
}
