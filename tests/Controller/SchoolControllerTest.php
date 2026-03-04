<?php

namespace App\Tests\Controller;

use App\Entity\School;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SchoolControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $schoolRepository;
    private string $path = '/school/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->schoolRepository = $this->manager->getRepository(School::class);

        foreach ($this->schoolRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('School index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'school[name]' => 'Testing',
            'school[address]' => 'Testing',
            'school[contactName]' => 'Testing',
            'school[contactPhone]' => 'Testing',
            'school[contactEmail]' => 'Testing',
            'school[trialStartAt]' => 'Testing',
            'school[trialDuration]' => 'Testing',
            'school[createdAt]' => 'Testing',
            'school[fee]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->schoolRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new School();
        $fixture->setName('My Title');
        $fixture->setAddress('My Title');
        $fixture->setContactName('My Title');
        $fixture->setContactPhone('My Title');
        $fixture->setContactEmail('My Title');
        $fixture->setTrialStartAt('My Title');
        $fixture->setTrialDuration('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setFee('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('School');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new School();
        $fixture->setName('Value');
        $fixture->setAddress('Value');
        $fixture->setContactName('Value');
        $fixture->setContactPhone('Value');
        $fixture->setContactEmail('Value');
        $fixture->setTrialStartAt('Value');
        $fixture->setTrialDuration('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setFee('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'school[name]' => 'Something New',
            'school[address]' => 'Something New',
            'school[contactName]' => 'Something New',
            'school[contactPhone]' => 'Something New',
            'school[contactEmail]' => 'Something New',
            'school[trialStartAt]' => 'Something New',
            'school[trialDuration]' => 'Something New',
            'school[createdAt]' => 'Something New',
            'school[fee]' => 'Something New',
        ]);

        self::assertResponseRedirects('/school/');

        $fixture = $this->schoolRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getAddress());
        self::assertSame('Something New', $fixture[0]->getContactName());
        self::assertSame('Something New', $fixture[0]->getContactPhone());
        self::assertSame('Something New', $fixture[0]->getContactEmail());
        self::assertSame('Something New', $fixture[0]->getTrialStartAt());
        self::assertSame('Something New', $fixture[0]->getTrialDuration());
        self::assertSame('Something New', $fixture[0]->getCreatedAt());
        self::assertSame('Something New', $fixture[0]->getFee());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new School();
        $fixture->setName('Value');
        $fixture->setAddress('Value');
        $fixture->setContactName('Value');
        $fixture->setContactPhone('Value');
        $fixture->setContactEmail('Value');
        $fixture->setTrialStartAt('Value');
        $fixture->setTrialDuration('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setFee('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/school/');
        self::assertSame(0, $this->schoolRepository->count([]));
    }
}
