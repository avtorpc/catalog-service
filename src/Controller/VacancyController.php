<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Workspace\{VacancyService, WorkspaceService};
use App\Infrastructure\Security\JwtAuthenticator;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

final class VacancyController
{
    public function __construct(private JwtAuthenticator $auth, private WorkspaceService $workspace, private VacancyService $vacancies) {}

    #[Route('/vacancies', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        $identity = $this->workspace->identity($this->auth->authenticate($request));
        $token = substr((string)$request->headers->get('Authorization'), 7);
        return new JsonResponse(['success'=>true, 'data'=>$this->vacancies->save($identity, $request->toArray(), $token)], 200, ['Cache-Control'=>'private, no-store']);
    }

    #[Route('/vacancies/{id}', methods: ['GET'])]
    public function get(Request $request, string $id): JsonResponse
    {
        $identity = $this->workspace->identity($this->auth->authenticate($request));
        return new JsonResponse(['success'=>true, 'data'=>$this->vacancies->get($identity, $id)], 200, ['Cache-Control'=>'private, no-store']);
    }
    #[Route('/vacancies/{id}/{action}', requirements: ['action'=>'publish|unpublish'], methods: ['POST'])]
    public function publication(Request $request, string $id, string $action): JsonResponse
    {
        $identity = $this->workspace->identity($this->auth->authenticate($request));
        return new JsonResponse(['success'=>true, 'data'=>$this->vacancies->publication($identity, $id, $request->toArray(), $action === 'publish')], 200, ['Cache-Control'=>'private, no-store']);
    }
}
