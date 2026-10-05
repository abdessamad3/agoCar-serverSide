<?php

namespace App\Controller\Api;

use App\Entity\ParametresSociete;
use App\Repository\ParametresSocieteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/parametres-societe', name: 'app_api_parametres_societe_')]
class ParametresSocieteController extends AbstractController
{
    private function getOrCreate(ParametresSocieteRepository $repo, EntityManagerInterface $em): ParametresSociete
    {
        $ps = $repo->find(1);
        if (!$ps) {
            $ps = new ParametresSociete();
            $ps->setId(1);
            $em->persist($ps);
            $em->flush();
        }
        return $ps;
    }

    private function serialize(ParametresSociete $ps): array
    {
        return [
            'raisonSociale' => $ps->getRaisonSociale(),
            'telephones'    => $ps->getTelephones(),
            'adresse'       => $ps->getAdresse(),
            'rc'            => $ps->getRc(),
            'ice'           => $ps->getIce(),
            'ifFiscal'      => $ps->getIfFiscal(),
            'cnss'          => $ps->getCnss(),
            'logoPath'      => $ps->getLogoPath(),
            'email'         => $ps->getEmail(),
            'website'       => $ps->getWebsite(),
            'whatsapp'      => $ps->getWhatsapp(),
        ];
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(ParametresSocieteRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        return $this->json($this->serialize($this->getOrCreate($repo, $em)));
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(Request $request, ParametresSocieteRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ps   = $this->getOrCreate($repo, $em);
        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('raisonSociale', $data)) $ps->setRaisonSociale($data['raisonSociale']);
        if (array_key_exists('telephones', $data))    $ps->setTelephones(is_array($data['telephones']) ? $data['telephones'] : []);
        if (array_key_exists('adresse', $data))       $ps->setAdresse($data['adresse']);
        if (array_key_exists('rc', $data))            $ps->setRc($data['rc']);
        if (array_key_exists('ice', $data))           $ps->setIce($data['ice']);
        if (array_key_exists('ifFiscal', $data))      $ps->setIfFiscal($data['ifFiscal']);
        if (array_key_exists('cnss', $data))          $ps->setCnss($data['cnss']);
        if (array_key_exists('email', $data))         $ps->setEmail($data['email']);
        if (array_key_exists('website', $data))       $ps->setWebsite($data['website']);
        if (array_key_exists('whatsapp', $data))      $ps->setWhatsapp($data['whatsapp']);

        $em->flush();
        return $this->json($this->serialize($ps));
    }

    #[Route('/logo', name: 'upload_logo', methods: ['POST'])]
    public function uploadLogo(Request $request, ParametresSocieteRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $file = $request->files->get('logo');
        if (!$file) {
            return $this->json(['error' => 'No file provided'], Response::HTTP_BAD_REQUEST);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        if (!in_array($file->getMimeType(), $allowed)) {
            return $this->json(['error' => 'Type non autorisé'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $ext      = $file->guessExtension() ?? 'png';
        $filename = 'logo.' . $ext;
        $dir      = $this->getParameter('kernel.project_dir') . '/public/uploads/logo';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $file->move($dir, $filename);

        $logoPath = '/uploads/logo/' . $filename;
        $ps = $this->getOrCreate($repo, $em);
        $ps->setLogoPath($logoPath);
        $em->flush();

        return $this->json(['logoPath' => $logoPath]);
    }
}
