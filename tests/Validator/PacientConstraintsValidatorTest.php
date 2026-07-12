<?php

namespace App\Tests\Validator;

use App\Entity\Pacient;
use App\Repository\PacientRepository;
use App\Validator\PacientConstraints;
use App\Validator\PacientConstraintsValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class PacientConstraintsValidatorTest extends ConstraintValidatorTestCase
{
    private $repo;
    private $pacient;

    /**
     * @covers \App\Validator\PacientConstraintsValidator::__construct
     */
    public function testItCanBuildConstraintValidator()
    {
        $this->assertInstanceOf(PacientConstraintsValidator::class, $this->createValidator());
    }

    protected function createValidator()
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->getMockBuilder(PacientRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $em->method('getRepository')->willReturn($this->repo);

        $this->pacient = new Pacient();
        $this->pacient->setId(1);
        $this->pacient->setCnp('1212232354589');
        $this->pacient->setTara('Romania');

        return new PacientConstraintsValidator($em);
    }

    /**
     * @covers \App\Validator\PacientConstraintsValidator::validate
     * @covers \App\Validator\PacientConstraintsValidator::cnpIdUnic
     */
    public function testInvalidCnpUnicAddsViolation()
    {
        $pacient = $this->createMock(Pacient::class);
        $this->repo->method('findOneBy')->willReturn($pacient);

        $constraint = new PacientConstraints();

        $this->validator->validate(['cnp' => '1212232354589'], $constraint);

        $this->buildViolation($constraint->messages['cnpIdUnic'])
            ->assertRaised();
    }

    /**
     * @covers \App\Validator\PacientConstraintsValidator::validate
     * @covers \App\Validator\PacientConstraintsValidator::cnpIdUnic
     */
    public function testValidCnpUnicDoesNotAddViolation()
    {
        $constraint = new PacientConstraints();

        $this->validator->validate(['cnp' => '1790630060774', 'tara' => 'Romania'], $constraint);

        $this->assertNoViolation();
    }

    /**
     * @covers \App\Validator\PacientConstraintsValidator::validate
     * @covers \App\Validator\PacientConstraintsValidator::cnpIdUnic
     */
    public function testValidCnpUnicSameCnpDoesNotAddViolation()
    {
        $this->repo->method('findOneBy')->willReturn($this->pacient);

        $constraint = new PacientConstraints();

        $this->validator->validate($this->pacient, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @covers \App\Validator\PacientConstraintsValidator::validate
     * @covers \App\Validator\PacientConstraintsValidator::cnp
     * @dataProvider invalidDataProvider
     */
    public function testInvalidCnpInvalidAddsViolation($cnp, $tara)
    {
        $constraint = new PacientConstraints();

        $this->pacient->setCnp($cnp);
        $this->pacient->setTara($tara);
        $this->validator->validate($this->pacient, $constraint);

        $this->buildViolation($constraint->messages['cnp'])->assertRaised();
    }

    /**
     * @covers \App\Validator\PacientConstraintsValidator::validate
     * @covers \App\Validator\PacientConstraintsValidator::cnp
     * @dataProvider validDataProvider
     */
    public function testValidCnpDoesNotAddViolation($cnp, $tara)
    {
        $constraint = new PacientConstraints();

        $this->pacient->setCnp($cnp);
        $this->pacient->setTara($tara);
        $this->validator->validate($this->pacient, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @covers \App\Validator\PacientConstraintsValidator::validate
     */
    public function testWithNonExistentProperty()
    {
        $constraint = new PacientConstraints();

        $this->validator->validate(['none' => '42475870'], $constraint);

        $this->assertNoViolation();
    }

    protected function invalidDataProvider()
    {
        yield ['cnp' => '0234568978452', 'tara' => 'Romania'];
        yield ['cnp' => '92345689784521', 'tara' => 'Romania'];
        yield ['cnp' => 'a345689784521', 'tara' => 'Romania'];
    }

    protected function validDataProvider()
    {
        yield ['cnp' => '1790630060774', 'tara' => 'Romania'];
        yield ['cnp' => '5021027428362', 'tara' => 'Romania'];
        yield ['cnp' => '7021027424852', 'tara' => 'Romania'];
        yield ['cnp' => '1790630060774', 'tara' => 'Japonia'];
    }
}
