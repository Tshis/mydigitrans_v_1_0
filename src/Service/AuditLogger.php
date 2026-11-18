<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Agency;
use App\Entity\AuditLog;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\DeviceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service central de traçabilité légère et d'audit de sécurité (AuditLog).
 */
final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {}

    /**
     * Enregistre une entrée d'audit compacte (uniquement le delta des champs critiques).
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function log(
        AuditAction $action,
        string $entityName,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        bool $flush = true,
    ): AuditLog {
        $resolvedUser = $actor;
        if (!$resolvedUser instanceof User) {
            $securityUser = $this->security->getUser();
            if ($securityUser instanceof User) {
                $resolvedUser = $securityUser;
            }
        }

        // Utilise getMainRequest() pour éviter les sous-requêtes internes
        $request = $this->requestStack->getMainRequest() ?? $this->requestStack->getCurrentRequest();
        $rawUa = (string) ($request?->headers->get('User-Agent') ?? '');
        $ipAddress = $request?->getClientIp();

        // Ne conserver que les clés qui ont réellement changé
        if (null !== $oldValues && null !== $newValues) {
            $diffOld = [];
            $diffNew = [];
            foreach ($newValues as $key => $val) {
                if (!\array_key_exists($key, $oldValues) || $oldValues[$key] !== $val) {
                    $diffOld[$key] = $oldValues[$key] ?? null;
                    $diffNew[$key] = $val;
                }
            }
            $oldValues = [] !== $diffOld ? $diffOld : null;
            $newValues = [] !== $diffNew ? $diffNew : null;
        }

        $audit = new AuditLog();
        $audit->setUser($resolvedUser)
            ->setAction($action)
            ->setEntityName($entityName)
            ->setEntityId($entityId)
            ->setOldValues([] !== $oldValues ? $oldValues : null)
            ->setNewValues([] !== $newValues ? $newValues : null)
            ->setIpAddress(null !== $ipAddress ? mb_substr($ipAddress, 0, 45) : null)
            ->setDeviceType($this->detectDeviceType($request, $rawUa))
            ->setUserAgent('' !== $rawUa ? $this->summarizeUserAgent($rawUa) : null);

        $this->em->persist($audit);

        if ($flush) {
            $this->em->flush();
        }

        return $audit;
    }

    /**
     * Retourne les logs d'audit formatés pour la console Plateforme.
     *
     * @return list<array<string, mixed>>
     */
    public function listPlatformLogs(int $limit = 200): array
    {
        /** @var list<AuditLog> $logs */
        $logs = $this->em->createQueryBuilder()
            ->select('a', 'u')
            ->from(AuditLog::class, 'a')
            ->leftJoin('a.user', 'u')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->formatLogs($logs);
    }

    /**
     * Retourne les logs d'audit formatés pour une Agence.
     *
     * @return list<array<string, mixed>>
     */
    public function listAgencyLogs(Agency $agency, int $limit = 200): array
    {
        /** @var list<AuditLog> $logs */
        $logs = $this->em->createQueryBuilder()
            ->select('a', 'u')
            ->from(AuditLog::class, 'a')
            ->join('a.user', 'u')
            ->andWhere('u.agency = :agency')
            ->setParameter('agency', $agency)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->formatLogs($logs);
    }

    /**
     * @param list<AuditLog> $logs
     * @return list<array<string, mixed>>
     */
    private function formatLogs(array $logs): array
    {
        $out = [];
        foreach ($logs as $log) {
            $user = $log->getUser();
            $out[] = [
                'id'          => $log->getId(),
                'user_name'   => $user instanceof User ? ($user->getFullName() ?: $user->getEmail()) : 'Système / Anonyme',
                'user_email'  => $user?->getEmail() ?? '-',
                'action'      => $log->getAction()->value,
                'entityName'  => (string) $log->getEntityName(),
                'entityId'    => $log->getEntityId(),
                'oldValues'   => $log->getOldValues() ?? [],
                'newValue'    => $log->getNewValues() ?? [],
                'device_type' => $log->getDeviceType()->value,
                'user_agent'  => $log->getUserAgent() ?? 'Inconnu',
                'ipAddress'   => $log->getIpAddress() ?? '0.0.0.0',
                'createdAt'   => $log->getCreatedAt(),
            ];
        }

        return $out;
    }

    private function detectDeviceType(?Request $request, string $rawUa): DeviceType
    {
        if (null !== $request && 'pos' === strtolower((string) $request->headers->get('X-Device-Type', ''))) {
            return DeviceType::Pos;
        }

        $ua = strtolower($rawUa);
        if (str_contains($ua, 'pos') || str_contains($ua, 'sunmi') || str_contains($ua, 'pax')) {
            return DeviceType::Pos;
        }

        if (1 === preg_match('/android|iphone|ipad|ipod|mobile|blackberry|windows phone/i', $ua)) {
            return DeviceType::Mobile;
        }

        return DeviceType::Desktop;
    }

    /**
     * Compacte la chaîne User-Agent (ex: "Chrome / Windows" ou "Safari / iPhone") pour économiser l'espace DB.
     */
    private function summarizeUserAgent(string $rawUa): string
    {
        $browser = match (true) {
            str_contains($rawUa, 'Edg/')     => 'Edge',
            str_contains($rawUa, 'OPR/'), str_contains($rawUa, 'Opera') => 'Opera',
            str_contains($rawUa, 'Chrome/')  => 'Chrome',
            str_contains($rawUa, 'Firefox/') => 'Firefox',
            str_contains($rawUa, 'Safari/') && !str_contains($rawUa, 'Chrome') => 'Safari',
            default                          => 'Navigateur',
        };

        $os = match (true) {
            str_contains($rawUa, 'Windows') => 'Windows',
            str_contains($rawUa, 'Android') => 'Android',
            str_contains($rawUa, 'iPhone')  => 'iPhone',
            str_contains($rawUa, 'iPad')    => 'iPad',
            str_contains($rawUa, 'Mac OS')  => 'macOS',
            str_contains($rawUa, 'Linux')   => 'Linux',
            default                         => 'Autre OS',
        };

        return sprintf('%s / %s', $browser, $os);
    }
}
