<?php

namespace App\Controller\Agent;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AgentPictureController extends AbstractController
{
    #[IsGranted('ROLE_AGENT')]
    #[Route('/api/agents/upload-picture', name: 'agent_upload_picture', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('picture');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        if (
            !in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])
            || $file->getSize() > 2 * 1024 * 1024
        ) {
            return $this->json(['error' => 'Invalid file type or size'], 400);
        }

        $filename = bin2hex(random_bytes(16)).'.'.$file->guessExtension();
        $file->move($this->getParameter('agent_pictures_directory'), $filename);

        $url = '/uploads/agents/' . $filename;

        return $this->json([
            'status' => 'success',
            'message' => 'Image uploadée avec succès',
            'url' => $url
        ]);
    }
}
