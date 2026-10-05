<?php

namespace App\EventSubscriber;

use App\Entity\Contrat;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(event: Events::prePersist)]
class ContractNumberSubscriber
{
    public function __construct(private EntityManagerInterface $em) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $contrat = $args->getObject();

        if (!$contrat instanceof Contrat || !empty($contrat->getNumero())) {
            return;
        }

        $year = (int) date('Y');

        $last = $this->em->createQueryBuilder()
            ->select('c.numero')
            ->from(Contrat::class, 'c')
            ->where('c.numero LIKE :pattern')
            ->setParameter('pattern', "AGO-{$year}-%")
            ->orderBy('c.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $next = 1;
        if ($last && isset($last['numero'])) {
            $parts = explode('-', (string) $last['numero']);
            $next  = ((int) end($parts)) + 1;
        }

        $contrat->setNumero(sprintf('AGO-%d-%06d', $year, $next));
    }
}
