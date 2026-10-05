<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/document', name: 'app_api_document_')]
class DocumentController extends AbstractController
{
    private const ALLOWED_TYPES = ['insurance', 'vignette', 'technical-inspection', 'vidange', 'reparation', 'adblue'];
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
    ];
    private const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file     = $request->files->get('file');
        $type     = $request->request->get('type');
        $entityId = $request->request->get('entityId');

        if (!$file) {
            return $this->json(['error' => 'No file provided'], 400);
        }

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->json(['error' => 'Invalid document type. Allowed: ' . implode(', ', self::ALLOWED_TYPES)], 400);
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            return $this->json(['error' => 'File type not allowed. Use JPEG, PNG, WebP, GIF or PDF.'], 400);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            return $this->json(['error' => 'File exceeds the 10 MB limit.'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $type;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext      = $file->guessExtension() ?? 'bin';
        $filename = uniqid($type . '_' . ($entityId ?? '0') . '_', true) . '.' . $ext;

        $file->move($uploadDir, $filename);

        return $this->json([
            'message'  => 'File uploaded successfully',
            'path'     => '/uploads/' . $type . '/' . $filename,
            'filename' => $filename,
            'type'     => $type,
            'entityId' => $entityId,
        ], 201);
    }
}
