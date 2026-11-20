<?php

test('the js entrypoint registers the schedule appointment popover', function () {
    $source = file_get_contents(base_path('resources/js/app.js'));

    expect($source)->toContain("import arkScheduleAppointmentPopover from './ark-schedule-appointment-popover';")
        ->and($source)->toContain("Alpine.data('arkScheduleAppointmentPopover', arkScheduleAppointmentPopover);")
        ->and($source)->toContain("import { arkPaymentCapture } from './ark-payment-capture';")
        ->and($source)->toContain("Alpine.data('arkPaymentCapture', (config = {}) => arkPaymentCapture(config));")
        ->and($source)->toContain("import { arReceivePayment, portalAccountPay } from './ark-ar-receive-payment';")
        ->and($source)->toContain("Alpine.data('arReceivePayment', (config = {}) => arReceivePayment(config));");
});
