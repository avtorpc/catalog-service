<?php

declare(strict_types=1);
namespace App\Controller;
use App\Application\Workspace\WorkspaceService;
use App\Infrastructure\Security\JwtAuthenticator;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
final class WorkspaceController
{
 public function __construct(private JwtAuthenticator $auth,private WorkspaceService $workspace) {}
 #[Route('/workspace',methods:['GET'])]
 #[Route('/profile',methods:['PATCH'])]
 #[Route('/assessment/options',methods:['GET'])]
 #[Route('/assessment/preferences',methods:['POST'])]
 public function __invoke(Request $request):JsonResponse {
  $identity=$this->workspace->identity($this->auth->authenticate($request));$token=substr((string)$request->headers->get('Authorization'),7);
  $path=$request->getPathInfo();
  $data=match($path){
   '/workspace'=>$this->workspace->dashboard($identity,$token,$request->query->getInt('testsPage',1),$request->query->getInt('vacanciesPage',1)),
   '/profile'=>$this->workspace->saveProfile($identity,$request->toArray(),$token),
   '/assessment/preferences'=>$this->workspace->savePreferences($identity,$request->toArray(),$token),
   '/assessment/options'=>$this->workspace->options($identity,$request->query->has('specializationId')?$request->query->getInt('specializationId'):null),
  };
  return new JsonResponse(['success'=>true,'data'=>$data],200,['Cache-Control'=>'private, no-store']);
 }
}
