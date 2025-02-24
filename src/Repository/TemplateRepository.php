<?php
namespace App\Repository;

use App\Entity\Template;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Template::class);
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->orWhere('t.description LIKE :query')
            ->orWhere('EXISTS (
                SELECT 1 FROM App\Entity\Question q
                WHERE q.template = t AND q.description LIKE :query
            )')
            ->orWhere('EXISTS (
                SELECT 1 FROM App\Entity\Comment c
                WHERE c.template = t AND c.content LIKE :query
            )')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();
    }

    public function findAll(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC']);
    }
}