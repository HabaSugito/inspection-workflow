<?php

namespace App\Exceptions;

use App\Enums\InspectionStatus;
use DomainException;

class InvalidTransition extends DomainException
{
    public function __construct(
        public readonly InspectionStatus $currentStatus,
        public readonly ?string $allowedAction,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notAllowedIn(InspectionStatus $status): self
    {
        $label = str_replace('_', ' ', $status->value);
        $allowed = $status->nextAction();

        return new self(
            $status,
            $allowed,
            $allowed === null
                ? "This inspection is {$label}. No further action is allowed."
                : "This inspection is {$label}. The only action allowed is: {$allowed}.",
        );
    }

    public static function noInspector(InspectionStatus $status): self
    {
        return new self($status, null, 'This inspection has no inspector, so it cannot be started.');
    }
}
