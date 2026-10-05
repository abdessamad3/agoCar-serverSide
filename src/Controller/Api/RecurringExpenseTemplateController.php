<?php

namespace App\Controller\Api;

use App\Entity\RecurringExpenseTemplate;
use App\Repository\BureauRepository;
use App\Repository\RecurringExpenseTemplateRepository;
use App\Trait\BureauAwareTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/recurring-expense-template', name: 'app_api_recurring_template_')]
#[IsGranted('ROLE_USER')]
class RecurringExpenseTemplateController extends AbstractController
{
    use BureauAwareTrait;

    private const REPETITIVE_TYPES = ['loyer', 'salaire', 'telephone', 'electricite', 'eau', 'internet', 'vignette'];

    private function serialize(RecurringExpenseTemplate $t): array
    {
        return [
            'id'               => $t->getId(),
            'bureauId'         => $t->getBureau()?->getId(),
            'bureauNom'        => $t->getBureau()?->getNom(),
            'typeDepense'      => $t->getTypeDepense(),
            'frequency'        => $t->getFrequency(),
            'priceType'        => $t->getPriceType(),
            'fixedAmount'      => $t->getFixedAmount(),
            'startPeriodMonth' => $t->getStartPeriodMonth(),
            'startPeriodYear'  => $t->getStartPeriodYear(),
            'isActive'         => $t->isActive(),
            'createdAt'        => $t->getCreatedAt()->format('Y-m-d'),
        ];
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(RecurringExpenseTemplateRepository $repo): JsonResponse
    {
        $bureauId = $this->getEffectiveBureauId();
        $all = $repo->findAll();

        $filtered = array_filter($all, fn($t) =>
            $bureauId === null || $t->getBureau()?->getId() === $bureauId
        );

        return $this->json(array_values(array_map($this->serialize(...), $filtered)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        BureauRepository $bureauRepo,
        RecurringExpenseTemplateRepository $repo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $type = $data['typeDepense'] ?? '';

        if (!in_array($type, self::REPETITIVE_TYPES, true)) {
            return $this->json(['message' => 'Type non récurrent'], 422);
        }

        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        $bureau = $user->getBureau();
        if (!$bureau && !empty($data['bureauId'])) {
            $bureau = $bureauRepo->find($data['bureauId']);
        }
        if (!$bureau) {
            return $this->json(['message' => 'Bureau requis'], 422);
        }

        $existing = $repo->findOneByBureauAndType($bureau, $type);
        if ($existing) {
            // Update instead of create duplicate
            $existing->setFrequency($data['frequency'] ?? $existing->getFrequency());
            $existing->setPriceType($data['priceType'] ?? $existing->getPriceType());
            $existing->setFixedAmount(($data['priceType'] ?? '') === 'fixed' ? (string)($data['fixedAmount'] ?? 0) : null);
            $existing->setStartPeriodMonth($data['startPeriodMonth'] ?? null);
            $existing->setStartPeriodYear((int)($data['startPeriodYear'] ?? date('Y')));
            $existing->setIsActive(true);
            $em->flush();
            return $this->json($this->serialize($existing));
        }

        $template = new RecurringExpenseTemplate();
        $template->setBureau($bureau);
        $template->setTypeDepense($type);
        $template->setFrequency($data['frequency'] ?? 'monthly');
        $template->setPriceType($data['priceType'] ?? 'fixed');
        $template->setFixedAmount(($data['priceType'] ?? '') === 'fixed' ? (string)($data['fixedAmount'] ?? 0) : null);
        $template->setStartPeriodMonth($data['startPeriodMonth'] ?? null);
        $template->setStartPeriodYear((int)($data['startPeriodYear'] ?? date('Y')));

        $em->persist($template);
        $em->flush();

        return $this->json($this->serialize($template), 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(
        RecurringExpenseTemplate $template,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (array_key_exists('isActive', $data))    $template->setIsActive((bool)$data['isActive']);
        if (array_key_exists('fixedAmount', $data)) $template->setFixedAmount($data['fixedAmount'] !== null ? (string)$data['fixedAmount'] : null);
        if (array_key_exists('priceType', $data))   $template->setPriceType($data['priceType']);
        if (array_key_exists('frequency', $data))   $template->setFrequency($data['frequency']);

        $em->flush();

        return $this->json($this->serialize($template));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(RecurringExpenseTemplate $template, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($template);
        $em->flush();
        return $this->json(['message' => 'Template supprimé']);
    }
}
