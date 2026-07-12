<?php

namespace App\Tests\Services;

use App\Services\FixturesEntityFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

class FixturesEntityFactoryTest extends TestCase
{
    public function testEnsureEntityCreatesNewEntityWhenNotFound()
    {
        $manager = $this->createMock(ObjectManager::class);
        $repo = $this->createMock(ObjectRepository::class);

        $manager->method('getRepository')->willReturn($repo);

        $repo->method('findOneBy')->willReturn(null);

        $manager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(DummyEntity::class));

        $factory = new FixturesEntityFactory();

        $entity = $factory->ensureEntity(
            $manager,
            DummyEntity::class,
            ['name' => 'test'],
            ['setName' => 'John']
        );

        $this->assertInstanceOf(DummyEntity::class, $entity);
        $this->assertEquals('John', $entity->getName());
    }

    public function testEnsureEntityUsesExistingEntity()
    {
        $manager = $this->createMock(ObjectManager::class);
        $repo = $this->createMock(ObjectRepository::class);

        $entity = new DummyEntity();
        $entity->setName('Existing');

        $manager->method('getRepository')->willReturn($repo);
        $repo->method('findOneBy')->willReturn($entity);

        $manager->expects($this->once())
            ->method('persist')
            ->with($entity);

        $factory = new FixturesEntityFactory();

        $result = $factory->ensureEntity(
            $manager,
            DummyEntity::class,
            ['name' => 'Existing'],
            ['setName' => 'Updated']
        );

        $this->assertSame($entity, $result);
        $this->assertEquals('Updated', $result->getName());
    }
}

class DummyEntity
{
    private string $name;

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
