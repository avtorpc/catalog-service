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
 #[Route('/resumes',methods:['GET','POST'])]
 #[Route('/resumes/{id}/pdf',methods:['POST'])]
 public function __invoke(Request $request):JsonResponse {
  $identity=$this->workspace->identity($this->auth->authenticate($request));$token=substr((string)$request->headers->get('Authorization'),7);
  $path=$request->getPathInfo();
  $data=match($path){
   '/workspace'=>$this->workspace->dashboard($identity,$token,$request->query->getInt('testsPage',1)),
   '/profile'=>$this->workspace->saveProfile($identity,$request->toArray(),$token),
   '/assessment/preferences'=>$this->workspace->savePreferences($identity,$request->toArray(),$token),
   '/assessment/options'=>$this->workspace->options($identity,$request->query->has('specializationId')?$request->query->getInt('specializationId'):null),
   '/resumes'=>$request->isMethod('POST')?$this->workspace->createResume($identity,$request->toArray()):$this->workspace->resumes($identity),
   default=>str_ends_with($path,'/pdf')?$this->workspace->attachResumePdf($identity,(string)$request->attributes->get('id'),$request->files->get('file'),new \App\Application\Workspace\ResumePdfStorage($_ENV['CATALOG_UPLOAD_DIR'])):[],
  };
 return new JsonResponse(['success'=>true,'data'=>$data],200,['Cache-Control'=>'private, no-store']);
 }
 #[Route('/resumes/{id}/pdf',methods:['GET'])]
 public function pdf(Request $request):JsonResponse { $identity=$this->workspace->identity($this->auth->authenticate($request));$baseDir=(string)($_ENV['CATALOG_UPLOAD_DIR']??$_SERVER['CATALOG_UPLOAD_DIR']??getenv('CATALOG_UPLOAD_DIR')??'');$file=$this->workspace->resumePdf($identity,(string)$request->attributes->get('id'),$baseDir);return new JsonResponse(['success'=>true,'data'=>['name'=>$file['name'],'mime'=>$file['mime'],'content'=>base64_encode((string)file_get_contents($file['path']))]],200,['Cache-Control'=>'private, no-store']); }
}
