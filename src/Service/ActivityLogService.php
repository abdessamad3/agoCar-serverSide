<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Bureau;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security               $security,
        private RequestStack           $requestStack,
    ) {}

    public function logCreate(string $entityType, int $entityId, array $newData, ?Bureau $bureau = null): void
    {
        $this->persist('CREATE', $entityType, $entityId, null, $newData, $bureau);
    }

    public function logUpdate(string $entityType, int $entityId, array $oldData, array $newData, ?Bureau $bureau = null): void
    {
        [$changedOld, $changedNew] = $this->diff($oldData, $newData);
        if (empty($changedOld)) {
            return; // nothing actually changed
        }
        $this->persist('UPDATE', $entityType, $entityId, $changedOld, $changedNew, $bureau);
    }

    public function logDelete(string $entityType, int $entityId, array $oldData, ?Bureau $bureau = null): void
    {
        $this->persist('DELETE', $entityType, $entityId, $oldData, null, $bureau);
    }

    public function logArchive(string $entityType, int $entityId, array $oldData, ?Bureau $bureau = null): void
    {
        $this->persist('ARCHIVE', $entityType, $entityId, $oldData, null, $bureau);
    }

    public function logView(string $entityType, int $entityId, ?Bureau $bureau = null): void
    {
        $this->persist('VIEW', $entityType, $entityId, null, null, $bureau);
    }

    private function persist(
        string  $action,
        string  $entityType,
        int     $entityId,
        ?array  $oldData,
        ?array  $newData,
        ?Bureau $bureau,
    ): void {
        /** @var Utilisateur|null $user */
        $user = $this->security->getUser();

        $log = new ActivityLog();
        $log->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setAction($action)
            ->setOldData($oldData)
            ->setNewData($newData)
            ->setUser($user instanceof Utilisateur ? $user : null)
            ->setBureau($bureau)
            ->setIpAddress($this->requestStack->getCurrentRequest()?->getClientIp());

        $this->em->persist($log);
        $this->em->flush();
    }

    /**
     * Returns [changedOld, changedNew] — only fields whose value actually changed.
     * Skips null vs missing differences and casts to string for loose comparison.
     */
    private function diff(array $old, array $new): array
    {
        $changedOld = [];
        $changedNew = [];

        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($keys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            // normalise: treat empty string same as null
            if ($oldVal === '') $oldVal = null;
            if ($newVal === '') $newVal = null;

            if ($oldVal !== $newVal) {
                $changedOld[$key] = $oldVal;
                $changedNew[$key] = $newVal;
            }
        }

        return [$changedOld, $changedNew];
    }
}
