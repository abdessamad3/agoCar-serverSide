<?php

namespace App\Controller\Api;

use App\Entity\FinancialInstitution;
use App\Repository\FinancialInstitutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Trait\PaginationTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/financial-institution', name: 'app_api_financial_institution_')]
class FinancialInstitutionController extends AbstractController
{
    use PaginationTrait;

    private function serialize(FinancialInstitution $fi): array
    {
        return [
            'id'            => $fi->getId(),
            'name'          => $fi->getName(),
            'type'          => $fi->getType(),
            'contactPerson' => $fi->getContactPerson(),
            'phone'         => $fi->getPhone(),
            'email'         => $fi->getEmail(),
            'address'       => $fi->getAddress(),
            'notes'         => $fi->getNotes(),
            'status'        => $fi->getStatus(),
            'createdAt'     => $fi->getCreatedAt()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(FinancialInstitutionRepository $repo, Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $page   = $this->getPageParam($request);

        $qb = $repo->createQueryBuilder('fi')
            ->orderBy('fi.name', 'ASC');

        if ($status) {
            $qb->where('fi.status = :status')->setParameter('status', $status);
        }

        [$items, $total] = $this->paginateQb($qb, $page);
        return $this->json(['data' => array_map(fn($fi) => $this->serialize($fi), $items), 'meta' => $this->paginateMeta($total, $page)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(FinancialInstitution $fi): JsonResponse
    {
        return $this->json($this->serialize($fi));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $fi = new FinancialInstitution();
        $fi->setName($data['name']);
        $fi->setType($data['type'] ?? 'bank');
        $fi->setContactPerson($data['contactPerson'] ?? null);
        $fi->setPhone($data['phone'] ?? null);
        $fi->setEmail($data['email'] ?? null);
        $fi->setAddress($data['address'] ?? null);
        $fi->setNotes($data['notes'] ?? null);
        $fi->setStatus($data['status'] ?? 'active');

        $em->persist($fi);
        $em->flush();

        return $this->json(['message' => 'Institution créée', 'id' => $fi->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(FinancialInstitution $fi, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name']))          $fi->setName($data['name']);
        if (isset($data['type']))          $fi->setType($data['type']);
        if (array_key_exists('contactPerson', $data)) $fi->setContactPerson($data['contactPerson']);
        if (array_key_exists('phone', $data))         $fi->setPhone($data['phone']);
        if (array_key_exists('email', $data))         $fi->setEmail($data['email']);
        if (array_key_exists('address', $data))       $fi->setAddress($data['address']);
        if (array_key_exists('notes', $data))         $fi->setNotes($data['notes']);
        if (isset($data['status']))        $fi->setStatus($data['status']);

        $em->flush();

        return $this->json(['message' => 'Institution mise à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(FinancialInstitution $fi, EntityManagerInterface $em): JsonResponse
    {
        $fi->setStatus('inactive');
        $em->flush();
        return $this->json(['message' => 'Institution désactivée']);
    }
}
