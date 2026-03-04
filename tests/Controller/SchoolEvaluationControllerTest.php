<?php

namespace App\Tests\Controller;

use App\Entity\SchoolEvaluation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SchoolEvaluationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $schoolEvaluationRepository;
    private string $path = '/school/evaluation/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->schoolEvaluationRepository = $this->manager->getRepository(SchoolEvaluation::class);

        foreach ($this->schoolEvaluationRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('SchoolEvaluation index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'school_evaluation[cretedAt]' => 'Testing',
            'school_evaluation[frame]' => 'Testing',
            'school_evaluation[time]' => 'Testing',
            'school_evaluation[period]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->schoolEvaluationRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolEvaluation();
        $fixture->setCretedAt('My Title');
        $fixture->setFrame('My Title');
        $fixture->setTime('My Title');
        $fixture->setPeriod('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('SchoolEvaluation');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolEvaluation();
        $fixture->setCretedAt('Value');
        $fixture->setFrame('Value');
        $fixture->setTime('Value');
        $fixture->setPeriod('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'school_evaluation[cretedAt]' => 'Something New',
            'school_evaluation[frame]' => 'Something New',
            'school_evaluation[time]' => 'Something New',
            'school_evaluation[period]' => 'Something New',
        ]);

        self::assertResponseRedirects('/school/evaluation/');

        $fixture = $this->schoolEvaluationRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getCretedAt());
        self::assertSame('Something New', $fixture[0]->getFrame());
        self::assertSame('Something New', $fixture[0]->getTime());
        self::assertSame('Something New', $fixture[0]->getPeriod());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new SchoolEvaluation();
        $fixture->setCretedAt('Value');
        $fixture->setFrame('Value');
        $fixture->setTime('Value');
        $fixture->setPeriod('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/school/evaluation/');
        self::assertSame(0, $this->schoolEvaluationRepository->count([]));
    }
}
