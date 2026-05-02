<?php

namespace App\Entity;

use App\Contract\GenderEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', columns: ['username'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $loginAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $capacity = null;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'author')]
    private Collection $paymentsRegistered;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'tutorsUsers', cascade: ['persist'])]
    private ?self $tutor = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'tutor')]
    private Collection $tutorsUsers;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $infos = null;

    /**
     * @var Collection<int, StudentClass>
     */
    #[ORM\OneToMany(targetEntity: StudentClass::class, mappedBy: 'student', cascade:['remove'])]
    private Collection $studentClasses;

    #[ORM\Column(length: 10)]
    private ?GenderEnum $gender = null;

    #[ORM\Column]
    private ?bool $repeated = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $registrationNumber = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $nationalRegistrationNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $religion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeOfBirth = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: SchoolClassAdmissionPayment::class)]
    private Collection $admissionPayments;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: ModalitiesSubscriptions::class, cascade: ['persist', 'remove'])]
    private Collection $subscriptions;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: AdmissionReductions::class, orphanRemoval: true)]
    private Collection $admissionReductions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserBaseConfiguration::class, cascade: ['persist', 'remove'])]
    private Collection $baseConfigurations;
    /**
     * @var Collection<int, StudentAttendance>
     */
    #[ORM\OneToMany(targetEntity: StudentAttendance::class, mappedBy: 'student')]
    private Collection $studentAttendances;

    /**
     * @var Collection<int, SchoolClassSubject>
     */
    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: SchoolClassSubject::class)]
    private Collection $teacherSchoolClassSubjects;

    #[ORM\OneToMany(mappedBy: 'classMaster', targetEntity: SchoolClassPeriod::class)]
    private Collection $classMasters;

    #[ORM\Column(type: 'boolean')]
    private $resetPassword = false;

    #[ORM\OneToMany(mappedBy: 'teacher', targetEntity: Timetable::class)]
    private Collection $timetables;

        /**
     * @var Collection<int, AdmissionReductions>
     */
    #[ORM\OneToMany(mappedBy: 'requestedBy', targetEntity: AdmissionReductions::class)]
    private $admissionReductionsRequested;

    /**
     * @var Collection<int, AdmissionReductions>
     */
    #[ORM\OneToMany(mappedBy: 'approvedBy', targetEntity: AdmissionReductions::class)]
    private $admissionReductionsApproved;

    
    
    public function __construct() 
    {
        $this->createdAt = new \DateTime();
        $this->enabled = true;
        $this->paymentsRegistered = new ArrayCollection();
        $this->tutorsUsers = new ArrayCollection();
        $this->studentClasses = new ArrayCollection();
        $this->repeated = false;
        $this->admissionPayments = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->admissionReductions = new ArrayCollection();
        $this->baseConfigurations = new ArrayCollection();
        $this->studentAttendances = new ArrayCollection();
        $this->teacherSchoolClassSubjects = new ArrayCollection();
        $this->classMasters = new ArrayCollection();
        $this->timetables = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function addRole(string $role): self
    {
        $this->roles[] = $role;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

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

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getLoginAt(): ?\DateTimeInterface
    {
        return $this->loginAt;
    }

    public function setLoginAt(?\DateTimeInterface $loginAt): self
    {
        $this->loginAt = $loginAt;

        return $this;
    }

    public function getCapacity(): ?array
    {
        if(null == $this->capacity){
            return [];
        }
        return $this->capacity;
    }

    public function setCapacity(?array $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPaymentsRegistered(): Collection
    {
        return $this->paymentsRegistered;
    }

    public function addPaymentsRegistered(Payment $paymentsRegistered): self
    {
        if (!$this->paymentsRegistered->contains($paymentsRegistered)) {
            $this->paymentsRegistered->add($paymentsRegistered);
            $paymentsRegistered->setAuthor($this);
        }

        return $this;
    }

    public function removePaymentsRegistered(Payment $paymentsRegistered): self
    {
        if ($this->paymentsRegistered->removeElement($paymentsRegistered)) {
            // set the owning side to null (unless already changed)
            if ($paymentsRegistered->getAuthor() === $this) {
                $paymentsRegistered->setAuthor(null);
            }
        }

        return $this;
    }

    public function getStudentClassesList(): string
    {
        $classes = '';
        foreach ($this->studentClasses as $studentClass) {
            $period = $studentClass->getSchoolClassPeriod();
            $classOccurence = $period ? $period->getClassOccurence() : null;
            $name = $classOccurence ? $classOccurence->getName() : '';
            if ($name) {
                $classes .= $name . ', ';
            }
        }
        // retirer la dernière virgule
        return $classes ? substr($classes, 0, -2) : '';
    }

    public function getClasseList(): string
    {
        
        if ($this->isStudent()) {
            return $this->getStudentClassesList();
        }

        return '';
    }

    public function isTeacher(): bool
    {
        return in_array('ROLE_TEACHER', $this->roles);
    }

    public function isStudent(): bool
    {
        return in_array('ROLE_STUDENT', $this->roles);
    }

    public function getSchoolName(): ?string
    {
        return $this->school ? $this->school->getName() : null;
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

    public function getTutor(): ?self
    {
        return $this->tutor;
    }

    public function setTutor(?self $tutor): self
    {
        $this->tutor = $tutor;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getTutorsUsers(): Collection
    {
        return $this->tutorsUsers;
    }

    public function addTutorsUser(self $tutorsUser): self
    {
        if (!$this->tutorsUsers->contains($tutorsUser)) {
            $this->tutorsUsers->add($tutorsUser);
            $tutorsUser->setTutor($this);
        }

        return $this;
    }

    public function removeTutorsUser(self $tutorsUser): self
    {
        if ($this->tutorsUsers->removeElement($tutorsUser)) {
            // set the owning side to null (unless already changed)
            if ($tutorsUser->getTutor() === $this) {
                $tutorsUser->setTutor(null);
            }
        }

        return $this;
    }

    public function getInfos(): ?string
    {
        return $this->infos;
    }

    public function setInfos(?string $infos): self
    {
        $this->infos = $infos;

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
            $this->studentClasses->add($studentClass);
            $studentClass->setStudent($this);
        }

        return $this;
    }

    public function removeStudentClass(StudentClass $studentClass): self
    {
        if ($this->studentClasses->removeElement($studentClass)) {
            // set the owning side to null (unless already changed)
            if ($studentClass->getStudent() === $this) {
                $studentClass->setStudent(null);
            }
        }
        return $this;
    }

    public function getGender(): ?GenderEnum
    {
        return $this->gender;
    }

    public function setGender(GenderEnum $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function isRepeated(): ?bool
    {
        return $this->repeated;
    }

    public function setRepeated(bool $repeated): self
    {
        $this->repeated = $repeated;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getNationalRegistrationNumber(): ?string
    {
        return $this->nationalRegistrationNumber;
    }

    public function setNationalRegistrationNumber(?string $nationalRegistrationNumber): self
    {
        $this->nationalRegistrationNumber = $nationalRegistrationNumber;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;

        return $this;
    }

    public function getReligion(): ?string
    {
        return $this->religion;
    }

    public function setReligion(?string $religion): self
    {
        $this->religion = $religion;

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
            $admissionPayment->setStudent($this);
        }
        return $this;
    }

    /** @return Collection<int, StudentAttendance>
     */
    public function getStudentAttendances(): Collection
    {
        return $this->studentAttendances;
    }

    public function addStudentAttendance(StudentAttendance $studentAttendance): self
    {
        if (!$this->studentAttendances->contains($studentAttendance)) {
            $this->studentAttendances->add($studentAttendance);
            $studentAttendance->setStudent($this);
        }

        return $this;
    }

    public function removeAdmissionPayment(SchoolClassAdmissionPayment $admissionPayment): self
    {
        if ($this->admissionPayments->removeElement($admissionPayment)) {
            // Set the owning side to null (unless already changed)
            if ($admissionPayment->getStudent() === $this) {
                $admissionPayment->setStudent(null);
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
            $subscription->setStudent($this);
        }

        return $this;
    }

    public function removeSubscription(ModalitiesSubscriptions $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            // Set the owning side to null (unless already changed)
            if ($subscription->getStudent() === $this) {
                $subscription->setStudent(null);
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
            $admissionReduction->setStudent($this);
        }

        return $this;
    }

    public function removeAdmissionReduction(AdmissionReductions $admissionReduction): self
    {
        if ($this->admissionReductions->removeElement($admissionReduction)) {
            // Set the owning side to null (unless already changed)
            if ($admissionReduction->getStudent() === $this) {
                $admissionReduction->setStudent(null);
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
            $baseConfiguration->setUser($this);
        }

        return $this;
    }

    public function removeBaseConfiguration(UserBaseConfiguration $baseConfiguration): self
    {
        if ($this->baseConfigurations->removeElement($baseConfiguration)) {
            // Set the owning side to null (unless already changed)
            if ($baseConfiguration->getUser() === $this) {
                $baseConfiguration->setUser(null);
            }
        }
        return $this;
    }
    public function removeStudentAttendance(StudentAttendance $studentAttendance): self
    {
        if ($this->studentAttendances->removeElement($studentAttendance)) {
            // set the owning side to null (unless already changed)
            if ($studentAttendance->getStudent() === $this) {
                $studentAttendance->setStudent(null);
            }
        }

        return $this;
    }

    // Getter et Setter pour placeOfBirth
    public function getPlaceOfBirth(): ?string
    {
        return $this->placeOfBirth;
    }

    public function setPlaceOfBirth(?string $placeOfBirth): self
    {
        $this->placeOfBirth = $placeOfBirth;

        return $this;
    }

    // Getter et Setter pour photo
    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }


    /**
     * @return Collection<int, SchoolClassSubject>
     */
    public function getTeacherSchoolClassSubjects(): Collection
    {
        return $this->teacherSchoolClassSubjects;
    }

    public function addTeacherSchoolClassSubject(SchoolClassSubject $subject): self
    {
        if (!$this->teacherSchoolClassSubjects->contains($subject)) {
            $this->teacherSchoolClassSubjects[] = $subject;
            $subject->setTeacher($this);
        }
        return $this;
    }

    public function removeTeacherSchoolClassSubject(SchoolClassSubject $subject): self
    {
        if ($this->teacherSchoolClassSubjects->removeElement($subject)) {
            if ($subject->getTeacher() === $this) {
                $subject->setTeacher(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SchoolClassPeriod>
     */
    public function getClassMasters(): Collection
    {
        return $this->classMasters;
    }

    public function addClassMaster(SchoolClassPeriod $schoolClassPeriod): self
    {
        if (!$this->classMasters->contains($schoolClassPeriod)) {
            $this->classMasters[] = $schoolClassPeriod;
            $schoolClassPeriod->setClassMaster($this);
        }
        return $this;
    }

    public function removeClassMaster(SchoolClassPeriod $schoolClassPeriod): self
    {
        if ($this->classMasters->removeElement($schoolClassPeriod)) {
            if ($schoolClassPeriod->getClassMaster() === $this) {
                $schoolClassPeriod->setClassMaster(null);
            }
        }
        return $this;
    }

    public function isResetPassword(): bool
    {
        return (bool) $this->resetPassword;
    }

    public function setResetPassword(bool $resetPassword): self
    {
        $this->resetPassword = $resetPassword;
        return $this;
    }

    public function isSchoolClassClassMaster(SchoolClassPeriod $schoolClassPeriod): bool
    {
        return $this->classMasters->contains($schoolClassPeriod);
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
            $timetable->setTeacher($this);
        }

        return $this;
    }

    public function removeTimetable(Timetable $timetable): self
    {
        if ($this->timetables->removeElement($timetable)) {
            // set the owning side to null (unless already changed)
            if ($timetable->getTeacher() === $this) {
                $timetable->setTeacher(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getAdmissionReductionsRequested()
    {
        return $this->admissionReductionsRequested;
    }

    public function getAdmissionReductionsApproved()
    {
        return $this->admissionReductionsApproved;
    }

}
