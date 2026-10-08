<?php

declare(strict_types=1);
namespace App\Infrastructure\Workspace;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
final class ReferenceCatalog
{
 private array $cache=[];
 public function __construct(private HttpClientInterface $http, #[Autowire('%env(DICTIONARY_SERVICE_URL)%')] private string $url) {}
 public function items(string $name):array {
  if(isset($this->cache[$name]))return $this->cache[$name];
  try{
   $response=$this->http->request('GET',rtrim($this->url,'/').'/'.$name,['timeout'=>3,'max_duration'=>5,'max_redirects'=>0]);
   $data=$response->toArray();
   if(($data['success']??false)!==true||!is_array($data['data']['items']??null))throw new \RuntimeException();
   return $this->cache[$name]=$data['data']['items'];
  }catch(\Throwable){throw new ServiceUnavailableHttpException(5,'Справочники временно недоступны. Повторите запрос позже.');}
 }
}
