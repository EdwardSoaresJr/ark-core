<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Presents operational meaning for an existing scope on the worksheet.
 *
 * Intake captures meaning once; this projection keeps it visible for every
 * scope — new and legacy — without re-running the intake control.
 *
 * Staff-only context belongs on Note / Supporting Note lines (is_private),
 * not on a parallel concern.notes Advisor Note field.
 */
final class RepairOrderConcernMeaningPresentation
{
    public function __construct(
        private readonly RepairOrderConcern $concern,
    ) {}

    public function entryKindLabel(): string
    {
        return $this->concern->entryKind()->staffLabel();
    }

    public function summary(): string
    {
        return $this->concern->summary;
    }

    public function customerWording(): ?string
    {
        $customerStates = trim((string) $this->concern->customer_states);

        if ($customerStates === '') {
            return null;
        }

        if ($this->normalized($customerStates) === $this->normalized($this->concern->summary)) {
            return null;
        }

        return $customerStates;
    }

    public function showsCustomerWording(): bool
    {
        return $this->customerWording() !== null;
    }

    public function showsMeaningStrip(): bool
    {
        return $this->showsCustomerWording();
    }

    private function normalized(string $value): string
    {
        return mb_strtolower(RepairOrderFreeText::normalize($value));
    }
}
