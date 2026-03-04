<?php

namespace App\Command;

use App\Contract\GenderEnum;
use App\Contract\SubjectGroupEnum;
use App\Entity\CoreUpdate;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\SchoolEvaluationTime;
use App\Entity\StudyLevel;
use App\Entity\SectionCategorySubjectGroup;
use App\Entity\User;
use App\Repository\CoreUpdateRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:default-data',
    description: 'Add a short description for your command',
)]
class DefaultDataCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private CoreUpdateRepository $coreUpdateRepository,
        private SluggerInterface $slugger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $message = '';
        $admin = $this->userRepository->findOneBy(['email' => 'admin@admin.com']);

        if (null === $admin) {
            $admin = (new User())
                ->setEmail('admin@admin.com')
                ->setUsername('admin@admin.com')
                ->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_BASE', 'ROLE_ADMIN'])
                ->setAddress("Admin address")
                ->setFullName('Admin Admin')
                ->setPhone("694199566")
                ->setGender(GenderEnum::MALE);

            $admin->setPassword(
                $this->passwordHasher->hashPassword(
                    $admin,
                    'password'
                )
            );
            $this->entityManager->persist($admin);
            $message .= 'Admin user ';
        }

        $coreUpdate = $this->coreUpdateRepository->findOneBy([]);
        if(null == $coreUpdate){
            $update = (new CoreUpdate())
                ->setVersion("0.0.0");
            $this->entityManager->persist($update);

            $message = 'Core Update ';
        }

        $sections = $this->entityManager->getRepository(StudyLevel::class)->count();
        if($sections == 0){
            
            $this->populateSchoolSectionsAndCategories();

            $message .= 'Sections ';
        }

        $evaluationFrame = $this->entityManager->getRepository(SchoolEvaluationFrame::class)->count();
        if($evaluationFrame == 0){
            $first = (new SchoolEvaluationFrame())
                ->setName("Premier trimestre")
                ->setSlug($this->slugger->slug('Premier trimestre'));
            $this->entityManager->persist($first);

            $second = (new SchoolEvaluationFrame())
                ->setName("Deuxième trimestre")
                ->setSlug($this->slugger->slug('Deuxième trimestre'));
            $this->entityManager->persist($second);

            $third = (new SchoolEvaluationFrame())
                ->setName("Troisième trimestre")
                ->setSlug($this->slugger->slug('Troisième trimestre'));
            $this->entityManager->persist($third);

            $this->entityManager->flush();

            $message .= 'Evaluation Frame ';
        }

        $evaluationTime = $this->entityManager->getRepository(SchoolEvaluationTime::class)->count();
        if($evaluationTime == 0){
            $first = (new SchoolEvaluationTime())
                ->setName("Première séquence")
                ->setSlug($this->slugger->slug('Première séquence'));
            $this->entityManager->persist($first);

            $second = (new SchoolEvaluationTime())
                ->setName("Deuxième séquence")
                ->setSlug($this->slugger->slug('Deuxième séquence'));
            $this->entityManager->persist($second);

            $third = (new SchoolEvaluationTime())
                ->setName("Troisième séquence")
                ->setSlug($this->slugger->slug('Troisième séquence'));
            $this->entityManager->persist($third);

            $fourth = (new SchoolEvaluationTime())
                ->setName("Quatrième séquence")
                ->setSlug($this->slugger->slug('Quatrième séquence'));
            $this->entityManager->persist($fourth);

            $fith = (new SchoolEvaluationTime())
                ->setName("Cinquième séquence")
                ->setSlug($this->slugger->slug('Cinquième séquence'));
            $this->entityManager->persist($fith);

            $sixth = (new SchoolEvaluationTime())
                ->setName("Sixième séquence")
                ->setSlug($this->slugger->slug('Sixième séquence'));
            $this->entityManager->persist($sixth);

            $this->entityManager->flush();

            $message .= 'Evaluation Time ';
        }

        $subjectGroups = $this->entityManager->getRepository(SectionCategorySubjectGroup::class)->count();
        if($subjectGroups == 0) {
            $groups = [
                'Premier groupe' => SubjectGroupEnum::FIRST,
                'Deuxième groupe' => SubjectGroupEnum::SECOND,
                'Troisième groupe' => SubjectGroupEnum::THIRD,
                'Quatrième groupe' => SubjectGroupEnum::FOURTH,
            ];
            foreach ($groups as $name => $code) {
                $subjectGroup = (new SectionCategorySubjectGroup())
                    ->setName($name)
                    ->setCode($code);
                $this->entityManager->persist($subjectGroup);
            }
            $this->entityManager->flush();
            $message .= 'Subject Groups ';
          
        }


        $this->entityManager->flush();
        $io->success($message . 'Added');

        return Command::SUCCESS;
    }

    private function populateSchoolSectionsAndCategories()
    {
        $maternelle = (new StudyLevel())
                ->setName("Maternelle")
                ->setSlug($this->slugger->slug('Maternelle'));
            $this->entityManager->persist($maternelle);
            $this->entityManager->flush();
        $categories = [
            "Petite StudyLevel",
            "Moyenne StudyLevel",
            "Grande StudyLevel"
        ];
        foreach ($categories as $category) {
            $cat = (new StudyLevel())
                ->setName($category)
                ->setSlug($this->slugger->slug($category))
                ->setSection($maternelle);
            $this->entityManager->persist($cat);
        }


        $primaire = (new StudyLevel())
            ->setName("Primaire")
            ->setSlug($this->slugger->slug('Primaire'));
        $this->entityManager->persist($primaire);
        $this->entityManager->flush();
        $categories = [
            "SIL",
            "CP",
            "CE1",
            "CE2",
            "CM1",
            "CM2"
        ];
        foreach ($categories as $category) {
            $cat = (new StudyLevel())
                ->setName($category)
                ->setSlug($this->slugger->slug($category))
                ->setSection($primaire);
            $this->entityManager->persist($cat);
        }
        
        $secondaireGeneral = (new StudyLevel())
            ->setName("Secondaire General")
            ->setSlug($this->slugger->slug('Secondaire General'));
        $this->entityManager->persist($secondaireGeneral);
        $this->entityManager->flush();
        $categories = [
            "Sixième",
            "Cinquième",
            "Quatrième",
            "Troisième",
            "Seconde",
            "Première",
            "Terminale",
        ];
        foreach ($categories as $category) {
            $cat = (new StudyLevel())
                ->setName($category)
                ->setSlug($this->slugger->slug($category))
                ->setSection($secondaireGeneral);
            $this->entityManager->persist($cat);
        }

        $secondaireTechnique = (new StudyLevel())
            ->setName("Secondaire Technique")
            ->setSlug($this->slugger->slug('Secondaire Technique'));
        $this->entityManager->persist($secondaireTechnique);
        $this->entityManager->flush();


        $nursury = (new StudyLevel())
            ->setName("Nursury")
            ->setSlug($this->slugger->slug('Nursury'));
        $this->entityManager->persist($nursury);
        $this->entityManager->flush();
        $categories = [
            "Nursury 1",
            "Nursury 2",
            "Nursury 3"
        ];
        foreach ($categories as $category) {
            $cat = (new StudyLevel())
                ->setName($category)
                ->setSlug($this->slugger->slug($category))
                ->setSection($nursury);
            $this->entityManager->persist($cat);
        }

        $secondayGeneral = (new StudyLevel())
            ->setName("Secondary General")
            ->setSlug($this->slugger->slug('Secondary General'));
        $this->entityManager->persist($secondayGeneral);
        $this->entityManager->flush();
        $categories = [
            "Form 1",
            "Form 2",
            "Form 3",
            "Form 4",
            "Form 5",
            "Lower 6",
            "Upper 6"
        ];
        foreach ($categories as $category) {
            $cat = (new StudyLevel())
                ->setName($category)
                ->setSlug($this->slugger->slug($category))
                ->setSection($secondayGeneral);
            $this->entityManager->persist($cat);
        }

        $secondayTechnical = (new StudyLevel())
            ->setName("Secondary Technical")
            ->setSlug($this->slugger->slug('Secondary Technical'));
        $this->entityManager->persist($secondayTechnical);
        $this->entityManager->flush();
        

        $primary = (new StudyLevel())
            ->setName("Primary")
            ->setSlug($this->slugger->slug('Primary'));
        $this->entityManager->persist($primary);
        $this->entityManager->flush();
        $categories = [
            "Class 1",
            "Class 2",
            "Class 3",
            "Class 4",
            "Class 5",
            "Class 6",
            "Class 7",
            "Class 8"
        ];
        foreach ($categories as $category) {
            $cat = (new StudyLevel())
                ->setName($category)
                ->setSlug($this->slugger->slug($category))
                ->setSection($primary);
            $this->entityManager->persist($cat);
        }
    }

    
}
