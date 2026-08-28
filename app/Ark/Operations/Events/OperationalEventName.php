<?php

namespace App\Ark\Operations\Events;

enum OperationalEventName: string
{
    case ConversationMessageReceived = 'conversation_message_received';
    case EncounterCreated = 'encounter_created';
    case EncounterCustomerResolved = 'encounter_customer_resolved';
    case EncounterVehicleResolved = 'encounter_vehicle_resolved';
    case EncounterVisitStarted = 'encounter_visit_started';
    case EncounterDismissed = 'encounter_dismissed';
    case RepairOrderCreated = 'repair_order_created';
    case RepairOrderLifecycleChanged = 'repair_order_lifecycle_changed';
    case RepairOrderPosted = 'repair_order_posted';
    case RepairOrderPaymentReceived = 'repair_order_payment_received';
    case RepairOrderTechnicianAssigned = 'repair_order_technician_assigned';
    case RepairActionOwnerChanged = 'repair_action_owner_changed';
    case RepairActionCommunicationUpdated = 'repair_action_communication_updated';
    case OperationalCommunicationLogged = 'operational_communication_logged';
    case EstimateLineAdded = 'estimate_line_added';
    case EstimateLineUpdated = 'estimate_line_updated';
    case EstimateLineDeleted = 'estimate_line_deleted';
    case ConcernCreated = 'concern_created';
    case ConcernDeleted = 'concern_deleted';
    case ConcernDispositionChanged = 'concern_disposition_changed';
    case ConcernMovedToNewRepairOrder = 'concern_moved_to_new_repair_order';
    case ConcernProductionStatusChanged = 'concern_production_status_changed';
    case FlagProductionRecognized = 'flag_production_recognized';
    case FlagProductionRecognitionDeferred = 'flag_production_recognition_deferred';
    case WorkAuthorizationCreated = 'work_authorization_created';
    case WorkAuthorizationCompleted = 'work_authorization_completed';
    case EstimateDocumentGenerated = 'estimate_document_generated';
    case EstimateDocumentRefreshed = 'estimate_document_refreshed';
    case EstimateEmailedToCustomer = 'estimate_emailed_to_customer';
    case EstimateSentWithMissingVin = 'estimate_sent_with_missing_vin';
    case RteRecommendationApplied = 'rte_recommendation_applied';
    case RteRecommendationOverridden = 'rte_recommendation_overridden';
    case InvoiceEmailedToCustomer = 'invoice_emailed_to_customer';
    case PartSourcingStarted = 'part_sourcing_started';
    case PartOrdered = 'part_ordered';
    case PartReceived = 'part_received';
    case PartBackordered = 'part_backordered';
    case PartInstalled = 'part_installed';
    case PartCanceled = 'part_canceled';
    case VehicleDecoded = 'vehicle_decoded';
    case StaffFrontDoorLanded = 'staff_front_door_landed';
    case PortalDocumentViewed = 'portal_document_viewed';
    case PortalDocumentDownloaded = 'portal_document_downloaded';
    case PortalVehicleViewed = 'portal_vehicle_viewed';
    case PortalActiveVisitViewed = 'portal_active_visit_viewed';
    case PortalCommunicationSectionViewed = 'portal_communication_section_viewed';
    case AppointmentScheduled = 'appointment_scheduled';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentArrived = 'appointment_arrived';
    case AppointmentCanceled = 'appointment_canceled';
    case AppointmentNoShow = 'appointment_no_show';
    case AppointmentCompleted = 'appointment_completed';
}
