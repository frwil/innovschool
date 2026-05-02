<?php

namespace App\Tests\Controller;

use App\Entity\SchoolClassPeriod;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SchoolClassControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $schoolClassRepository;
    private string $path = '/school/class/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->schoolClassRepository = $this->manager->getRepository(SchoolClassPeriod::class);

        foreach ($this->schoolClassRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('SchoolClassPeriod index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'school_class[name]' => 'Testing',
            'school_class[slug]' => 'Testing',
            'school_class[school]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->schoolClassRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolClassPeriod();
        $fixture->setName('My Title');
        $fixture->setSlug('My Title');
        $fixture->setSchool('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('SchoolClassPeriod');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolClassPeriod();
        $fixture->setName('Value');
        $fixture->setSlug('Value');
        $fixture->setSchool('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'school_class[name]' => 'Something New',
            'school_class[slug]' => 'Something New',
            'school_class[school]' => 'Something New',
        ]);

        self::assertResponseRedirects('/school/class/');

        $fixture = $this->schoolClassRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getSlug());
        self::assertSame('Something New', $fixture[0]->getSchool());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolClassPeriod();
        $fixture->setName('Value');
        $fixture->setSlug('Value');
        $fixture->setSchool('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/school/class/');
        self::assertSame(0, $this->schoolClassRepository->count([]));
    }
}
