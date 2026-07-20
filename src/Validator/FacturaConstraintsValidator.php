<?php

namespace App\Validator;

use App\Entity\Factura;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class FacturaConstraintsValidator extends ConstraintValidator
{
    public function __construct(private EntityManagerInterface $em) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        $this->facturaUnica($value, $constraint);
        $this->noClient($value, $constraint);
    }

    private function noClient($value, $constraint): bool
    {
        if (!$value->getPacient() && !$value->getClientPj()) {
            $this->context->buildViolation($constraint->messages['noClient'])->addViolation();
            return false;
        }

        return true;
    }

    private function facturaUnica($value, $constraint): bool
    {
        $factura = $this->em->getRepository(Factura::class)
            ->findOneBy([
                'serie' => $value->getSerie(),
                'numar' => $value->getNumar(),
                'data' => new \DateTime($value->getData()->format("Y-m-d"))
            ]);

        if ($factura) {
            $this->context->buildViolation($constraint->messages['facturaUnica'])->addViolation();
            return false;
        }

        return true;
    }
}
