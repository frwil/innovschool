<?php

namespace App\Entity;

use App\Repository\SchoolPeriodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\SubjectGroup;

#[ORM\Entity(repositoryClass: SchoolPeriodRepository::class)]
class SchoolPeriod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    /**
     * @var Collection<int, SchoolClassPeriod>
     */
    #[ORM\OneToMany(targetEntity: SchoolClassPeriod::class, mappedBy: 'period')]
    private Collection $schoolClassPeriods;

    /**
     * @var Collection<int, SchoolEvaluation>
     */
    #[ORM\OneToMany(targetEntity: SchoolEvaluation::class, mappedBy: 'period')]
    private Collection $schoolEvaluations;

    /**
     * @var Collection<int, SchoolClassPaymentModal>
     *
     * Cette propriété représente une relation "Un à Plusieurs" entre l'entité SchoolPeriod et l'entité SchoolClassPaymentModal.
     * 
     * - Chaque instance de SchoolPeriod peut avoir plusieurs modalités de paiement associées.
     * - La relation est définie via l'attribut "schoolPeriod" dans l'entité SchoolClassPaymentModal.
     * - Doctrine gère cette relation en utilisant une collection (ArrayCollection) pour stocker les modalités de paiement.
     * 
     * Utilisations :
     * - Permet de récupérer toutes les modalités de paiement associées à une période scolaire via la méthode getPaymentModals().
     * - Permet d'ajouter ou de supprimer des modalités de paiement pour une période scolaire via les méthodes addPaymentModal() et removePaymentModal().
     * 
     * Exemple :
     * - Ajouter une modalité de paiement : $schoolPeriod->addPaymentModal($paymentModal);
     * - Récupérer toutes les modalités de paiement : $schoolPeriod->getPaymentModals();
     */
    #[ORM\OneToMany(mappedBy: 'schoolPeriod', targetEntity: SchoolClassPaymentModal::class)]
    private Collection $paymentModals;

    #[ORM\OneToMany(mappedBy: 'schoolPeriod', targetEntity: SchoolClassAdmissionPayment::class)]
    private Collection $admissionPayments;

    #[ORM\OneToMany(mappedBy: 'schoolPeriod', targetEntity: ModalitiesSubscriptions::class, cascade: ['persist', 'remove'])]
    private Collection $subscriptions;

    #[ORM\OneToMany(mappedBy: 'schoolPeriod', targetEntity: AdmissionReductions::class, orphanRemoval: true)]
    private Collection $admissionReductions;

    #[ORM\OneToMany(mappedBy: 'period', targetEntity: ClassSubjectModule::class, cascade: ['persist', 'remove'])]
    private Collection $classSubjectModules;


    #[ORM\OneToMany(mappedBy: 'period', targetEntity: SubjectGroup::class)]
    private Collection $subjectGroups;

    #[ORM\OneToMany(mappedBy: 'period', targetEntity: Timetable::class)]
    private Collection $timetables;

    public function __construct() 
    {
        $this->enabled = false;
        $this->schoolClassPeriods = new ArrayCollection();
        $this->schoolEvaluations = new ArrayCollection();
        $this->paymentModals = new ArrayCollection();
        $this->admissionPayments = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->admissionReductions = new ArrayCollection();
        $this->classSubjectModules = new ArrayCollection();
        $this->subjectGroups = new ArrayCollection();
        $this->timetables = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return Collection<int, SchoolClassPeriod>
     */
    public function getSchoolClassPeriods(): Collection
    {
        return $this->schoolClassPeriods;
    }

    public function addSchoolClassPeriod(SchoolClassPeriod $schoolClassPeriod): self
    {
        if (!$this->schoolClassPeriods->contains($schoolClassPeriod)) {
            $this->schoolClassPeriods->add($schoolClassPeriod);
            $schoolClassPeriod->setPeriod($this);
        }

        return $this;
    }

    public function removeSchoolClassPeriod(SchoolClassPeriod $schoolClassPeriod): self
    {
        if ($this->schoolClassPeriods->removeElement($schoolClassPeriod)) {
            // set the owning side to null (unless already changed)
            if ($schoolClassPeriod->getPeriod() === $this) {
                $schoolClassPeriod->setPeriod(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->name;
    }

    /**
     * @return Collection<int, SchoolEvaluation>
     */
    public function getSchoolEvaluations(): Collection
    {
        return $this->schoolEvaluations;
    }

    public function addSchoolEvaluation(SchoolEvaluation $schoolEvaluation): self
    {
        if (!$this->schoolEvaluations->contains($schoolEvaluation)) {
            $this->schoolEvaluations->add($schoolEvaluation);
            $schoolEvaluation->setPeriod($this);
        }

        return $this;
    }

    public function removeSchoolEvaluation(SchoolEvaluation $schoolEvaluation): self
    {
        if ($this->schoolEvaluations->removeElement($schoolEvaluation)) {
            // set the owning side to null (unless already changed)
            if ($schoolEvaluation->getPeriod() === $this) {
                $schoolEvaluation->setPeriod(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<int, SchoolClassPaymentModal>
     */
    public function getPaymentModals(): Collection
    {
        return $this->paymentModals;
    }

    public function addPaymentModal(SchoolClassPaymentModal $paymentModal): self
    {
        if (!$this->paymentModals->contains($paymentModal)) {
            $this->paymentModals[] = $paymentModal;
            $paymentModal->setSchoolPeriod($this);
        }
        return $this;
    }

    public function removePaymentModal(SchoolClassPaymentModal $paymentModal): self
    {
        if ($this->paymentModals->removeElement($paymentModal)) {
            if ($paymentModal->getSchoolPeriod() === $this) {
                $paymentModal->setSchoolPeriod(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SchoolClassAdmissionPayment>
     */
    public function getAdmissionPayments(): Collection
    {
        return $this->admissionPayments;
    }

    public function addAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if (!$this->admissionPayments->contains($admissionPayment)) {
            $this->admissionPayments[] = $admissionPayment;
            $admissionPayment->setSchoolPeriod($this);
        }

        return $this;
    }

    public function removeAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if ($this->admissionPayments->removeElement($admissionPayment)) {
            // Set the owning side to null (unless already changed)
            if ($admissionPayment->getSchoolPeriod() === $this) {
                $admissionPayment->setSchoolPeriod(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ModalitiesSubscriptions>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function addSubscription(ModalitiesSubscriptions $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions[] = $subscription;
            $subscription->setSchoolPeriod($this);
        }

        return $this;
    }

    public function removeSubscription(ModalitiesSubscriptions $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // Set the owning side to null (unless already changed)
            if ($subscription->getSchoolPeriod() === $this) {
                $subscription->setSchoolPeriod(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdmissionReductions>
     */
    public function getAdmissionReductions(): Collection
    {
        return $this->admissionReductions;
    }

    public function addAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if (!$this->admissionReductions->contains($admissionReduction)) {
            $this->admissionReductions[] = $admissionReduction;
            $admissionReduction->setSchoolPeriod($this);
        }

        return $this;
    }

    public function removeAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if ($this->admissionReductions->removeElement($admissionReduction)) {
            // Set the owning side to null (unless already changed)
            if ($admissionReduction->getSchoolPeriod() === $this) {
                $admissionReduction->setSchoolPeriod(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ClassSubjectModules>
     */
    public function getClassSubjectModules(): Collection
    {
        return $this->classSubjectModules;
    }

    public function addClassSubjectModule(ClassSubjectModule $classSubjectModule): self
    {
        if (!$this->classSubjectModules->contains($classSubjectModule)) {
            $this->classSubjectModules[] = $classSubjectModule;
            $classSubjectModule->setPeriod($this);
        }

        return $this;
    }

    public function removeClassSubjectModule(ClassSubjectModule $classSubjectModule): self
    {
        if ($this->classSubjectModules->removeElement($classSubjectModule)) {
            // Set the owning side to null (unless already changed)
            if ($classSubjectModule->getPeriod() === $this) {
                $classSubjectModule->setPeriod(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SubjectGroup>
     */
    public function getSubjectGroups(): Collection
    {
        return $this->subjectGroups;
    }

    public function addSubjectGroup(SubjectGroup $subjectGroup): self
    {
        if (!$this->subjectGroups->contains($subjectGroup)) {
            $this->subjectGroups[] = $subjectGroup;
            $subjectGroup->setPeriod($this);
        }
        return $this;
    }

    public function removeSubjectGroup(SubjectGroup $subjectGroup): self
    {
        if ($this->subjectGroups->removeElement($subjectGroup)) {
            if ($subjectGroup->getPeriod() === $this) {
                $subjectGroup->setPeriod(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Timetable>
     */
    public function getTimetables(): Collection
    {
        return $this->timetables;
    }

    public function addTimetable(Timetable $timetable): self
    {
        if (!$this->timetables->contains($timetable)) {
            $this->timetables->add($timetable);
            $timetable->setPeriod($this);
        }
        return $this;
    }

    public function removeTimetable(Timetable $timetable): self
    {
        if ($this->timetables->removeElement($timetable)) {
            if ($timetable->getPeriod() === $this) {
                $timetable->setPeriod(null);
            }
        }
        return $this;
    }
}
