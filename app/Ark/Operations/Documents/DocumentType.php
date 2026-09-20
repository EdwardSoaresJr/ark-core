<?php

namespace App\Ark\Operations\Documents;

enum DocumentType: string
{
    case General = 'general';
    case ServiceRecord = 'service_record';
    case Warranty = 'warranty';
    case Registration = 'registration';
    case Insurance = 'insurance';
    case OutsideInvoice = 'outside_invoice';
    case Alignment = 'alignment';
    case Authorization = 'authorization';
    case DiagnosticReport = 'diagnostic_report';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::ServiceRecord => 'Service Record',
            self::Warranty => 'Warranty / Protection Plan',
            self::Registration => 'Registration',
            self::Insurance => 'Insurance',
            self::OutsideInvoice => 'Outside Invoice / Receipt',
            self::Alignment => 'Alignment / Measurement',
            self::Authorization => 'Authorization',
            self::DiagnosticReport => 'Diagnostic Report',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
