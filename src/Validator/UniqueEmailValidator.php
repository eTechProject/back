<?php

namespace App\Validator;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {}

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEmail) {
            throw new UnexpectedTypeException($constraint, UniqueEmail::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $existingUser = $this->userRepository->findOneByEmail($value);
        
        if ($existingUser && 
            ($constraint->excludeUserId === null || $existingUser->getId() !== $constraint->excludeUserId)) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
