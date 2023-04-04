<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Avatar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AvatarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avatar::class);
    }

    public function save(Avatar $entity): void
    {
        if (null === $entity->getId()) {
            $this->_em->persist($entity);
        }
        $this->_em->flush();
    }
}
