<?php

namespace App\Ark\Mail;

enum TransactionalMailOperation: string
{
    case EstimateSend = 'estimate.send';
    case InvoiceSend = 'invoice.send';
    case InspectionSend = 'inspection.send';
    case AppointmentSend = 'appointment.send';
    case DocumentSend = 'document.send';
    case PaymentLinkSend = 'payment_link.send';
    case DepositRequestSend = 'deposit_request.send';
    case ReviewRequestSend = 'review_request.send';
    case CustomerTransactionalMessage = 'customer.transactional_message';
    case AccountSystemMessage = 'account.system_message';
    case PortalSignIn = 'portal.sign_in';
    case EmployeeInvitation = 'employee.invitation';
    case BookingIdentityCode = 'booking.identity_code';
    case AccountEmailVerification = 'account.email_verification';
    case OwnerDigest = 'owner.digest';
    case CoachingDigest = 'coaching.digest';
    case ErrorReport = 'error.report';
}
