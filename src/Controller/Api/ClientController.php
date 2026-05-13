<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/client', name: 'app_api_client_')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ClientRepository $repo): JsonResponse
    {
        $clients = $repo->findAll();
        $data = array_map(fn($c) => [
            'id'             => $c->getId(),
            'nom'            => $c->getNom(),
            'cin'            => $c->getCin(),
            'passeport'      => $c->getPasseport(),
            'permisConduite' => $c->getPermisConduite(),
            'nationalite'    => $c->getNationalite(),
            'telephone'      => $c->getTelephone(),
            'creeAu'         => $c->getCreeAu()?->format('Y-m-d H:i:s'),
        ], $clients);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Client $client): JsonResponse
    {
        return $this->json([
            'id'             => $client->getId(),
            'nom'            => $client->getNom(),
            'cin'            => $client->getCin(),
            'passeport'      => $client->getPasseport(),
            'permisConduite' => $client->getPermisConduite(),
            'nationalite'    => $client->getNationalite(),
            'telephone'      => $client->getTelephone(),
            'creeAu'         => $client->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu'         => $client->getEditAu()?->format('Y-m-d H:i:s'),
            'creePar'        => $client->getCreePar()?->getId(),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $client = new Client();
        $client->setNom($data['nom']);
        $client->setCin($data['cin'] ?? null);
        $client->setPasseport($data['passeport'] ?? null);
        $client->setPermisConduite($data['permisConduite'] ?? null);
        $client->setNationalite($data['nationalite'] ?? null);
        $client->setTelephone($data['telephone'] ?? null);
        $client->setCreeAu(new \DateTime());
        $client->setCreePar($this->getUser());

        $em->persist($client);
        $em->flush();

        return $this->json(['message' => 'Client créé', 'id' => $client->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Client $client, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['nom']))            $client->setNom($data['nom']);
        if (isset($data['cin']))            $client->setCin($data['cin']);
        if (isset($data['passeport']))      $client->setPasseport($data['passeport']);
        if (isset($data['permisConduite'])) $client->setPermisConduite($data['permisConduite']);
        if (isset($data['nationalite']))    $client->setNationalite($data['nationalite']);
        if (isset($data['telephone']))      $client->setTelephone($data['telephone']);
        $client->setEditAu(new \DateTime());

        $em->flush();

        return $this->json(['message' => 'Client mis à jour']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Client $client, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($client);
        $em->flush();

        return $this->json(['message' => 'Client supprimé'], 204);
    }
}
