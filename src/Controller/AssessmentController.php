<?php
declare(strict_types=1);
namespace App\Controller;
use App\Application\Workspace\{AssessmentService,WorkspaceService};
use App\Infrastructure\Security\JwtAuthenticator;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
final class AssessmentController
{
    public function __construct(private JwtAuthenticator $auth,private WorkspaceService $workspace,private AssessmentService $assessment) {}
    #[Route('/assessment/tests',methods:['POST'])]
    public function create(Request $request): JsonResponse
    {
        $identity=$this->workspace->identity($this->auth->authenticate($request));
        return $this->response($this->assessment->create($identity,$request->toArray(),substr((string)$request->headers->get('Authorization'),7)));
    }
    #[Route('/assessment/tests/{id}',requirements:['id'=>'[1-9][0-9]*'],methods:['GET'])]
    #[Route('/assessment/tests/{id}/{action}',requirements:['id'=>'[1-9][0-9]*','action'=>'answer|submit|retry-generation|retry-evaluation'],methods:['POST'])]
    public function task(Request $request,int $id,?string $action=null): JsonResponse
    {
        $identity=$this->workspace->identity($this->auth->authenticate($request));
        $data=match($action){
            null=>$this->assessment->get($identity,$id),
            'answer','submit'=>$this->assessment->answer($identity,$id,$request->toArray(),$action==='submit'),
            'retry-generation','retry-evaluation'=>$this->assessment->retry($identity,$id,$action==='retry-generation'?'generate':'evaluate'),
        };
        return $this->response($data);
    }
    private function response(array $data): JsonResponse { return new JsonResponse(['success'=>true,'data'=>$data],200,['Cache-Control'=>'private, no-store']); }
}
