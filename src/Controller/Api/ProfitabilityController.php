<?php

namespace App\Controller\Api;

use App\Service\ProfitabilityService;
use App\Trait\BureauAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/profitability', name: 'app_api_profitability_')]
class ProfitabilityController extends AbstractController
{
    use BureauAwareTrait;

    public function __construct(private ProfitabilityService $profitabilityService) {}

    #[Route('/vehicles', name: 'vehicles', methods: ['GET'])]
    public function vehicles(Request $request): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $yearRaw  = $request->query->get('year', (string) date('Y'));
        $year     = $yearRaw === 'all' ? null : max(2000, (int) $yearRaw);
        $carId    = $request->query->has('carId') ? (int) $request->query->get('carId') : null;

        return $this->json($this->profitabilityService->getVehicleProfitability($bureauId ?: null, $year, $carId));
    }

    #[Route('/bureaux', name: 'bureaux', methods: ['GET'])]
    public function bureaux(Request $request): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $year     = max(2000, (int) $request->query->get('year', (int) date('Y')));

        return $this->json($this->profitabilityService->getBureauProfitability($bureauId ?: null, $year));
    }
}
