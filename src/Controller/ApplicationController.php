<?php

declare(strict_types=1);
namespace App\Controller;
use App\Application\Workspace\{ApplicationService,WorkspaceService};
use App\Infrastructure\Security\JwtAuthenticator;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
final class ApplicationController
{
 public function __construct(private JwtAuthenticator $auth,private WorkspaceService $workspace,private ApplicationService $apps){}
 private function who(Request $r):array{return $this->workspace->identity($this->auth->authenticate($r));}
 #[Route('/applications',methods:['POST'])]
 public function create(Request $r):JsonResponse{return $this->result($this->apps->create($this->who($r),$r->toArray(),substr((string)$r->headers->get('Authorization'),7)));}
 #[Route('/applications/start-chat',methods:['POST'])]
 public function startChat(Request $r):JsonResponse{return $this->result($this->apps->startChat($this->who($r),$r->toArray(),substr((string)$r->headers->get('Authorization'),7)));}
 #[Route('/applications',methods:['GET'])]
 public function list(Request $r):JsonResponse{return $this->result($this->apps->list($this->who($r),$r->query->getInt('page',1)));}
 #[Route('/applications/{id}/chat-context',methods:['GET'])]
 public function context(Request $r,string $id):JsonResponse{return $this->result($this->apps->context($this->who($r),$id));}
 #[Route('/applications/{id}/review',methods:['POST'])]
 public function review(Request $r,string $id):JsonResponse{return $this->result($this->apps->review($this->who($r),$id));}
 #[Route('/applications/{id}',methods:['PATCH'])]
 public function update(Request $r,string $id):JsonResponse{return $this->result($this->apps->update($this->who($r),$id,$r->toArray()));}
 private function result(array $data):JsonResponse{return new JsonResponse(['success'=>true,'data'=>$data],200,['Cache-Control'=>'private, no-store']);}
}
