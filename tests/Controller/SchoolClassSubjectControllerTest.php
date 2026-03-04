<?php

namespace App\Tests\Controller;

use App\Entity\SchoolClassSubject;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SchoolClassSubjectControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $schoolClassSubjectRepository;
    private string $path = '/school/class/subject/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->schoolClassSubjectRepository = $this->manager->getRepository(SchoolClassSubject::class);

        foreach ($this->schoolClassSubjectRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('SchoolClassSubject index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'school_class_subject[name]' => 'Testing',
            'school_class_subject[slug]' => 'Testing',
            'school_class_subject[school]' => 'Testing',
            'school_class_subject[schoolClassPeriod]' => 'Testing',
            'school_class_subject[period]' => 'Testing',
            'school_class_subject[teacher]' => 'Testing',
            'school_class_subject[sectionCategorySubject]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->schoolClassSubjectRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolClassSubject();
        $fixture->setName('My Title');
        $fixture->setSlug('My Title');
        $fixture->setSchool('My Title');
        $fixture->setSchoolClass('My Title');
        $fixture->setPeriod('My Title');
        $fixture->setTeacher('My Title');
        $fixture->setSectionCategorySubject('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('SchoolClassSubject');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolClassSubject();
        $fixture->setName('Value');
        $fixture->setSlug('Value');
        $fixture->setSchool('Value');
        $fixture->setSchoolClass('Value');
        $fixture->setPeriod('Value');
        $fixture->setTeacher('Value');
        $fixture->setSectionCategorySubject('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'school_class_subject[name]' => 'Something New',
            'school_class_subject[slug]' => 'Something New',
            'school_class_subject[school]' => 'Something New',
            'school_class_subject[schoolClassPeriod]' => 'Something New',
            'school_class_subject[period]' => 'Something New',
            'school_class_subject[teacher]' => 'Something New',
            'school_class_subject[sectionCategorySubject]' => 'Something New',
        ]);

        self::assertResponseRedirects('/school/class/subject/');

        $fixture = $this->schoolClassSubjectRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getSlug());
        self::assertSame('Something New', $fixture[0]->getSchool());
        self::assertSame('Something New', $fixture[0]->getSchoolClass());
        self::assertSame('Something New', $fixture[0]->getPeriod());
        self::assertSame('Something New', $fixture[0]->getTeacher());
        self::assertSame('Something New', $fixture[0]->getSectionCategorySubject());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolClassSubject();
        $fixture->setName('Value');
        $fixture->setSlug('Value');
        $fixture->setSchool('Value');
        $fixture->setSchoolClass('Value');
        $fixture->setPeriod('Value');
        $fixture->setTeacher('Value');
        $fixture->setSectionCategorySubject('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/school/class/subject/');
        self::assertSame(0, $this->schoolClassSubjectRepository->count([]));
    }
}
