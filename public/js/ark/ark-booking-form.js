/*
 * Online booking form: one step at a time, fields that follow the chosen service, and days and
 * times from /book/availability. Without this script every section shows and the server still
 * validates everything.
 */
(function () {
    'use strict';

    var inFrame = window.parent && window.parent !== window;

    function post(message) {
        if (inFrame) {
            window.parent.postMessage(Object.assign({ source: 'ark-booking' }, message), '*');
        }
    }

    function $(root, selector) { return root.querySelector(selector); }
    function $$(root, selector) { return Array.prototype.slice.call(root.querySelectorAll(selector)); }

    function initReceipt(receipt) {
        var heading = $(receipt, '[data-receipt-heading]');
        if (heading) { heading.focus(); }
        post({ type: 'ark-book:done', status: receipt.getAttribute('data-status') });
        $$(receipt, '[data-booking-close]').forEach(function (button) {
            button.addEventListener('click', function () { post({ type: 'ark-book:close' }); });
        });
    }

    function initForm(card) {
        var form = $(card, '[data-booking-form]');
        if (!form) { return; }

        var url = card.getAttribute('data-availability-url');
        var live = $(form, '[data-booking-live]');
        var nav = $(form, '[data-booking-nav]');
        var back = $(form, '[data-booking-back]');
        var next = $(form, '[data-booking-next]');
        var progress = $(form, '[data-booking-progress]');
        var submit = $(form, '[data-booking-submit]');
        var allSteps = $$(form, '[data-book-step]');
        // What availability last said (the page as rendered, then each refresh). A service's own
        // mode is only a guess until availability answers for it.
        var availabilityMode = form.getAttribute('data-availability-mode');
        var current = 0;
        var refreshTimer = null;

        var MODE_NOTES = {
            request: '<strong>This is a request, not a confirmed appointment.</strong> Choose when works for you. The shop confirms the exact time with you.',
            instant: '<strong>You are booking a confirmed appointment.</strong> Pick an open time and it is reserved for you.'
        };
        var FINAL_NOTES = {
            request: 'When you press <strong>Send appointment request</strong>, the shop receives your request. Your appointment is <strong>not confirmed</strong> until the shop contacts you.',
            instant: 'When you press <strong>Book this appointment</strong>, the time you picked is reserved for you.'
        };

        function selectedService() {
            return $(form, 'input[name="service_id"]:checked');
        }

        function locationMode() {
            var service = selectedService();
            return service ? service.getAttribute('data-location-mode') : 'shop';
        }

        function chosenLocation() {
            var checked = $(form, 'input[name="location"]:checked');
            return checked ? checked.value : 'shop';
        }

        function filledVehicles() {
            var count = $$(form, '[data-vehicle-row]').filter(function (row) {
                return $$(row, 'input').some(function (input) { return input.value.trim() !== ''; });
            }).length;
            return Math.max(1, count);
        }

        function steps() {
            return allSteps.filter(function (step) {
                if (step.hasAttribute('data-location-step')) {
                    return locationMode() !== 'shop';
                }
                if (step.getAttribute('data-book-step') === 'questions') {
                    return $$(step, '[data-question]').some(function (q) { return !q.hidden; });
                }
                return true;
            });
        }

        function setEnabled(container, enabled) {
            $$(container, 'input, select, textarea').forEach(function (el) { el.disabled = !enabled; });
        }

        function applyService() {
            var service = selectedService();
            var id = service ? service.value : '';

            $$(form, '[data-service-options]').forEach(function (box) {
                var active = box.getAttribute('data-service-options') === id;
                box.hidden = !active;
                var select = $(box, 'select');
                select.disabled = !active;
                select.required = active;
            });

            var concern = $(form, '#book-concern');
            if (concern) {
                var prompt = service ? service.getAttribute('data-concern-prompt') : '';
                concern.placeholder = prompt || concern.getAttribute('data-default-placeholder') || '';
                var option = id ? $(form, '[data-service-options="' + id + '"] select') : null;
                var picked = option && option.selectedOptions[0];
                var needsDetails = picked && picked.getAttribute('data-requires-details') === '1';
                if (!concern.hasAttribute('data-base-required')) {
                    concern.setAttribute('data-base-required', concern.required ? '1' : '0');
                }
                concern.required = concern.getAttribute('data-base-required') === '1' || !!needsDetails;
            }

            var mode = locationMode();
            var shopChip = $(form, '[data-location-option="shop"]');
            if (shopChip) {
                shopChip.hidden = mode === 'mobile';
                if (mode === 'mobile' && chosenLocation() === 'shop') {
                    var site = $(form, 'input[name="location"][value="customer_site"]');
                    if (site) { site.checked = true; }
                }
            }
            var choice = $(form, '[data-location-choice]');
            if (choice) {
                choice.hidden = mode === 'shop';
                if (mode === 'shop') {
                    var shop = $(form, 'input[name="location"][value="shop"]');
                    if (shop) { shop.checked = true; }
                }
            }
            applyLocation();

            $$(form, '[data-question]').forEach(function (question) {
                var ids = (question.getAttribute('data-services') || '').split(',').filter(Boolean);
                var applies = ids.length === 0 || ids.indexOf(id) !== -1;
                question.hidden = !applies;
                setEnabled(question, applies);
            });

            if (service) {
                applyMode(availabilityMode || service.getAttribute('data-booking-mode'));
            }
        }

        function applyLocation() {
            var onSite = locationMode() !== 'shop' && chosenLocation() !== 'shop';
            var address = $(form, '[data-address-fields]');
            if (!address) { return; }
            address.hidden = !onSite;
            setEnabled(address, onSite);
            $$(address, '[data-required-on-site]').forEach(function (el) { el.required = onSite; });
        }

        function applyMode(mode) {
            var instant = mode === 'instant';
            var note = $(card, '[data-booking-mode-note]');
            if (note) { note.innerHTML = MODE_NOTES[instant ? 'instant' : 'request']; }
            var finalNote = $(form, '[data-booking-final-note]');
            if (finalNote) { finalNote.innerHTML = FINAL_NOTES[instant ? 'instant' : 'request']; }
            if (submit) {
                submit.textContent = submit.getAttribute(instant ? 'data-label-instant' : 'data-label-request');
            }
            var periods = $(form, '[data-booking-periods]');
            var slots = $(form, '[data-booking-slots]');
            if (periods) {
                periods.hidden = instant;
                $$(periods, 'input').forEach(function (el) { el.disabled = instant; el.required = !instant; });
            }
            if (slots) {
                slots.hidden = !instant;
                var time = $(slots, 'select');
                time.disabled = !instant;
                time.required = instant;
            }
            var hint = $(form, '[data-when-hint]');
            if (hint) {
                hint.textContent = instant
                    ? 'Only open times are shown, in the shop\'s local time.'
                    : 'Pick a day and a time of day. This is your preference; the shop confirms the exact time.';
            }
        }

        function scheduleRefresh() {
            window.clearTimeout(refreshTimer);
            refreshTimer = window.setTimeout(refresh, 250);
        }

        function refresh() {
            if (!url || typeof fetch !== 'function') { return; }
            var service = selectedService();
            var option = service ? $(form, '[data-service-options="' + service.value + '"] select') : null;
            var postal = $(form, 'input[name="address[postal_code]"]');
            var date = $(form, '[data-booking-date]');
            var params = new URLSearchParams({
                service_id: service ? service.value : '',
                service_option: option ? option.value : '',
                vehicles: String(filledVehicles()),
                location: chosenLocation(),
                postal_code: postal && !postal.disabled ? postal.value : '',
                date: date ? date.value : ''
            });

            fetch(url + '?' + params.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) { render(data); } })
                .catch(function () {});
        }

        function render(data) {
            var date = $(form, '[data-booking-date]');
            if (date) {
                date.innerHTML = '';
                if (!data.dates.some(function (row) { return row.available; })) {
                    var none = document.createElement('option');
                    none.value = '';
                    none.textContent = 'No days available';
                    none.selected = true;
                    date.appendChild(none);
                }
                data.dates.forEach(function (row) {
                    var option = document.createElement('option');
                    option.value = row.date;
                    option.textContent = row.available ? row.label : row.label + ' - ' + row.reason;
                    option.disabled = !row.available;
                    option.selected = row.date === data.selected_date;
                    date.appendChild(option);
                });
            }

            var periodBox = $(form, '[data-period-options]');
            if (periodBox) {
                var previous = $(form, 'input[name="preferred_period"]:checked');
                var keep = previous ? previous.value : null;
                periodBox.innerHTML = '';
                data.periods.forEach(function (period, index) {
                    var label = document.createElement('label');
                    label.className = 'ark-booking__chip';
                    var input = document.createElement('input');
                    input.type = 'radio';
                    input.name = 'preferred_period';
                    input.value = period.value;
                    input.checked = keep ? keep === period.value : index === 0;
                    var span = document.createElement('span');
                    span.textContent = period.label;
                    label.appendChild(input);
                    label.appendChild(document.createTextNode(' '));
                    label.appendChild(span);
                    periodBox.appendChild(label);
                });
            }

            var time = $(form, '[data-booking-time]');
            if (time) {
                var keepTime = time.value;
                time.innerHTML = '';
                if (data.slots.length === 0) {
                    var none = document.createElement('option');
                    none.value = '';
                    none.textContent = 'No open times that day';
                    time.appendChild(none);
                }
                data.slots.forEach(function (slot) {
                    var option = document.createElement('option');
                    option.value = slot.value;
                    option.textContent = slot.label;
                    option.selected = slot.value === keepTime;
                    time.appendChild(option);
                });
            }

            availabilityMode = data.mode;
            applyMode(data.mode);

            var notice = $(form, '[data-booking-notice]');
            if (notice) {
                notice.textContent = data.notice || '';
                notice.hidden = !data.notice;
            }

            var hint = $(form, '[data-when-hint]');
            if (hint && !data.accepting && data.message) {
                hint.textContent = data.message;
            }
        }

        function renderProgress() {
            if (!progress) { return; }
            var visible = steps();
            progress.innerHTML = '';
            visible.forEach(function (step, index) {
                var item = document.createElement('li');
                item.textContent = step.getAttribute('data-step-label') || '';
                item.className = index < current ? 'is-done' : (index === current ? 'is-current' : '');
                if (index === current) { item.setAttribute('aria-current', 'step'); }
                progress.appendChild(item);
            });
        }

        function show(index, focus) {
            var visible = steps();
            current = Math.max(0, Math.min(index, visible.length - 1));
            allSteps.forEach(function (step) { step.hidden = visible.indexOf(step) !== current; });
            back.hidden = current === 0;
            next.hidden = current === visible.length - 1;
            renderProgress();
            var step = visible[current];
            if (step.getAttribute('data-book-step') === 'review') { summarize(); }
            if (live) {
                live.textContent = 'Step ' + (current + 1) + ' of ' + visible.length + ': ' + ($(step, '.ark-booking__step-title') || {}).textContent;
            }
            if (focus) {
                var heading = $(step, '.ark-booking__step-title');
                if (heading) {
                    heading.setAttribute('tabindex', '-1');
                    heading.focus();
                }
            }
            post({ type: 'ark-book:step', step: step.getAttribute('data-book-step') });
        }

        function stepIsValid(step) {
            var fields = $$(step, 'input, select, textarea').filter(function (el) {
                return !el.disabled && el.offsetParent !== null && typeof el.checkValidity === 'function';
            });
            for (var i = 0; i < fields.length; i++) {
                if (!fields[i].checkValidity()) {
                    fields[i].reportValidity();
                    return false;
                }
            }
            return true;
        }

        function text(selector) {
            var el = $(form, selector);
            if (!el || el.disabled) { return ''; }
            if (el.tagName === 'SELECT') { return el.selectedOptions[0] ? el.selectedOptions[0].textContent.trim() : ''; }
            return el.value.trim();
        }

        function summarize() {
            var list = $(form, '[data-booking-summary]');
            if (!list) { return; }
            var rows = [];
            var service = selectedService();
            if (service) {
                var name = service.closest('label').querySelector('.ark-booking__service-name').textContent.trim();
                var option = $(form, '[data-service-options="' + service.value + '"] select');
                rows.push(['Service', option && option.value ? name + ' · ' + option.value : name]);
            }
            var choice = $(form, 'input[name="concern_choice"]:checked');
            var concern = text('#book-concern');
            if (choice || concern) { rows.push(['Concern', [choice ? choice.value : '', concern].filter(Boolean).join(' - ')]); }
            var vehicles = $$(form, '[data-vehicle-row]').map(function (row) {
                return $$(row, 'input').map(function (i) { return i.value.trim(); }).filter(Boolean).join(' ');
            }).filter(Boolean);
            if (vehicles.length) { rows.push([vehicles.length > 1 ? 'Vehicles' : 'Vehicle', vehicles.join('; ')]); }
            if (locationMode() !== 'shop' && chosenLocation() !== 'shop') {
                rows.push(['Where', [text('#address-line1'), text('#address-city'), text('#address-postal')].filter(Boolean).join(', ')]);
            }
            var when = text('[data-booking-date]');
            var period = $(form, 'input[name="preferred_period"]:checked');
            var time = $(form, '[data-booking-time]');
            if (time && !time.disabled) {
                when += ' at ' + text('[data-booking-time]');
            } else if (period) {
                when += ' · ' + period.parentNode.textContent.trim();
            }
            rows.push(['When', when]);
            rows.push(['Contact', [text('#book-name'), text('#book-phone'), text('#book-email')].filter(Boolean).join(' · ')]);

            list.innerHTML = '';
            rows.forEach(function (row) {
                var wrap = document.createElement('div');
                var dt = document.createElement('dt');
                var dd = document.createElement('dd');
                dt.textContent = row[0];
                dd.textContent = row[1];
                wrap.appendChild(dt);
                wrap.appendChild(dd);
                list.appendChild(wrap);
            });
            list.hidden = false;
        }

        // Extra vehicle rows stay folded until asked for.
        var addVehicle = $(form, '[data-add-vehicle]');
        $$(form, '[data-vehicle-extra]').forEach(function (row) { row.hidden = true; });
        if (addVehicle) {
            addVehicle.hidden = $$(form, '[data-vehicle-extra]').length === 0;
            addVehicle.addEventListener('click', function () {
                var hidden = $$(form, '[data-vehicle-extra]').filter(function (row) { return row.hidden; });
                if (hidden[0]) {
                    hidden[0].hidden = false;
                    var first = $(hidden[0], 'input');
                    if (first) { first.focus(); }
                }
                addVehicle.hidden = hidden.length <= 1;
            });
        }

        form.addEventListener('change', function (event) {
            var target = event.target;
            if (target.name === 'service_id' || (target.name === 'service_option')) {
                availabilityMode = null;
                applyService();
                scheduleRefresh();
            } else if (target.name === 'location') {
                applyLocation();
                scheduleRefresh();
            } else if (target.matches('[data-booking-date]')) {
                scheduleRefresh();
            } else if (target.closest('[data-vehicle-row]') || target.matches('[data-availability-input]')) {
                scheduleRefresh();
            }
        });

        form.addEventListener('keydown', function (event) {
            var target = event.target;
            if (event.key !== 'Enter' || target.tagName === 'TEXTAREA' || target.tagName === 'BUTTON') { return; }
            event.preventDefault();
            if (!next.hidden) { next.click(); }
        });

        next.addEventListener('click', function () {
            if (stepIsValid(steps()[current])) { show(current + 1, true); }
        });
        back.addEventListener('click', function () { show(current - 1, true); });

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            var intent = submitter ? submitter.value : 'submit';
            if (intent === 'submit' && submit) {
                if (submit.getAttribute('aria-busy') === 'true') {
                    event.preventDefault();
                    return;
                }
                submit.setAttribute('aria-busy', 'true');
                submit.disabled = true;
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'book_intent';
                hidden.value = 'submit';
                form.appendChild(hidden);
            }
        });

        applyService();

        nav.hidden = false;
        progress.hidden = false;
        form.classList.add('is-stepped');

        var firstError = $(form, '[aria-invalid="true"], .ark-booking__error');
        var resume = $(form, '[data-booking-resume]');
        var start = 0;
        if (firstError || resume) {
            var owner = firstError ? firstError.closest('[data-book-step]') : resume;
            var index = steps().indexOf(owner);
            if (index >= 0) { start = index; }
        } else if (selectedService() && steps()[0] && steps()[0].getAttribute('data-book-step') === 'service') {
            var preselectedOption = $(form, '[data-service-options="' + selectedService().value + '"] select');
            start = preselectedOption && preselectedOption.value === '' ? 0 : 1;
        }
        show(start, false);

        var alert = $(card, '[data-booking-errors]');
        if (alert) { alert.focus(); }
    }

    function init() {
        $$(document, '[data-ark-booking-receipt]').forEach(initReceipt);
        $$(document, '[data-ark-booking]').forEach(initForm);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { post({ type: 'ark-book:escape' }); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
