/**
 * Rapid section-based Standard Vehicle Inspection capture.
 * Mutates via existing points.update JSON endpoint — no parallel authority.
 */
export function arkInspectionSectionWalk(config = {}) {
    const stages = (config.stages ?? []).map((stage) => ({
        ...stage,
        sections: (stage.sections ?? []).map((section) => ({
            ...section,
            points: (section.points ?? []).map((point) => hydratePoint(point)),
        })),
    }));

    return {
        csrf: config.csrf ?? '',
        stages,
        focusSectionKey: config.focusSectionKey ?? null,
        rearAxleBrakeType: config.rearAxleBrakeType ?? null,
        progress: {
            addressed: config.progress?.addressed ?? 0,
            total: config.progress?.total ?? 0,
            remaining: config.progress?.remaining ?? 0,
        },
        openSectionKeys: {},
        init() {
            const focus = this.focusSectionKey;
            this.stages.forEach((stage) => {
                stage.sections.forEach((section) => {
                    const open = focus
                        ? section.key === focus
                        : section.state !== 'complete';
                    this.openSectionKeys[section.key] = open;
                });
            });
            this.recomputeProgress();
        },

        isSectionOpen(key) {
            return !!this.openSectionKeys[key];
        },

        toggleSection(key) {
            this.openSectionKeys[key] = !this.openSectionKeys[key];
        },

        toggleExpand(point) {
            if (! this.shouldExpandForStatus(point, point.status)) {
                return;
            }
            point.expanded = !point.expanded;
        },

        shouldExpandForStatus(point, status) {
            if (! status) {
                return false;
            }
            const when = point.expand_when ?? ['monitor', 'needs_attention', 'failed'];

            return when.includes(status);
        },

        /**
         * Yellow/Red open documentation; Green collapses without clearing notes/photos/observations.
         */
        syncDisclosure(point) {
            point.expanded = this.shouldExpandForStatus(point, point.status);
        },

        toggleObservation(point, key) {
            if (!point.update_url || point.saveState === 'saving') {
                return;
            }
            const current = Array.isArray(point.selected_observations)
                ? [...point.selected_observations]
                : [];
            const index = current.indexOf(key);
            if (index >= 0) {
                current.splice(index, 1);
            } else {
                current.push(key);
            }
            point.selected_observations = current;
            point.expanded = true;
            this.saveObservations(point);
        },

        async saveObservations(point) {
            const ok = await this.patchPoint(point, {
                status: point.status ?? 'good',
                selected_observations: point.selected_observations ?? [],
            });
            if (ok) {
                this.applyFollowUp(point);
                this.recomputeProgress();
            }
        },

        recomputeProgress() {
            let addressed = 0;
            let total = 0;
            this.stages.forEach((stage) => {
                stage.sections.forEach((section) => {
                    let sectionAddressed = 0;
                    section.points.forEach((point) => {
                        total += 1;
                        if (point.addressed) {
                            addressed += 1;
                            sectionAddressed += 1;
                        }
                    });
                    section.addressed = sectionAddressed;
                    section.total = section.points.length;
                    section.state = sectionAddressed <= 0
                        ? 'not_started'
                        : (sectionAddressed >= section.total ? 'complete' : 'in_progress');
                    section.state_label = section.state === 'complete'
                        ? 'Complete'
                        : (section.state === 'in_progress' ? 'In progress' : 'Not started');
                });
                const stageAddressed = stage.sections.reduce((sum, s) => sum + s.addressed, 0);
                const stageTotal = stage.sections.reduce((sum, s) => sum + s.total, 0);
                stage.addressed = stageAddressed;
                stage.total = stageTotal;
                stage.state = stageAddressed <= 0
                    ? 'not_started'
                    : (stageAddressed >= stageTotal ? 'complete' : 'in_progress');
            });
            this.progress = {
                addressed,
                total,
                remaining: Math.max(0, total - addressed),
            };
            this.syncCoverageLabel();
        },

        syncCoverageLabel() {
            const el = document.querySelector('[data-inspection-coverage]');
            if (!el) {
                return;
            }
            const { addressed, total, remaining } = this.progress;
            if (total <= 0) {
                el.textContent = 'Not Started';
                return;
            }
            if (addressed <= 0) {
                el.textContent = `0 of ${total} checked · ${remaining} remaining`;
                return;
            }
            if (remaining > 0) {
                el.textContent = `${addressed} of ${total} checked · ${remaining} remaining`;
                return;
            }
            el.textContent = `${addressed} of ${total} checked`;
        },

        async setCondition(point, status) {
            if (!point.update_url || point.saveState === 'saving') {
                return;
            }
            if (point.road_test_finding_locked) {
                return;
            }
            if (point.road_test_force_na && status !== 'na') {
                return;
            }
            if (point.status === status && point.saveState === 'saved') {
                return;
            }

            const previous = point.status;
            point.status = status;
            point.status_label = displayLabelForPoint(point, status);
            // Expand Yellow/Red; collapse Green — never wipe notes/photos/observations.
            this.syncDisclosure(point);

            const payload = { status };
            if (point.is_axle_gate && this.rearAxleBrakeType) {
                payload.rear_axle_brake_type = this.rearAxleBrakeType;
            }

            const ok = await this.patchPoint(point, payload);
            if (!ok) {
                point.status = previous;
                point.status_label = displayLabelForPoint(point, previous);
                return;
            }

            this.applyFollowUp(point);
            this.recomputeProgress();
        },

        async setRearAxle(point, type) {
            this.rearAxleBrakeType = type;
            point.status = 'good';
            const ok = await this.patchPoint(point, {
                status: 'good',
                rear_axle_brake_type: type,
            });
            if (!ok) {
                return;
            }
            // Visibility of rear brake paths changes — reload host.
            window.location.reload();
        },

        scheduleSlots(point) {
            clearTimeout(point._slotTimer);
            point._slotTimer = setTimeout(() => {
                this.saveSlots(point);
            }, 450);
        },

        async saveSlots(point) {
            if (!point.update_url) {
                return;
            }

            const measurements = (point.measurement_slots ?? [])
                .filter((slot) => String(slot.value ?? '').trim() !== '')
                .map((slot) => ({
                    key: slot.key,
                    name: slot.name,
                    value: String(slot.value).trim(),
                    unit: slot.unit || null,
                }));

            if (measurements.length === 0 && !(point.status)) {
                return;
            }

            const payload = {
                status: point.status ?? 'good',
                measurements,
            };

            const ok = await this.patchPoint(point, payload);
            if (ok) {
                this.applyFollowUp(point);
                this.recomputeProgress();
            }
        },

        scheduleNote(point) {
            clearTimeout(point._noteTimer);
            point._noteTimer = setTimeout(() => {
                this.saveNote(point);
            }, 500);
        },

        async saveNote(point) {
            if (!point.update_url) {
                return;
            }
            const ok = await this.patchPoint(point, {
                status: point.status ?? 'good',
                note: point.note ?? '',
            });
            if (ok) {
                this.applyFollowUp(point);
                this.recomputeProgress();
            }
        },

        async retry(point) {
            if (point._lastPayload) {
                await this.patchPoint(point, point._lastPayload);
                this.applyFollowUp(point);
                this.recomputeProgress();
            }
        },

        applyFollowUp(point) {
            const follow = point._followUp;
            if (!follow) {
                return;
            }
            point.addressed = !!follow.addressed;
            point.missing_measurement_slots = follow.missing_measurement_slots ?? [];
            // Missing measurements stay on the compact card — do not open documentation panel.
            this.syncDisclosure(point);
        },

        async patchPoint(point, payload) {
            point.saveState = 'saving';
            point.saveError = null;
            point._lastPayload = payload;

            try {
                const body = new FormData();
                Object.entries(payload).forEach(([key, value]) => {
                    if (value === null || value === undefined) {
                        return;
                    }
                    if (key === 'measurements' && Array.isArray(value)) {
                        value.forEach((row, index) => {
                            Object.entries(row).forEach(([field, fieldValue]) => {
                                if (fieldValue !== null && fieldValue !== undefined) {
                                    body.append(`measurements[${index}][${field}]`, fieldValue);
                                }
                            });
                        });
                        return;
                    }
                    if (key === 'selected_observations' && Array.isArray(value)) {
                        body.append('selected_observations_json', JSON.stringify(value));
                        return;
                    }
                    body.append(key, value);
                });
                body.append('_method', 'PATCH');

                const response = await fetch(point.update_url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body,
                });

                if (!response.ok) {
                    point.saveState = 'error';
                    point.saveError = 'Save failed';
                    return false;
                }

                const data = await response.json();
                const living = data.living_record ?? {};
                if (living.status) {
                    point.status = living.status;
                    point.status_label = living.status_label ?? displayLabelForPoint(point, living.status);
                }
                if (Array.isArray(living.selected_observations)) {
                    point.selected_observations = living.selected_observations;
                }
                if (Array.isArray(living.measurement_slots)) {
                    point.measurement_slots = living.measurement_slots.map((slot) => ({
                        ...slot,
                        value: slot.value ?? '',
                    }));
                }
                if (Object.prototype.hasOwnProperty.call(living, 'note')) {
                    point.note = living.note ?? '';
                }
                if (Array.isArray(data.brake_prompts)) {
                    point.brake_prompts = data.brake_prompts;
                }
                point._followUp = data.follow_up ?? null;
                if (data.follow_up && typeof data.follow_up.addressed === 'boolean') {
                    point.addressed = data.follow_up.addressed;
                } else if (typeof living.addressed === 'boolean') {
                    point.addressed = living.addressed;
                }

                point.saveState = 'saved';
                clearTimeout(point._savedTimer);
                point._savedTimer = setTimeout(() => {
                    if (point.saveState === 'saved') {
                        point.saveState = 'idle';
                    }
                }, 1200);

                return true;
            } catch (error) {
                point.saveState = 'error';
                point.saveError = 'Save failed';
                return false;
            }
        },
    };
}

function hydratePoint(point) {
    const expandWhen = point.expand_when ?? ['monitor', 'needs_attention', 'failed'];
    const status = point.status ?? null;
    const expanded = !!status && expandWhen.includes(status);

    return {
        ...point,
        expanded,
        note: point.note ?? '',
        selected_observations: Array.isArray(point.selected_observations)
            ? [...point.selected_observations]
            : [],
        observation_options: point.observation_options ?? [],
        expand_when: expandWhen,
        measurement_slots: (point.measurement_slots ?? []).map((slot) => ({
            ...slot,
            value: slot.value ?? '',
        })),
        saveState: 'idle',
        saveError: null,
        _slotTimer: null,
        _noteTimer: null,
        _savedTimer: null,
        _lastPayload: null,
        _followUp: null,
    };
}

function displayLabelForPoint(point, status) {
    const options = point?.condition_options ?? [];
    const match = options.find((option) => option.value === status);
    if (match?.display) {
        return match.display;
    }
    switch (status) {
        case 'good':
            return 'Good';
        case 'monitor':
            return 'Monitor';
        case 'needs_attention':
            return 'Needs Attention';
        case 'failed':
            return 'Failed';
        case 'na':
            return 'N/A';
        default:
            return '—';
    }
}
