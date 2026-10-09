<?php

declare(strict_types=1);
namespace App\Infrastructure\Workspace;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Certificate trust is scoped to this client, never to the host or other HTTP clients. */
final class GigaChatClient
{
    private ?string $accessToken = null;
    private int $expiresAt = 0;

    public function __construct(private HttpClientInterface $http,
        #[Autowire('%env(AI_API_KEY)%')] private string $key,
        #[Autowire('%env(GIGACHAT_SCOPE)%')] private string $scope,
        #[Autowire('%env(GIGACHAT_MODEL)%')] private string $model,
        #[Autowire('%env(GIGACHAT_CA_FILE)%')] private string $caFile) {}

    public function configured(): bool { return trim($this->key) !== ''; }
    public function model(): string { return $this->model; }

    private function tls(): array
    {
        if (!is_readable($this->caFile)) throw new \RuntimeException('GIGACHAT_CA_UNAVAILABLE');
        return ['cafile'=>$this->caFile, 'verify_peer'=>true, 'verify_host'=>true, 'max_redirects'=>0];
    }

    private function token(): string
    {
        if ($this->accessToken !== null && $this->expiresAt > time() + 60) return $this->accessToken;
        $uuid = bin2hex(random_bytes(16));
        $rqUid = substr($uuid,0,8).'-'.substr($uuid,8,4).'-4'.substr($uuid,13,3).'-8'.substr($uuid,17,3).'-'.substr($uuid,20);
        $response = $this->http->request('POST','https://ngw.devices.sberbank.ru:9443/api/v2/oauth', $this->tls() + [
            'headers'=>['Authorization'=>'Basic '.trim($this->key), 'RqUID'=>$rqUid, 'Accept'=>'application/json'],
            'body'=>['scope'=>$this->scope], 'timeout'=>10, 'max_duration'=>10,
        ]);
        if ($response->getStatusCode() !== 200) throw new \RuntimeException('GIGACHAT_OAUTH_HTTP_'.$response->getStatusCode());
        $body = $response->toArray(false);
        $token = $body['access_token'] ?? null;
        $expiresAt = $body['expires_at'] ?? null;
        if (!is_string($token) || $token === '' || !is_numeric($expiresAt)) throw new \RuntimeException('GIGACHAT_OAUTH_INVALID');
        $this->expiresAt = (int) floor((float)$expiresAt / 1000);
        if ($this->expiresAt <= time() + 60) throw new \RuntimeException('GIGACHAT_OAUTH_EXPIRED');
        return $this->accessToken = $token;
    }

    public function json(string $instruction, array $payload): array
    {
        if (!$this->configured()) throw new \RuntimeException('GIGACHAT_NOT_CONFIGURED');
        $operation = $payload['operation'] ?? null;
        if (!in_array($operation,['generate_task','evaluate_task'],true)) throw new \InvalidArgumentException('Unknown assessment operation');
        $deadline = microtime(true) + 100;
        for ($try=0; $try<2; ++$try) {
            $token = $this->token();
            $remaining = min(90.0, $deadline - microtime(true));
            if ($remaining <= 0) throw new \RuntimeException('GIGACHAT_TIMEOUT');
            $response = $this->http->request('POST','https://api.giga.chat/v1/chat/completions', $this->tls() + [
                'headers'=>['Authorization'=>'Bearer '.$token, 'Accept'=>'application/json'],
                'json'=>['model'=>$this->model, 'stream'=>false, 'max_tokens'=>4000,
                    'messages'=>[['role'=>'system','content'=>$instruction.' Для аргументов функции reason и title всегда строки: при compatible=true reason пустая строка; при compatible=false title пустая строка и assignment={"text":"","deliverables":[],"constraints":[]}.'],
                        ['role'=>'user','content'=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]],
                    'function_call'=>['name'=>$operation],
                    'functions'=>[['name'=>$operation,'description'=>'Вернуть структурированный результат задания или оценки; никаких действий не выполняется.',
                        'parameters'=>$this->schema($operation,$payload)]]],
                'timeout'=>$remaining, 'max_duration'=>$remaining,
            ]);
            if ($response->getStatusCode() === 401 && $try === 0) {
                $this->accessToken = null; $this->expiresAt = 0;
                $response->cancel();
                continue;
            }
            if ($response->getStatusCode() !== 200) {
                $status = $response->getStatusCode();
                try { $response->toArray(false); } catch (\Throwable) {}
                throw new \RuntimeException('GIGACHAT_HTTP_'.$status);
            }
            $body = $response->toArray(false);
            $choice = $body['choices'][0] ?? [];
            if (($choice['finish_reason'] ?? null) === 'blacklist') throw new \RuntimeException('GIGACHAT_REFUSAL');
            if (($choice['finish_reason'] ?? null) !== 'function_call') throw new \UnexpectedValueException('Expected structured function result');
            $call = $choice['message']['function_call'] ?? [];
            if (($call['name'] ?? null) !== $operation) throw new \UnexpectedValueException('Unexpected result function');
            $result = $call['arguments'] ?? null;
            if (!is_array($result) || array_is_list($result)) throw new \UnexpectedValueException('Expected JSON object');
            if (strlen(json_encode($result,JSON_THROW_ON_ERROR)) > 100000) throw new \UnexpectedValueException('Oversized response');
            // This is only a structured result envelope: no function/tool/code is executed.
            return $result;
        }
        throw new \LogicException('Unreachable authentication retry');
    }

    private function schema(string $operation, array $payload): array
    {
        $string=['type'=>'string'];$integer=['type'=>'integer'];
        $checkId=['type'=>'string','pattern'=>'^[a-zA-Z0-9_-]{1,80}$'];
        if($operation==='generate_task')return $this->object([
            'compatible'=>['type'=>'boolean'],'reason'=>$string,'title'=>$string,
            'assignment'=>$this->object(['text'=>$string,'deliverables'=>$this->array($string),'constraints'=>$this->array($string)]),
            'required_technology_codes'=>$this->array($string),
            'criteria_checks'=>$this->array($this->object(['competency_code'=>['type'=>'string','enum'=>array_values(array_unique(array_column($payload['selected_pairs'],'competency_code')))],
                'criterion_code'=>['type'=>'string','enum'=>array_column($payload['selected_pairs'],'criterion_code')],'criterion_version'=>$integer,
                'checks'=>['minItems'=>2,'maxItems'=>2]+$this->array($this->object(['check_id'=>$checkId,'condition'=>$string,'expected_evidence'=>$this->array($string)]))])),
        ]);
        return $this->object(['summary'=>$string,'criteria'=>$this->array($this->object([
            'criterion_code'=>['type'=>'string','enum'=>array_column($payload['criteria_snapshot'],'code')],'criterion_version'=>$integer,
            'checks'=>['minItems'=>2,'maxItems'=>2]+$this->array($this->object(['check_id'=>$checkId,'status'=>['type'=>'string','enum'=>['passed','failed','unknown']],
                'explanation'=>$string,'evidence'=>$string])),
        ]))]);
    }
    private function object(array $properties): array
    {
        $object = ['type'=>'object','properties'=>$properties,'required'=>array_keys($properties),'additionalProperties'=>false];
        return $object;
    }
    private function array(array $items): array { return ['type'=>'array','items'=>$items,'maxItems'=>20]; }
}
