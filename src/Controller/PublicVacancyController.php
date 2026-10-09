<?php

declare(strict_types=1);
namespace App\Controller;
use App\Application\Workspace\PublicVacancyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
final class PublicVacancyController
{
    public function __construct(private PublicVacancyService $vacancies) {}
    #[Route('/public/vacancies', methods:['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['success'=>true,'data'=>$this->vacancies->list()],200,['Cache-Control'=>'no-store']);
    }
    #[Route('/public/vacancies/{id}', methods:['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse(['success'=>true,'data'=>$this->vacancies->get($id)],200,['Cache-Control'=>'no-store']);
    }
}
