<?php

namespace App\Controller\Api;

use App\Entity\VehicleCredit;
use App\Entity\VehicleCreditDocument;
use App\Repository\VehicleCreditRepository;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicle-credit-document', name: 'app_api_vc_document_')]
class VehicleCreditDocumentController extends AbstractController
{
    use BureauAwareTrait;

    /** Bureau-locked staff/managers may only touch credit documents belonging to
     *  their own bureau. True admins (getEffectiveBureauId() === null) are unrestricted. */
    private function assertBureauAccess(VehicleCredit $credit): void
    {
        $bureauId = $this->getEffectiveBureauId();
        if ($bureauId === null) return;

        if ($credit->getVoiture()?->getBureau()?->getId() !== $bureauId) {
            throw $this->createNotFoundException('Contrat introuvable');
        }
    }

    private function serialize(VehicleCreditDocument $d): array
    {
        return [
            'id'             => $d->getId(),
            'vehicleCreditId'=> $d->getVehicleCredit()?->getId(),
            'documentType'   => $d->getDocumentType(),
            'filePath'       => $d->getFilePath(),
            'fileName'       => $d->getFileName(),
            'notes'          => $d->getNotes(),
            'uploadedAt'     => $d->getUploadedAt()?->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $creditId = $request->query->get('vehicleCreditId');
        $qb = $em->createQueryBuilder()->select('d')->from(VehicleCreditDocument::class, 'd')
            ->orderBy('d.uploadedAt', 'DESC');
        if ($creditId) {
            $qb->where('d.vehicleCredit = :cid')->setParameter('cid', (int) $creditId);
        }
        return $this->json(array_map(fn($d) => $this->serialize($d), $qb->getQuery()->getResult()));
    }

    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $em,
        VehicleCreditRepository $creditRepo
    ): JsonResponse {
        $credit = $creditRepo->find($request->request->get('vehicleCreditId') ?? 0);
        if (!$credit) return $this->json(['error' => 'Contrat introuvable'], 404);
        $this->assertBureauAccess($credit);

        $file = $request->files->get('file');
        if (!$file) return $this->json(['error' => 'Aucun fichier'], 400);

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/credit-documents/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

        $ext      = $file->guessExtension() ?? 'bin';
        $fileName = uniqid('doc_') . '.' . $ext;
        $file->move($uploadDir, $fileName);

        $doc = new VehicleCreditDocument();
        $doc->setVehicleCredit($credit);
        $doc->setDocumentType($request->request->get('documentType', 'other'));
        $doc->setFilePath('/uploads/credit-documents/' . $fileName);
        $doc->setFileName($file->getClientOriginalName());
        $doc->setNotes($request->request->get('notes'));

        $em->persist($doc);
        $em->flush();

        return $this->json($this->serialize($doc), 201);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(VehicleCreditDocument $doc, EntityManagerInterface $em): JsonResponse
    {
        if ($doc->getVehicleCredit()) $this->assertBureauAccess($doc->getVehicleCredit());
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $doc->getFilePath();
        if (file_exists($filePath)) unlink($filePath);

        $em->remove($doc);
        $em->flush();

        return $this->json(['message' => 'Document supprimé']);
    }
}
