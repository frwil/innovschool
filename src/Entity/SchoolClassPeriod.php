<?php

namespace App\Entity;

use App\Contract\GenderEnum;
use App\Repository\SchoolClassPeriodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\EvaluationAppreciationTemplate;
use App\Entity\ReportCardTemplate;

#[ORM\Entity(repositoryClass: SchoolClassPeriodRepository::class)]
#[ORM\UniqueConstraint(
    name: "unique_school_period_class",
    columns: ["school_id", "period_id", "class_occurence_id"]
)]
class SchoolClassPeriod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schoolClassPeriods')]
    private ?School $school = null;


    #[ORM\ManyToOne(inversedBy: 'schoolClassPeriods')]
    private ?SchoolPeriod $period = null;

    /**
     * @var Collection<int, StudentClass>
     */
    #[ORM\OneToMany(targetEntity: StudentClass::class, mappedBy: 'schoolClassPeriod', cascade: ['persist', 'remove'])]
    private Collection $studentClasses;

    /**
     * @var Collection<int, SchoolClassSubject>
     */
    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: SchoolClassSubject::class)]
    private Collection $schoolClassSubjects;


    /**
     * @var Collection<int, SchoolClassPaymentModal>
     *
     * Cette propriété représente une relation "Un à Plusieurs" entre l'entité SchoolClassPeriod et l'entité SchoolClassPaymentModal.
     * 
     * - Chaque instance de SchoolClassPeriod peut avoir plusieurs modalités de paiement associées.
     * - La relation est définie via l'attribut "schoolClassPeriod" dans l'entité SchoolClassPaymentModal.
     * - Doctrine gère cette relation en utilisant une collection (ArrayCollection) pour stocker les modalités de paiement.
     * 
     * Utilisations :
     * - Permet de récupérer toutes les modalités de paiement associées à une classe via la méthode getPaymentModals().
     * - Permet d'ajouter ou de supprimer des modalités de paiement pour une classe via les méthodes addPaymentModal() et removePaymentModal().
     * 
     * Exemple :
     * - Ajouter une modalité de paiement : $schoolClassPeriod->addPaymentModal($paymentModal);
     * - Récupérer toutes les modalités de paiement : $schoolClassPeriod->getPaymentModals();
     */
    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: SchoolClassPaymentModal::class)]
    private Collection $paymentModals;

    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: SchoolClassAdmissionPayment::class)]
    private Collection $admissionPayments;

    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: ModalitiesSubscriptions::class, cascade: ['persist', 'remove'])]
    private Collection $subscriptions;

    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: AdmissionReductions::class, orphanRemoval: true)]
    private Collection $admissionReductions;

    #[ORM\OneToMany(mappedBy: 'class', targetEntity: ClassSubjectModule::class, cascade: ['persist', 'remove'])]
    private Collection $classSubjectModules;

    #[ORM\ManyToOne(targetEntity: EvaluationAppreciationTemplate::class, inversedBy: 'schoolClassPeriods')]
    #[ORM\JoinColumn(name: 'evaluation_appreciation_template_id', referencedColumnName: 'id', nullable: true)]
    private ?EvaluationAppreciationTemplate $evaluationAppreciationTemplate = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'classMasters')]
    #[ORM\JoinColumn(name: 'class_master_id', referencedColumnName: 'id', nullable: true)]
    private ?User $classMaster = null;

    #[ORM\ManyToOne(targetEntity: ReportCardTemplate::class, inversedBy: 'schoolClassPeriods')]
    #[ORM\JoinColumn(name: 'report_card_template_id', referencedColumnName: 'id', nullable: true)]
    private ?ReportCardTemplate $reportCardTemplate = null;

    /**
     * @var Collection<int, SchoolClassAttendance>
     */
    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: SchoolClassAttendance::class)]
    private Collection $schoolClassAttendances;

    /**
     * @var Collection<int, StudentAttendance>
     */
    #[ORM\OneToMany(mappedBy: 'schoolClassPeriod', targetEntity: StudentAttendance::class)]
    private Collection $studentAttendances;

    #[ORM\ManyToOne(targetEntity: ClassOccurence::class, inversedBy: 'schoolClassPeriods')]
    private ?ClassOccurence $classOccurence = null;

    public function __construct()
    {
        $this->studentClasses = new ArrayCollection();
        $this->paymentModals = new ArrayCollection();
        $this->admissionPayments = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->admissionReductions = new ArrayCollection();
        $this->classSubjectModules = new ArrayCollection();
        $this->schoolClassSubjects = new ArrayCollection();
        $this->schoolClassAttendances = new ArrayCollection();
        $this->studentAttendances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): self
    {
        $this->school = $school;

        return $this;
    }

    public function getPeriod(): ?SchoolPeriod
    {
        return $this->period;
    }

    public function setPeriod(?SchoolPeriod $period): self
    {
        $this->period = $period;

        return $this;
    }

    /**
     * @return Collection<int, StudentClass>
     */
    public function getStudentClasses(): Collection
    {
        return $this->studentClasses;
    }

    public function addStudentClass(StudentClass $studentClass): self
    {
        if (!$this->studentClasses->contains($studentClass)) {
            $this->studentClasses[] = $studentClass;
            $studentClass->setSchoolClassPeriod($this);
        }

        return $this;
    }

    public function removeStudentClass(StudentClass $studentClass): self
    {
        if ($this->studentClasses->removeElement($studentClass)) {
            // set the owning side to null (unless already changed)
            if ($studentClass->getSchoolClassPeriod() === $this) {
                $studentClass->setSchoolClassPeriod(null);
            }
        }

        return $this;
    }



    /**
     * @return Collection<int, SchoolClassSubject>
     */
    public function getSchoolClassSubjects(): Collection
    {
        return $this->schoolClassSubjects;
    }

    public function addSchoolClassSubject(SchoolClassSubject $schoolClassSubject): self
    {
        if (!$this->schoolClassSubjects->contains($schoolClassSubject)) {
            $this->schoolClassSubjects[] = $schoolClassSubject;
            $schoolClassSubject->setSchoolClassPeriod($this);
        }

        return $this;
    }

    public function removeSchoolClassSubject(SchoolClassSubject $schoolClassSubject): self
    {
        if ($this->schoolClassSubjects->removeElement($schoolClassSubject)) {
            // set the owning side to null (unless already changed)
            if ($schoolClassSubject->getSchoolClassPeriod() === $this) {
                $schoolClassSubject->setSchoolClassPeriod(null);
            }
        }

        return $this;
    }

    public function getStudents(): array
    {
        $students = [];
        foreach ($this->getStudentClasses() as $studentClass) {
            $students[] = $studentClass->getStudent();
        }

        return $students;
    }



    /**
     * Nombre d'étudiants garçon
     */
    public function getStudentsBoysCount(): ?int
    {
        $students = $this->getStudents();
        $repeaters = array_reduce($students, function ($carry, User $item) {
            if ($item->getGender() == GenderEnum::MALE) {
                $carry++;
            }
            return $carry;
        }, 0);

        return $repeaters;
    }

    /**
     * Nombre d'étudiants filles
     */
    public function getStudentsGirlssCount(): ?int
    {
        $students = $this->getStudents();
        $repeaters = array_reduce($students, function ($carry, User $item) {
            if ($item->getGender() == GenderEnum::FEMALE) {
                $carry++;
            }
            return $carry;
        }, 0);

        return $repeaters;
    }

    public function getParentsCount(): ?int
    {
        $parentsIds = array_map(function (User $student) {
            $id = $student->getTutor() ? $student->getTutor()->getId() : null;
            if (null !== $id) return $id;
        }, $this->getStudents());

        return sizeof(\array_unique($parentsIds));
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
            $paymentModal->setSchoolClassPeriod($this);
        }
        return $this;
    }

    public function removePaymentModal(SchoolClassPaymentModal $paymentModal): self
    {
        if ($this->paymentModals->removeElement($paymentModal)) {
            // set the owning side to null (unless already changed)
            if ($paymentModal->getSchoolClassPeriod() === $this) {
                $paymentModal->setSchoolClassPeriod(null);
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
            $admissionPayment->setSchoolClass($this);
        }
        return $this;
    }

    public function removeAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if ($this->admissionPayments->removeElement($admissionPayment)) {
            // Set the owning side to null (unless already changed)
            if ($admissionPayment->getSchoolClass() === $this) {
                $admissionPayment->setSchoolClass(null);
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
            $subscription->setSchoolClass($this);
        }

        return $this;
    }

    public function removeSubscription(ModalitiesSubscriptions $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // Set the owning side to null (unless already changed)
            if ($subscription->getSchoolClassPeriod() === $this) {
                $subscription->setSchoolClass(null);
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
            $admissionReduction->setSchoolClass($this);
        }

        return $this;
    }

    public function removeAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if ($this->admissionReductions->removeElement($admissionReduction)) {
            // Set the owning side to null (unless already changed)
            if ($admissionReduction->getSchoolClass() === $this) {
                $admissionReduction->setSchoolClass(null);
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
            $classSubjectModule->setClass($this);
        }

        return $this;
    }

    public function removeClassSubjectModule(ClassSubjectModule $classSubjectModule): self
    {
        if ($this->classSubjectModules->removeElement($classSubjectModule)) {
            // Set the owning side to null (unless already changed)
            if ($classSubjectModule->getClass() === $this) {
                $classSubjectModule->setClass(null);
            }
        }

        return $this;
    }

    public function getEvaluationAppreciationTemplate(): ?EvaluationAppreciationTemplate
    {
        return $this->evaluationAppreciationTemplate;
    }

    public function setEvaluationAppreciationTemplate(?EvaluationAppreciationTemplate $evaluationAppreciationTemplate): self
    {
        $this->evaluationAppreciationTemplate = $evaluationAppreciationTemplate;
        return $this;
    }

    public function getClassMaster(): ?User
    {
        return $this->classMaster;
    }

    public function setClassMaster(?User $classMaster): self
    {
        $this->classMaster = $classMaster;
        return $this;
    }

    public function getReportCardTemplate(): ?ReportCardTemplate
    {
        return $this->reportCardTemplate;
    }

    public function setReportCardTemplate(?ReportCardTemplate $reportCardTemplate): self
    {
        $this->reportCardTemplate = $reportCardTemplate;
        return $this;
    }

    public function getSchoolClassAttendances(): Collection
    {
        return $this->schoolClassAttendances;
    }

    public function addSchoolClassAttendance(SchoolClassAttendance $attendance): self
    {
        if (!$this->schoolClassAttendances->contains($attendance)) {
            $this->schoolClassAttendances[] = $attendance;
            $attendance->setSchoolClass($this);
        }
        return $this;
    }

    public function removeSchoolClassAttendance(SchoolClassAttendance $attendance): self
    {
        if ($this->schoolClassAttendances->removeElement($attendance)) {
            if ($attendance->getSchoolClass() === $this) {
                $attendance->setSchoolClass(null);
            }
        }
        return $this;
    }

    public function getStudentAttendances(): Collection
    {
        return $this->studentAttendances;
    }

    public function addStudentAttendance(StudentAttendance $attendance): self
    {
        if (!$this->studentAttendances->contains($attendance)) {
            $this->studentAttendances[] = $attendance;
            $attendance->setSchoolClass($this);
        }
        return $this;
    }

    public function removeStudentAttendance(StudentAttendance $attendance): self
    {
        if ($this->studentAttendances->removeElement($attendance)) {
            if ($attendance->getSchoolClass() === $this) {
                $attendance->setSchoolClass(null);
            }
        }
        return $this;
    }

    public function getClassOccurence(): ?ClassOccurence
    {
        return $this->classOccurence;
    }

    public function setClassOccurence(?ClassOccurence $classOccurence): self
    {
        $this->classOccurence = $classOccurence;
        return $this;
    }
}
