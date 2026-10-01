import {
    coreConversationId,
    coreMarkReadUrl,
    interruptMessageKey,
    smsInterruptWouldDisplay,
    smsMessageIdentity,
} from '../../resources/js/ark-comms-sms-interrupt-identity.js';

const failures = [];

function assert(condition, message) {
    if (! condition) {
        failures.push(message);
    }
}

const hosted = {
    kind: 'sms',
    state: 'unread',
    direction: 'inbound',
    platform_message_public_id: '8f3c1d2a-1111-2222-3333-444444444444',
    conversation_id: 44,
    reply_url: '/app/conversations/44/reply?compose=text#conversation-composer',
};

const mirrored = {
    kind: 'sms',
    state: 'unread',
    direction: 'inbound',
    conversation_message_id: 91,
    conversation_id: 44,
    reply_url: '/app/conversations/44/reply?compose=text#conversation-composer',
};

const outbound = {
    ...hosted,
    direction: 'outbound',
    state: 'sent',
};

const missingIdentity = {
    kind: 'sms',
    state: 'unread',
    conversation_id: 44,
    reply_url: '/app/conversations/44/reply',
};

const template = '/app/api/conversations/__CONVERSATION__/read';

assert(smsInterruptWouldDisplay(hosted) === true, 'hosted unread SMS should display');
assert(smsInterruptWouldDisplay(mirrored) === true, 'mirrored unread SMS should display');
assert(smsInterruptWouldDisplay(outbound) === false, 'outbound SMS must not display as inbound popup');
assert(smsInterruptWouldDisplay(missingIdentity) === false, 'SMS without a message identity must not display');
assert(smsMessageIdentity(hosted) === 'platform:8f3c1d2a-1111-2222-3333-444444444444', 'platform public id is the hosted identity');
assert(smsMessageIdentity(mirrored) === 'core:91', 'core message id remains the mirrored identity');
assert(interruptMessageKey(hosted) === interruptMessageKey({ ...hosted }), 'duplicate provider events share one popup key');
assert(coreConversationId(hosted) === 44, 'dismissal uses Core conversation id');
assert(coreMarkReadUrl(hosted, template) === '/app/api/conversations/44/read', 'mark-read posts the numeric Core conversation id');
assert(coreMarkReadUrl({
    ...hosted,
    conversation_id: null,
}, template) === '', 'platform public id is never substituted into the Core mark-read URL');

if (coreMarkReadUrl(hosted, template).includes(hosted.platform_message_public_id)) {
    failures.push('mark-read URL leaked a Platform public id');
}

if (failures.length > 0) {
    console.error(failures.join('\n'));
    process.exit(1);
}

const payloadPath = process.argv[2];

if (payloadPath) {
    const { readFileSync } = await import('node:fs');
    const interrupt = JSON.parse(readFileSync(payloadPath, 'utf8'));
    const markReadUrl = coreMarkReadUrl(interrupt, template);

    if (! smsInterruptWouldDisplay(interrupt)) {
        console.error('browser would reject PHP payload');
        process.exit(1);
    }

    if (! smsMessageIdentity(interrupt).startsWith('platform:')) {
        console.error('browser did not use the Platform public id');
        process.exit(1);
    }

    if (! interrupt.reply_url) {
        console.error('payload has no conversation navigation');
        process.exit(1);
    }

    if (interrupt.platform_message_public_id && markReadUrl.includes(interrupt.platform_message_public_id)) {
        console.error('mark-read URL used a Platform public id');
        process.exit(1);
    }
}

console.log('hosted-sms-interrupt-identity ok');
