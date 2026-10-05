<?php

namespace App\Controller\Api;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/company', name: 'app_api_company_')]
class CompanyController extends AbstractController
{
    public function __construct(private KernelInterface $kernel) {}

    private function serialize(Company $c): array
    {
        return [
            'id'      => $c->getId(),
            'nom'     => $c->getNom(),
            'logo'    => $c->getLogo(),
            'manager' => $c->getManager() ? [
                'id'     => $c->getManager()->getId(),
                'nom'    => $c->getManager()->getNom(),
                'prenom' => $c->getManager()->getPrenom(),
                'email'  => $c->getManager()->getEmail(),
            ] : null,
            'staff' => array_map(fn($u) => [
                'id'     => $u->getId(),
                'nom'    => $u->getNom(),
                'prenom' => $u->getPrenom(),
                'email'  => $u->getEmail(),
            ], $c->getStaff()->toArray()),
            'creeAu' => $c->getCreeAu()?->format('Y-m-d H:i:s'),
            'editAu' => $c->getEditAu()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(CompanyRepository $repo, Request $request): JsonResponse
    {
        $page  = max(1, (int) ($request->query->get('page', 1)));
        $limit = min(100, max(1, (int) ($request->query->get('limit', 20))));

        $user    = $this->getUser();
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles());

        $all = $isAdmin
            ? $repo->findBy([], ['creeAu' => 'DESC'])
            : $repo->findByUser($user);

        $total = count($all);
        $paged = array_slice($all, ($page - 1) * $limit, $limit);

        return $this->json([
            'data' => array_map(fn($c) => $this->serialize($c), $paged),
            'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Company $company): JsonResponse
    {
        return $this->json($this->serialize($company));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom'])) {
            return $this->json(['error' => 'nom is required'], 400);
        }
        if (empty($data['managerId'])) {
            return $this->json(['error' => 'managerId is required'], 400);
        }

        $manager = $userRepo->find($data['managerId']);
        if (!$manager) {
            return $this->json(['error' => 'Manager not found'], 404);
        }

        $company = new Company();
        $company->setNom($data['nom']);
        $company->setLogo($data['logo'] ?? null);
        $company->setManager($manager);
        $company->setCreeAu(new \DateTimeImmutable());

        foreach ($data['staffIds'] ?? [] as $uid) {
            $u = $userRepo->find($uid);
            if ($u) $company->addStaff($u);
        }

        $em->persist($company);
        $em->flush();

        return $this->json(['message' => 'Company créée', 'id' => $company->getId()], 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Company $company,
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!empty($data['nom'])) {
            $company->setNom($data['nom']);
        }
        if (array_key_exists('logo', $data)) {
            $company->setLogo($data['logo'] ?: null);
        }
        if (!empty($data['managerId'])) {
            $manager = $userRepo->find($data['managerId']);
            if ($manager) $company->setManager($manager);
        }
        if (array_key_exists('staffIds', $data)) {
            foreach ($company->getStaff()->toArray() as $u) {
                $company->removeStaff($u);
            }
            foreach ($data['staffIds'] as $uid) {
                $u = $userRepo->find($uid);
                if ($u) $company->addStaff($u);
            }
        }

        $company->setEditAu(new \DateTimeImmutable());
        $em->flush();

        return $this->json($this->serialize($company));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Company $company, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($company);
        $em->flush();
        return $this->json(['message' => 'Company supprimée']);
    }

    #[Route('/{id}/logo', name: 'upload_logo', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function uploadLogo(
        Company $company,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $file = $request->files->get('logoFile');
        if (!$file) return $this->json(['error' => 'No file uploaded'], 400);

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed)) return $this->json(['error' => 'Type non autorisé'], 415);
        if ($file->getSize() > 2 * 1024 * 1024) return $this->json(['error' => 'Fichier trop grand (max 2MB)'], 413);

        $filename = uniqid('company_') . '.' . ($file->guessExtension() ?? 'png');
        $destDir  = $this->kernel->getProjectDir() . '/public/uploads/logos';

        $old = $company->getLogo();
        if ($old) { $oldPath = $this->kernel->getProjectDir() . '/public' . $old; if (file_exists($oldPath)) @unlink($oldPath); }

        $file->move($destDir, $filename);
        $company->setLogo('/uploads/logos/' . $filename);
        $em->flush();

        return $this->json(['logo' => $company->getLogo()]);
    }
}
