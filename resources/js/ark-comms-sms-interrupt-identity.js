export function smsMessageIdentity(row) {
    if (row?.kind !== 'sms' && row?.kind !== 'mms') {
        return '';
    }

    const coreId = Number(row.conversation_message_id ?? 0);

    if (Number.isFinite(coreId) && coreId > 0) {
        return `core:${coreId}`;
    }

    const platformId = String(row.platform_message_public_id ?? '').trim();

    if (platformId !== '' && ! /^\d+$/.test(platformId)) {
        return `platform:${platformId}`;
    }

    const fallbackId = Number(row.message_id ?? 0);

    if (Number.isFinite(fallbackId) && fallbackId > 0) {
        return `core:${fallbackId}`;
    }

    return '';
}

export function isPlatformOnlySmsInterrupt(message) {
    return smsMessageIdentity(message).startsWith('platform:');
}

export function interruptMessageKey(message) {
    if (! message) {
        return '';
    }

    if (message.kind === 'portal') {
        return String(message.portal_interrupt_key ?? message.message_id ?? '');
    }

    if (message.kind === 'website_lead') {
        return String(message.lead_interrupt_key ?? message.message_id ?? '');
    }

    return smsMessageIdentity(message);
}

export function coreConversationId(message) {
    const conversationId = Number(message?.conversation_id ?? 0);

    return Number.isFinite(conversationId) && conversationId > 0 ? conversationId : 0;
}

export function smsInterruptWouldDisplay(interrupt) {
    if (interrupt?.state !== 'unread') {
        return false;
    }

    if (interrupt?.kind !== 'sms' && interrupt?.kind !== 'mms') {
        return false;
    }

    return smsMessageIdentity(interrupt) !== '';
}

export function coreMarkReadUrl(interrupt, template) {
    const conversationId = coreConversationId(interrupt);

    if (! conversationId || ! template) {
        return '';
    }

    return template.replace('__CONVERSATION__', String(conversationId));
}
