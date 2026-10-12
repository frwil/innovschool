<?php

namespace App\Service;

use App\Entity\SchoolClassPeriod;
use App\Entity\StudentClass;
use App\Entity\User;
use App\Entity\UserStatusHistory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Statut d'un compte élève scopé par année scolaire (et donc par école, via
 * la SchoolClassPeriod) : un compte désactivé pendant une année puis réactivé
 * une autre année ne doit compter que dans l'année de sa réactivation.
 *
 * Règle : le compte est actif dans la période P si le dernier événement
 * d'historique de période ≤ P est une réactivation (enabled=true).
 * - aucun événement ≤ P et aucun événement du tout → état courant (comptes
 *   désactivés avant l'existence de l'historique) ;
 * - aucun événement ≤ P mais des événements postérieurs → actif (le compte
 *   n'était pas encore touché à l'époque P ; les seuls écrivains de statut
 *   créent toujours une ligne d'historique) ;
 * - période de la classe nulle → état courant (parité avec le UserChecker).
 *
 * Les événements legacy sans période sont rattachés par la migration à la
 * dernière année scolaire connue ; un événement sans période (écrit sans
 * contexte de session) retombe sur une clé dérivée de sa date (convention
 * sept→août, identique à SchoolPeriodRepository::suggestNextName).
 *
 * L'école n'est pas un critère : enabled est global au compte (le UserChecker
 * s'applique partout). Le scopage (année, école) est réalisé par l'évaluation
 * par SchoolClassPeriod, qui appartient à une école ; school sur l'événement
 * est de l'audit/affichage uniquement.
 */
class StudentStatusService
{
    /** @var array<int, UserStatusHistory[]> événements par élève, mémoïsés par requête */
    private array $eventsByUser = [];

    public function __construct(private EntityManagerInterface $entityManager) {}

    /** Le compte est-il actif dans la période de la classe ? */
    public function isActiveIn(User $student, SchoolClassPeriod $scp): bool
    {
        return $this->isActiveInPeriod($student, $scp->getPeriod()?->getName());
    }

    /**
     * Inscriptions de la liste dont le compte est actif dans la période de
     * $scp (les événements d'historique sont chargés en une requête pour le
     * lot d'élèves).
     *
     * @param StudentClass[] $studentClasses
     * @return StudentClass[]
     */
    public function filterActiveStudentClasses(SchoolClassPeriod $scp, array $studentClasses): array
    {
        $periodName = $scp->getPeriod()?->getName();
        if ($periodName === null) {
            // Pas de période : état courant, comme avant le scopage.
            return array_filter($studentClasses, fn (StudentClass $sc) => $sc->getStudent() !== null && false !== $sc->getStudent()->isEnabled());
        }

        $userIds = [];
        foreach ($studentClasses as $studentClass) {
            $student = $studentClass->getStudent();
            if ($student !== null) {
                $userIds[$student->getId()] = true;
            }
        }
        $this->loadEventsFor(array_keys($userIds));

        return array_filter(
            $studentClasses,
            fn (StudentClass $sc) => $this->isActiveInPeriod($sc->getStudent(), $periodName)
        );
    }

    /** @return StudentClass[] inscriptions actives de la classe (période de la classe) */
    public function getActiveStudentClasses(SchoolClassPeriod $scp): array
    {
        return $this->filterActiveStudentClasses($scp, $scp->getStudentClasses()->toArray());
    }

    /** @return User[] élèves au compte actif dans la période de la classe */
    public function getActiveStudents(SchoolClassPeriod $scp): array
    {
        return array_map(
            fn (StudentClass $studentClass) => $studentClass->getStudent(),
            $this->getActiveStudentClasses($scp)
        );
    }

    private function isActiveInPeriod(?User $student, ?string $periodName): bool
    {
        if ($student === null) {
            return false;
        }
        if ($periodName === null) {
            // Pas de période : état courant (parité avec le UserChecker).
            return false !== $student->isEnabled();
        }

        $events = $this->loadEventsFor([$student->getId()]);
        $events = $events[$student->getId()];

        $last = null;
        foreach ($events as $event) {
            $key = $this->eventPeriodKey($event);
            if ($key === null || strcmp($key, $periodName) > 0) {
                continue;
            }
            if ($last === null || $this->eventIsAfter($event, $last)) {
                $last = $event;
            }
        }

        if ($last === null) {
            // Aucun événement jusqu'à cette période : le compte n'était pas
            // encore touché → actif. Exception : aucun événement du tout
            // (compte désactivé avant l'existence de l'historique) → état
            // courant.
            return $events === [] ? false !== $student->isEnabled() : true;
        }

        return true === $last->isEnabled();
    }

    /**
     * Charge les événements d'un lot d'élèves en une requête, mémoïsés par
     * instance de service (les services Symfony sont partagés par requête).
     *
     * @param int[] $userIds
     * @return array<int, UserStatusHistory[]>
     */
    private function loadEventsFor(array $userIds): array
    {
        $missing = [];
        foreach ($userIds as $id) {
            if (!array_key_exists($id, $this->eventsByUser)) {
                $missing[] = $id;
            }
        }

        if ($missing !== []) {
            $events = $this->entityManager->getRepository(UserStatusHistory::class)->findBy(
                ['user' => $missing],
                ['id' => 'ASC']
            );
            foreach ($events as $event) {
                $uid = $event->getUser()?->getId();
                if ($uid !== null) {
                    $this->eventsByUser[$uid][] = $event;
                }
            }
            foreach ($missing as $id) {
                if (!array_key_exists($id, $this->eventsByUser)) {
                    $this->eventsByUser[$id] = [];
                }
            }
        }

        return array_intersect_key($this->eventsByUser, array_flip($userIds));
    }

    /**
     * Clé de période d'un événement : période stockée (modèle actuel), sinon
     * dérivée de la date (événement écrit sans contexte de session).
     */
    private function eventPeriodKey(UserStatusHistory $event): ?string
    {
        $period = $event->getSchoolPeriod();
        if ($period !== null) {
            return $period->getName();
        }

        $changedAt = $event->getChangedAt();
        $year = (int) $changedAt->format('Y');
        if ((int) $changedAt->format('n') < 9) {
            $year--;
        }
        return $year . '/' . ($year + 1);
    }

    /** $a est-il postérieur à $b selon (clé de période, changedAt, id) ? */
    private function eventIsAfter(UserStatusHistory $a, UserStatusHistory $b): bool
    {
        $keyA = $this->eventPeriodKey($a) ?? '';
        $keyB = $this->eventPeriodKey($b) ?? '';

        // Parenthèses obligatoires : « > » lie plus fort que « <=> », donc
        // « A <=> B > 0 » s'évalue comme « A <=> (B > 0) » et renvoie toujours
        // 0 — le premier événement gagnait au lieu du dernier (un compte
        // désactivé puis réactivé restait exclu des bordereaux et bulletins).
        return ([$keyA, $a->getChangedAt()->getTimestamp(), $a->getId() ?? 0]
            <=> [$keyB, $b->getChangedAt()->getTimestamp(), $b->getId() ?? 0]) > 0;
    }
}
