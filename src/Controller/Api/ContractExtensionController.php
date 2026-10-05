<?php

namespace App\Controller\Api;

use App\Entity\ContractExtension;
use App\Repository\ContratRepository;
use App\Repository\ContractExtensionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/contract-extension', name: 'app_api_ce_')]
class ContractExtensionController extends AbstractController
{
    private function serialize(ContractExtension $e): array
    {
        return [
            'id'        => $e->getId(),
            'contratId' => $e->getContrat()?->getId(),
            'dateFrom'  => $e->getDateFrom()?->format('Y-m-d'),
            'dateTo'    => $e->getDateTo()?->format('Y-m-d'),
            'nbJours'   => $e->getNbJours(),
            'notes'     => $e->getNotes(),
            'creeAu'    => $e->getCreeAu()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, ContractExtensionRepository $repo): JsonResponse
    {
        $contratId = $request->query->get('contratId');
        $criteria  = $contratId ? ['contrat' => (int) $contratId] : [];
        return $this->json(array_map(
            fn($e) => $this->serialize($e),
            $repo->findBy($criteria, ['dateFrom' => 'ASC'])
        ));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ContractExtension $extension): JsonResponse
    {
        return $this->json($this->serialize($extension));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ContratRepository $contratRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['contratId'])) {
            return $this->json(['error' => 'contratId is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $contrat = $contratRepo->find((int) $data['contratId']);
        if (!$contrat) {
            return $this->json(['error' => 'Contrat not found'], Response::HTTP_NOT_FOUND);
        }
        if (empty($data['dateFrom']) || empty($data['dateTo'])) {
            return $this->json(['error' => 'dateFrom and dateTo are required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ext = new ContractExtension();
        $ext->setContrat($contrat);
        $ext->setDateFrom(new \DateTimeImmutable($data['dateFrom']));
        $ext->setDateTo(new \DateTimeImmutable($data['dateTo']));
        $ext->setNotes($data['notes'] ?? null);

        $em->persist($ext);
        $em->flush();

        return $this->json($this->serialize($ext), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(ContractExtension $extension, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['dateFrom'])) $extension->setDateFrom(new \DateTimeImmutable($data['dateFrom']));
        if (isset($data['dateTo']))   $extension->setDateTo(new \DateTimeImmutable($data['dateTo']));
        if (array_key_exists('notes', $data)) $extension->setNotes($data['notes']);

        $em->flush();
        return $this->json($this->serialize($extension));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(ContractExtension $extension, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($extension);
        $em->flush();
        return $this->json(['message' => 'Extension supprimée']);
    }
}
