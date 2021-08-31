<?php

namespace App\Repository;

use App\Entity\WaveData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WaveDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaveData::class);
    }

    public function save(WaveData $waveData)
    {
        if (null === $waveData->getId()) {
            $this->_em->persist($waveData);
        }
        $this->_em->flush();
    }
}
