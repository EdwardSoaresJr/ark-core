<?php

namespace App\Ark\Operations\Messaging;

/**
 * Reads a carrier/provider delivery error into what the shop should do next. An explicit STOP reply is handled by
 * ProcessInboundSmsConsentAction; OptOut here is the provider saying the recipient already blocked this sender.
 */
final class SmsDeliveryFailureClassifier
{
    private const OPT_OUT = ['21610'];

    private const UNSUPPORTED = ['21614', '21612', '30006'];

    private const INVALID_DESTINATION = ['21211', '21217', '21401', '21421'];

    private const TEMPORARY = ['30001', '30003', '30005', '30008'];

    public function classify(?string $errorCode, ?string $detail = null): SmsDeliveryFailureClass
    {
        $code = trim((string) $errorCode);

        if (in_array($code, self::OPT_OUT, true)) {
            return SmsDeliveryFailureClass::OptOut;
        }

        if (in_array($code, self::UNSUPPORTED, true)) {
            return SmsDeliveryFailureClass::UnsupportedNumber;
        }

        if (in_array($code, self::INVALID_DESTINATION, true)) {
            return SmsDeliveryFailureClass::InvalidDestination;
        }

        if (in_array($code, self::TEMPORARY, true)) {
            return SmsDeliveryFailureClass::TemporaryFailure;
        }

        $haystack = strtolower(trim((string) $detail));

        if ($haystack !== '' && (str_contains($haystack, 'landline') || str_contains($haystack, 'not a mobile'))) {
            return SmsDeliveryFailureClass::UnsupportedNumber;
        }

        return SmsDeliveryFailureClass::Unknown;
    }

    public function staffGuidance(SmsDeliveryFailureClass $class, ?string $errorCode): string
    {
        $code = trim((string) $errorCode);
        $suffix = $code !== '' ? ' (Carrier error '.$code.')' : '';

        return match ($class) {
            SmsDeliveryFailureClass::OptOut => 'This number has opted out of texts'.$suffix.'. Do not text it again; call the customer or use email. Texts resume only if they reply START.',
            SmsDeliveryFailureClass::UnsupportedNumber => 'This number may not support text messages'.$suffix.'. Call the customer or use another number or email.',
            SmsDeliveryFailureClass::InvalidDestination => 'The phone number looks invalid'.$suffix.'. Confirm the number with the customer before texting again.',
            SmsDeliveryFailureClass::TemporaryFailure => 'The carrier could not deliver this text right now'.$suffix.'. Try sending it again later, or call the customer.',
            SmsDeliveryFailureClass::Unknown => 'The text was not delivered'.$suffix.'. Call the customer, or confirm the number before sending it again.',
        };
    }

    public function allowsRetry(SmsDeliveryFailureClass $class): bool
    {
        return ! in_array($class, [SmsDeliveryFailureClass::OptOut, SmsDeliveryFailureClass::UnsupportedNumber], true);
    }
}
