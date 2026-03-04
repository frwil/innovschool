<?php

namespace App\Entity;

use App\Repository\SchoolRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\RegistrationCardBaseConfig;
use App\Entity\EvaluationAppreciationTemplate;
use App\Entity\ReportCardTemplate;


#[ORM\Entity(repositoryClass: SchoolRepository::class)]
class School
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $address = null;

    #[ORM\Column(length: 255)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 255)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $lastAccesAt = null;


    #[ORM\Column]
    private ?bool $customer = null;

    /**
     * @var Collection<int, SchoolClassPeriod>
     */
    #[ORM\OneToMany(targetEntity: SchoolClassPeriod::class, mappedBy: 'school', cascade: ['remove'])]
    private Collection $schoolClassPeriods;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'school')]
    private Collection $users;

    #[ORM\Column(length: 255)]
    private ?string $acronym = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $schoolNumber = null;



    /**
     * @var Collection<int, SchoolClassPaymentModal>
     * Cette propriété représente une relation "Un à Plusieurs" entre l'entité School et l'entité SchoolClassPaymentModal.
     * - La relation est définie via l'attribut "school" dans l'entité SchoolClassPaymentModal.
     */
    #[ORM\OneToMany(mappedBy: 'school', targetEntity: SchoolClassPaymentModal::class)]
    private Collection $paymentModals;

    #[ORM\OneToMany(mappedBy: 'school', targetEntity: SchoolClassAdmissionPayment::class)]
    private Collection $admissionPayments;

    #[ORM\OneToMany(mappedBy: 'school', targetEntity: AdmissionReductions::class, orphanRemoval: true)]
    private Collection $admissionReductions;

    #[ORM\OneToMany(mappedBy: 'school', targetEntity: UserBaseConfiguration::class, cascade: ['persist', 'remove'])]
    private Collection $baseConfigurations;

    #[ORM\OneToOne(targetEntity: RegistrationCardBaseConfig::class, inversedBy: 'school', cascade: ['persist', 'remove'])]
    private ?RegistrationCardBaseConfig $registrationBaseConfig = null;

    #[ORM\OneToMany(mappedBy: 'school', targetEntity: ClassSubjectModule::class, cascade: ['persist', 'remove'])]
    private Collection $classSubjectModules;

    #[ORM\OneToMany(mappedBy: 'school', targetEntity: SubjectGroup::class, cascade: ['persist', 'remove'])]
    private Collection $subjectGroups;

    #[ORM\ManyToOne(targetEntity: EvaluationAppreciationTemplate::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'evaluation_appreciation_template_id', referencedColumnName: 'id', nullable: true)]
    private ?EvaluationAppreciationTemplate $evaluationAppreciationTemplate = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $licenseHash = null;

    /**
     * @var Collection<int, SchoolStudyType>
     */
    #[ORM\OneToMany(mappedBy: 'school', targetEntity: SchoolStudyType::class, cascade: ['persist', 'remove'])]
    private Collection $schoolStudyTypes;

    /**
     * @var Collection<int, AppLicense>
     */
    #[ORM\OneToMany(mappedBy: 'school', targetEntity: AppLicense::class, cascade: ['persist', 'remove'])]
    private Collection $licenses;

    /**
     * @var Collection<int, SchoolClassNumberingType>
     */
    #[ORM\OneToMany(mappedBy: 'school', targetEntity: SchoolClassNumberingType::class)]
    private Collection $schoolClassNumberingTypes;

    /**
     * @var Collection<int, Timetable>
     */
    #[ORM\OneToMany(mappedBy: 'school', targetEntity: Timetable::class)]
    private Collection $timetables;

    #[ORM\ManyToOne(targetEntity: ReportCardTemplate::class, inversedBy: 'schools')]
    #[ORM\JoinColumn(name: 'report_card_template_id', referencedColumnName: 'id', nullable: true)]
    private ?ReportCardTemplate $reportCardTemplate = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $adminOnly = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $activated = false;


    #[ORM\Column(type: Types::TEXT, nullable: true, options: ["default" => null])]
    private ?string $schoolValues = null;


    public function __construct()
    {
        $this->customer = false;
        $this->schoolClassPeriods = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->paymentModals = new ArrayCollection();
        $this->admissionPayments = new ArrayCollection();
        $this->admissionReductions = new ArrayCollection();
        $this->baseConfigurations = new ArrayCollection();
        $this->classSubjectModules = new ArrayCollection();
        $this->subjectGroups = new ArrayCollection();
        $this->schoolStudyTypes = new ArrayCollection();
        $this->lastAccesAt = new \DateTime();
        $this->schoolNumber = '';
        $this->logo = null;
        $this->evaluationAppreciationTemplate = null;
        $this->licenseHash = null;
        $this->acronym = '';
        $this->contactPhone = ''; 
        $this->contactEmail = '';
        $this->contactName = '';
        $this->name = '';
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(string $contactName): self
    {
        $this->contactName = $contactName;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(string $contactPhone): self
    {
        $this->contactPhone = $contactPhone;
        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }


    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isCustomer(): ?bool
    {
        return $this->customer;
    }

    public function setCustomer(bool $customer): self
    {
        $this->customer = $customer;
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
            $this->schoolClassPeriods[] = $schoolClassPeriod;
            $schoolClassPeriod->setSchool($this);
        }
        return $this;
    }

    public function removeSchoolClassPeriod(SchoolClassPeriod $schoolClassPeriod): self
    {
        if ($this->schoolClassPeriods->removeElement($schoolClassPeriod)) {
            // set the owning side to null (unless already changed)
            if ($schoolClassPeriod->getSchool() === $this) {
                $schoolClassPeriod->setSchool(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setSchool($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getSchool() === $this) {
                $user->setSchool(null);
            }
        }

        return $this;
    }

    public function getAcronym(): ?string
    {
        return $this->acronym;
    }

    public function setAcronym(string $acronym): self
    {
        $this->acronym = $acronym;

        return $this;
    }

    public function getSchoolNumber(): ?string
    {
        return $this->schoolNumber;
    }

    public function setSchoolNumber(string $schoolNumber): self
    {
        $this->schoolNumber = $schoolNumber;

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
            $paymentModal->setSchool($this);
        }

        return $this;
    }

    public function removePaymentModal(SchoolClassPaymentModal $paymentModal): self
    {
        if ($this->paymentModals->removeElement($paymentModal)) {
            // set the owning side to null (unless already changed)
            if ($paymentModal->getSchool() === $this) {
                $paymentModal->setSchool(null);
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
            $admissionPayment->setSchool($this);
        }

        return $this;
    }

    public function removeAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if ($this->admissionPayments->removeElement($admissionPayment)) {
            // Set the owning side to null (unless already changed)
            if ($admissionPayment->getSchool() === $this) {
                $admissionPayment->setSchool(null);
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
            $admissionReduction->setSchool($this);
        }

        return $this;
    }

    public function removeAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if ($this->admissionReductions->removeElement($admissionReduction)) {
            // Set the owning side to null (unless already changed)
            if ($admissionReduction->getSchool() === $this) {
                $admissionReduction->setSchool(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserBaseConfigurations>
     */
    public function getBaseConfigurations(): Collection
    {
        return $this->baseConfigurations;
    }

    public function addBaseConfiguration(UserBaseConfiguration $baseConfiguration): self
    {
        if (!$this->baseConfigurations->contains($baseConfiguration)) {
            $this->baseConfigurations[] = $baseConfiguration;
            $baseConfiguration->setSchool($this);
        }

        return $this;
    }

    public function removeBaseConfiguration(UserBaseConfiguration $baseConfiguration): self
    {
        if ($this->baseConfigurations->removeElement($baseConfiguration)) {
            // Set the owning side to null (unless already changed)
            if ($baseConfiguration->getSchool() === $this) {
                $baseConfiguration->setSchool(null);
            }
        }

        return $this;
    }

    // Getter pour la propriété logo
    public function getLogo(): ?string
    {
        return $this->logo;
    }

    // Setter pour la propriété logo
    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    // Getter et Setter pour la propriété registrationBaseConfig

    public function getRegistrationBaseConfig(): ?RegistrationCardBaseConfig
    {
        return $this->registrationBaseConfig;
    }

    public function setRegistrationBaseConfig(?RegistrationCardBaseConfig $registrationBaseConfig): self
    {
        $this->registrationBaseConfig = $registrationBaseConfig;

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
            $classSubjectModule->setSchool($this);
        }

        return $this;
    }

    public function removeClassSubjectModule(ClassSubjectModule $classSubjectModule): self
    {
        if ($this->classSubjectModules->removeElement($classSubjectModule)) {
            // Set the owning side to null (unless already changed)
            if ($classSubjectModule->getSchool() === $this) {
                $classSubjectModule->setSchool(null);
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
            $subjectGroup->setSchool($this);
        }

        return $this;
    }

    public function removeSubjectGroup(SubjectGroup $subjectGroup): self
    {
        if ($this->subjectGroups->removeElement($subjectGroup)) {
            // Set the owning side to null (unless already changed)
            if ($subjectGroup->getSchool() === $this) {
                $subjectGroup->setSchool(null);
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

    public function getLastAccesAt(): ?\DateTime
    {
        return $this->lastAccesAt;
    }

    public function setLastAccesAt(?\DateTime $lastAccesAt): self
    {
        $this->lastAccesAt = $lastAccesAt;
        return $this;
    }

    public function getLicenseHash(): ?string
    {
        return $this->licenseHash;
    }

    public function setLicenseHash(?string $licenseHash): self
    {
        $this->licenseHash = $licenseHash;
        return $this;
    }

    /**
     * @return Collection<int, AppLicense>
     */
    public function getLicenses(): Collection
    {
        return $this->licenses;
    }

    public function addLicense(AppLicense $license): self
    {
        if (!$this->licenses->contains($license)) {
            $this->licenses[] = $license;
            $license->setSchool($this);
        }
        return $this;
    }

    public function removeLicense(AppLicense $license): self
    {
        if ($this->licenses->removeElement($license)) {
            if ($license->getSchool() === $this) {
                $license->setSchool(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SchoolStudyType>
     */
    public function getSchoolStudyTypes(): Collection
    {
        return $this->schoolStudyTypes;
    }

    public function addSchoolStudyType(SchoolStudyType $schoolStudyType): self
    {
        if (!$this->schoolStudyTypes->contains($schoolStudyType)) {
            $this->schoolStudyTypes[] = $schoolStudyType;
            $schoolStudyType->setSchool($this);
        }
        return $this;
    }

    public function removeSchoolStudyType(SchoolStudyType $schoolStudyType): self
    {
        if ($this->schoolStudyTypes->removeElement($schoolStudyType)) {
            if ($schoolStudyType->getSchool() === $this) {
                $schoolStudyType->setSchool(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SchoolClassNumberingType>
     */
    public function getSchoolClassNumberingTypes(): Collection
    {
        return $this->schoolClassNumberingTypes;
    }

    public function addSchoolClassNumberingType(SchoolClassNumberingType $schoolClassNumberingType): self
    {
        if (!$this->schoolClassNumberingTypes->contains($schoolClassNumberingType)) {
            $this->schoolClassNumberingTypes->add($schoolClassNumberingType);
            $schoolClassNumberingType->setSchool($this);
        }

        return $this;
    }

    public function removeSchoolClassNumberingType(SchoolClassNumberingType $schoolClassNumberingType): self
    {
        if ($this->schoolClassNumberingTypes->removeElement($schoolClassNumberingType)) {
            // set the owning side to null (unless already changed)
            if ($schoolClassNumberingType->getSchool() === $this) {
                $schoolClassNumberingType->setSchool(null);
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
            $this->timetables[] = $timetable;
            $timetable->setSchool($this);
        }

        return $this;
    }

    public function removeTimetable(Timetable $timetable): self
    {
        if ($this->timetables->removeElement($timetable)) {
            // set the owning side to null (unless already changed)
            if ($timetable->getSchool() === $this) {
                $timetable->setSchool(null);
            }
        }

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

    public function isAdminOnly(): bool
    {
        return $this->adminOnly;
    }

    public function setAdminOnly(bool $adminOnly): self
    {
        $this->adminOnly = $adminOnly;
        return $this;
    }

    public function isActivated(): bool
    {
        return $this->activated;
    }

    public function setActivated(bool $activated): self
    {
        $this->activated = $activated;
        return $this;
    }

    public function getSchoolValues(): ?string
    {
        return $this->schoolValues;
    }

    public function setSchoolValues(?string $schoolValues): self
    {
        $this->schoolValues = $schoolValues;
        return $this;
    }
}
