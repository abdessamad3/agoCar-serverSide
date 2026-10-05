<?php

namespace App\Controller\Api;

use App\Entity\ConditionsContrat;
use App\Repository\ConditionsContratRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/conditions-contrat', name: 'app_api_conditions_contrat_')]
class ConditionsContratController extends AbstractController
{
    private function getOrCreate(ConditionsContratRepository $repo, EntityManagerInterface $em): ConditionsContrat
    {
        $cc = $repo->find(1);
        if (!$cc) {
            $cc = new ConditionsContrat();
            $cc->setId(1);
            $em->persist($cc);
            $em->flush();
        }
        return $cc;
    }

    private function serialize(ConditionsContrat $cc): array
    {
        return [
            'texteFrancais' => $cc->getTexteFrancais(),
            'texteArabe'    => $cc->getTexteArabe(),
            'updatedAt'     => $cc->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(ConditionsContratRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        return $this->json($this->serialize($this->getOrCreate($repo, $em)));
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(Request $request, ConditionsContratRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $cc   = $this->getOrCreate($repo, $em);
        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('texteFrancais', $data)) $cc->setTexteFrancais($data['texteFrancais']);
        if (array_key_exists('texteArabe', $data))    $cc->setTexteArabe($data['texteArabe']);
        $cc->setUpdatedAt(new \DateTimeImmutable());

        $em->flush();
        return $this->json($this->serialize($cc));
    }
}
