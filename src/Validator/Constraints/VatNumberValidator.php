<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class VatNumberValidator extends ConstraintValidator
{
    // Based on https://en.wikipedia.org/wiki/VAT_identification_number
    private array $patterns = [
        'AT' => 'ATU[0-9]{8}',
        'BE' => 'BE[0,1]?[0-9]{9}',
        'BG' => 'BG[0-9]{9,10}',
        'CY' => 'CY[0-9]{8}[A-Z]',
        'CZ' => 'CZ[0-9]{8,10}',
        'DE' => 'DE[0-9]{9}',
        'DK' => 'DK[0-9]{8}',
        'EE' => 'EE[0-9]{9}',
        'EL' => 'EL[0-9]{9}', // Greece
        'ES' => 'ES[A-Z0-9][0-9]{7}[A-Z0-9]',
        'FI' => 'FI[0-9]{8}',
        'FR' => 'FR[A-Z0-9]{2}[0-9]{9}',
        'GB' => 'GB([0-9]{9}|[0-9]{12})',
        'HR' => 'HR[0-9]{11}', // Croatia
        'HU' => 'HU[0-9]{8}',
        'IE' => 'IE[0-9][A-Z0-9][0-9]{5}[A-Z]',
        'IT' => 'IT[0-9]{11}',
        'LT' => 'LT[0-9]{9,12}',
        'LU' => 'LU[0-9]{8}',
        'LV' => 'LV[0-9]{11}',
        'MT' => 'MT[0-9]{8}',
        'NL' => 'NL[0-9]{9}B[0-9]{2}',
        'PL' => 'PL[0-9]{10}',
        'PT' => 'PT[0-9]{9}',
        'RO' => 'RO[0-9]{2,10}',
        'SE' => 'SE[0-9]{12}',
        'SI' => 'SI[0-9]{8}',
        'SK' => 'SK[0-9]{10}',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof VatNumber) {
            throw new UnexpectedTypeException($constraint, VatNumber::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $value = strtoupper(str_replace([' ', '-', '.'], '', $value));
        $countryCode = substr($value, 0, 2);

        if (!isset($this->patterns[$countryCode])) {
            $this->addViolation($value, $constraint);
            return;
        }

        if (!preg_match('/^' . $this->patterns[$countryCode] . '$/', $value)) {
            $this->addViolation($value, $constraint);
            return;
        }

        $method = 'validate' . $countryCode;
        if (method_exists($this, $method)) {
            if (!$this->$method($value)) {
                $this->addViolation($value, $constraint);
            }
        }
    }

    private function addViolation(string $value, Constraint $constraint): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $value)
            ->addViolation();
    }

    private function validateFR(string $vatNumber): bool
    {
        $siren = substr($vatNumber, 4);
        $key = substr($vatNumber, 2, 2);

        // Only validate numeric keys, which is the most common case for companies.
        if (!ctype_digit($key) || !ctype_digit($siren)) {
            // For alphanumeric keys, we just check the format, which is already done.
            return true;
        }

        $calculatedKey = (12 + 3 * ($siren % 97)) % 97;

        return (int) $key === $calculatedKey;
    }

    private function validateDE(string $vatNumber): bool
    {
        $number = substr($vatNumber, 2);
        $p = 10;
        for ($i = 0; $i < 8; $i++) {
            $s = (int)$number[$i] + $p;
            $s %= 10;
            if ($s === 0) {
                $s = 10;
            }
            $p = (2 * $s) % 11;
        }

        $checksum = 11 - $p;
        if ($checksum === 10) {
            $checksum = 0;
        }

        return $checksum === (int) $number[8];
    }
}
