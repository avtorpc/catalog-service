<?php

namespace App\Controller;

use App\Infrastructure\Application\ApplicationInfo;
use App\Infrastructure\Security\JwtAuthenticator;
use App\Shared\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private ApplicationInfo $app,
        private ClockInterface $clock,
        private JwtAuthenticator $jwtAuthenticator
    ) {}

    #[Route("/", name: "app_api", methods: ["GET"])]
    public function index(Request $request): JsonResponse
    {
      //  $this->jwtAuthenticator->authenticate($request);

        $params = $request->query->all(); // ВСЕ GET параметры

        $foo = $request->query->get('foo'); // конкретный параметр

        return $this->json([
            'service' => $this->app->name(),
            'version' => $this->app->version(),
            'status' => 'ok',
            'timestamp' => $this->clock->nowFormatted(),
            'query' => $params
        ]);
    }
}
