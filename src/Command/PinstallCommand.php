<?php

namespace App\Command;

use App\Contract\DefaultDataTitle;
use App\Contract\GenderEnum;
use App\Contract\SubjectGroupEnum;
use App\Entity\CoreUpdate;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\SchoolEvaluationTime;
use App\Entity\StudyLevel;
use App\Entity\SectionCategorySubject;
use App\Entity\SectionCategorySubjectGroup;
use App\Entity\User;
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
    name: 'app:pinstall',
    description: 'Install default data structure and data for the application',
)]
class PinstallCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
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
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@admin.com']);

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
            $this->entityManager->flush();
            $message .= 'Admin user ';
        }


        $coreUpdate = $this->entityManager->getRepository(CoreUpdate::class)->findOneBy([]);
        if (null == $coreUpdate) {
            $update = (new CoreUpdate())
                ->setVersion("0.0.0");
            $this->entityManager->persist($update);
            $this->entityManager->flush();
            $message .= 'Core Update ';
        }

        $primaire = $this->entityManager->getRepository(StudyLevel::class)->findOneBy(['slug' => $this->slugger->slug("Primaire")]);
        if (null === $primaire) {
            $datas = $this->getSectionsList();
            foreach ($datas as $key => $item) {
                $section = (new StudyLevel())->setName($item['name'])->setSlug($this->slugger->slug($item['name']));
                $this->entityManager->persist($section);
                $this->entityManager->flush();

                // classes
                foreach ($item[DefaultDataTitle::CLASSES] as $key => $classes) {
                    $classe = (new StudyLevel())
                        ->setName($classes['name'])
                        ->setSlug($this->slugger->slug($classes['name']))
                        ->setSection($section);
                    $this->entityManager->persist($classe);
                    $this->entityManager->flush();
                    // groups
                    foreach ($classes[DefaultDataTitle::GROUPS] as $key => $groups) {
                        $group = (new SectionCategorySubjectGroup())
                            ->setName($groups['name'])
                            ->setCode($groups['code'])
                            ->setSectionCategory($classe);
                        $this->entityManager->persist($group);
                        $this->entityManager->flush();

                        foreach ($groups[DefaultDataTitle::SUBJECTS] as $key => $subjectName) {
                            $subject = (new SectionCategorySubject())
                                ->setName($subjectName)
                                ->setSlug($this->slugger->slug($subjectName))
                                ->setSectionCategory($classe)
                                ->setSectionCategorySubjectGroup($group);
                            $this->entityManager->persist($subject);
                            $this->entityManager->flush();
                        }
                    }
                }
            }

            $message .= 'Sections subjects, groups ';
        }

        $evaluationFrame = $this->entityManager->getRepository(SchoolEvaluationFrame::class)->count();
        if ($evaluationFrame == 0) {
            $frames = [
                "Premier trimestre",
                "Deuxième trimestre",
                "Troisième trimestre",
            ];

            foreach ($frames as $key => $item) {
                $frame = (new SchoolEvaluationFrame())
                    ->setName($item)
                    ->setSlug($this->slugger->slug($item));
                $this->entityManager->persist($frame);
            }
            $this->entityManager->flush();
            $message .= 'Evaluation Frame ';
        }

        $evaluationTime = $this->entityManager->getRepository(SchoolEvaluationTime::class)->count();
        if ($evaluationTime == 0) {
            $times = [
                "Première séquence",
                "Deuxième séquence",
                "Troisième séquence",
                "Quatrième séquence",
                "Cinquième séquence",
                "Sixième séquence",
                "Septième séquence",
                "Huitième séquence",
                "Neuvième séquence",
                "Dixième séquence",
                "Onzième séquence",
                "Douzième séquence",
                "Treizième séquence",
                "Quatorzième séquence",
            ];

            foreach ($times as $key => $item) {
                $time = (new SchoolEvaluationTime())
                    ->setName($item)
                    ->setSlug($this->slugger->slug($item));
                $this->entityManager->persist($time);
            }
            $this->entityManager->flush();
            $message .= 'Evaluation Times ';
        }

        $io->success($message . 'Added');

        return Command::SUCCESS;
    }

    private function getSectionsList(): array
    {
        $data = [
            [
                "name" => "Primaire",
                DefaultDataTitle::CLASSES => [
                    [
                        "name" => "SIL",
                        DefaultDataTitle::GROUPS => [
                            [
                                "name" => 'PRATIQUER LES ACTIVITES ET ARTISTIQUES',
                                "code" => SubjectGroupEnum::FIRST,
                                DefaultDataTitle::SUBJECTS => [
                                    "Pratiquer les activités artistiques C6B"
                                ]
                            ],
                            [
                                "name" => "PRATIQUER LES ACTIVITES PHYSIQUES, SPORTIVES ET ARTISTIQUES",
                                "code" => SubjectGroupEnum::SECOND,
                                DefaultDataTitle::SUBJECTS => [
                                    "Pratiquer les activités physiques, sportives pour les apprenants aptes C6A1",
                                    "Pratiquer les activités physiques, sportives pour les apprenants Inaptes C6A2"
                                ]
                            ],
                            [
                                "name" => "UTILISER LES CONCEPTS ET LES OUTILS DE TECHNOLOGIES DE L'INFORMATION ET DE LA COMMUNICATION ",
                                "code" => SubjectGroupEnum::THIRD,
                                DefaultDataTitle::SUBJECTS => [
                                    "Utiliser les concepts de base et les outils des TIC C5"
                                ]
                            ],
                            [
                                "name" => "DEMONTRER L'AUTONOMIE, L'ESPRIT D'INITIATIVE, DE CREACTIVITE ET D' ENTREPRENEURIAT ",
                                "code" => SubjectGroupEnum::FOURTH,
                                DefaultDataTitle::SUBJECTS => [
                                    "Démontrer l'autonomie, l'esprit d'initiative, de créactivité et d'entrepreunariat C4"
                                ]
                            ],
                            [
                                "name" => "PRATIQUER LES VALEURS SOCIALES ET CITOYENNES",
                                "code" => SubjectGroupEnum::FIFTH,
                                DefaultDataTitle::SUBJECTS => [
                                    "Pratiquer les valeurs sociales C3a",
                                    "Pratiquer les valeurs citoyennes C3b"
                                ]
                            ],
                            [
                                "name" => "UTILISER LES NOTIONS DE BASE EN MATHEMATIQUES, SCIENCES ET TECHNOLOGIES",
                                "code" => SubjectGroupEnum::SIXTH,
                                DefaultDataTitle::SUBJECTS => [
                                    "Utiliser les notions de base en mathématiques C2a",
                                    "Utiliser les notions de base en sciences et technologies C2b"
                                ]
                            ],
                            [
                                "name" => "COMMUNIQUER EN FRANCAIS ET ANGLAIS ET PRATIQUER AU MOINS UNE LANGUE NATIONALE",
                                "code" => SubjectGroupEnum::SEVENTH,
                                DefaultDataTitle::SUBJECTS => [
                                    "COMMUNIQUER EN FRACAIS C1A",
                                    "Communicate in english C1b",
                                    "Pratiquer une langue nationale C1c"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
            ,
            [
                "name" => "Secondaire général",
                DefaultDataTitle::CLASSES => [
                    [
                        "name" => "6 eme",
                        DefaultDataTitle::GROUPS => [
                            [
                                "name" => 'ENSEIGNEMENT LITTERAIRE',
                                "code" => SubjectGroupEnum::FIRST,
                                DefaultDataTitle::SUBJECTS => [
                                    "ECM", "ETUDE DE TEXTE", "EXPRESSION ORALE", "HIST/GEO","ORTHOGRAPHE","ANGLAIS","EXPRESSION ECRITE"
                                ]
                            ],
                            [
                                "name" => "ENSEIGNEMENT SCIENTIFIQUE",
                                "code" => SubjectGroupEnum::SECOND,
                                DefaultDataTitle::SUBJECTS => [
                                    "SVT", "INFORMATIQUE", "MATHEMATIQUES",
                                ]
                            ],
                            [
                                "name" => "ENSEIGNEMENTS DIVERS",
                                "code" => SubjectGroupEnum::THIRD,
                                DefaultDataTitle::SUBJECTS => [
                                    "SPORTS", "ESF", "EVAI", "LCN","TRAVAIL MANUEL"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $data;
    }
}
