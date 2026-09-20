<?php

namespace App\Ark\Operations\Learn\Catalog;

use App\Ark\Runtime\Authorization\ArkRole;

final class LearnArkAdvisorArticles
{
    /**
     * @return list<array{slug: string, title: string, summary: string, view: string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'getting-started',
                'title' => 'Advisor basics',
                'summary' => 'Workboard rhythm, intake entry, and the RO lifecycle at a glance.',
                'view' => 'operations.learn.advisor.getting-started',
            ],
            [
                'slug' => 'workboard-lanes',
                'title' => 'Workboard lanes and triage',
                'summary' => 'Read pressure bands, move jobs, and pace the counter.',
                'view' => 'operations.learn.advisor.workboard-lanes',
            ],
            [
                'slug' => 'workspace-tabs',
                'title' => 'Workspace tabs',
                'summary' => 'Yellow bars, orange dots, locks — what tab signals mean.',
                'view' => 'operations.learn.advisor.workspace-tabs',
            ],
            [
                'slug' => 'advisor-intake',
                'title' => 'Service counter check-in',
                'summary' => 'Live search, VIN decode, duplicate guard, concern, open RO.',
                'view' => 'operations.learn.advisor.advisor-intake',
            ],
            [
                'slug' => 'customer-search',
                'title' => 'Customer search and recognition',
                'summary' => 'Live intake search, duplicate hints, and landing in the right hub.',
                'view' => 'operations.learn.advisor.customer-search',
            ],
            [
                'slug' => 'customer-hub',
                'title' => 'Customer Service Hub',
                'summary' => 'Work, vehicles, Comms timeline, and history — one relationship surface.',
                'view' => 'operations.learn.advisor.customer-hub',
            ],
            [
                'slug' => 'comms-queue',
                'title' => 'Comms Queue triage',
                'summary' => 'Interrupt vs recovery, attention gate, and morning scan rhythm.',
                'view' => 'operations.learn.advisor.comms-queue',
            ],
            [
                'slug' => 'ark-mobile-attention',
                'title' => 'ARK Mobile Attention',
                'summary' => 'Mobile triage slice, push alerts, check-in and comms on the lot.',
                'view' => 'operations.learn.advisor.ark-mobile-attention',
            ],
            [
                'slug' => 'ark-mobile-check-in',
                'title' => 'Mobile check-in and OBD',
                'summary' => 'Lane intake, VIN scan, iCar Pro codes, assign technician, open RO.',
                'view' => 'operations.learn.advisor.ark-mobile-check-in',
            ],
            [
                'slug' => 'incoming-calls-floor',
                'title' => 'Answering calls in ARK',
                'summary' => 'Centered screen pop, claim, SMS interrupt handoff, and intake.',
                'view' => 'operations.learn.advisor.incoming-calls-floor',
            ],
            [
                'slug' => 'texting-customers',
                'title' => 'Texting customers',
                'summary' => 'Inbound SMS interrupt, always-open hub composer, and remote sell rail.',
                'view' => 'operations.learn.advisor.texting-customers',
            ],
            [
                'slug' => 'scopes-and-intent',
                'title' => 'Scopes and recommendation intent',
                'summary' => 'Write the problem — not the repair — in the scope headline.',
                'view' => 'operations.learn.advisor.scopes-and-intent',
            ],
            [
                'slug' => 'repair-actions',
                'title' => 'Repair actions',
                'summary' => 'Scope vs repair action vs labor — what to write in each field.',
                'view' => 'operations.learn.advisor.repair-actions',
            ],
            [
                'slug' => 'parts-and-labor',
                'title' => 'Parts and labor entry',
                'summary' => 'Line fields, matrix pricing, labor categories, and sell authority.',
                'view' => 'operations.learn.advisor.parts-and-labor',
            ],
            [
                'slug' => 'parts-procurement',
                'title' => 'Parts status and waiting-parts',
                'summary' => 'Procurement fields, waiting-parts lane, release when parts land.',
                'view' => 'operations.learn.advisor.parts-procurement',
            ],
            [
                'slug' => 'inspection-to-aro',
                'title' => 'Inspection to sellable estimate',
                'summary' => 'Turn findings into scopes, repair actions, and deferred follow-up.',
                'view' => 'operations.learn.advisor.inspection-to-aro',
            ],
            [
                'slug' => 'estimate-review-mode',
                'title' => 'Review vs edit estimate',
                'summary' => 'Review posture, communication rail, and remote sell handoff.',
                'view' => 'operations.learn.advisor.estimate-review-mode',
            ],
            [
                'slug' => 'customer-authorization',
                'title' => 'Customer authorization',
                'summary' => 'Portal approve/defer, record authorization, disposition, and lifecycle advance.',
                'view' => 'operations.learn.advisor.customer-authorization',
            ],
            [
                'slug' => 'remote-sell',
                'title' => 'Remote sell after check-in',
                'summary' => 'Copy portal link, email PDF, SMS send, portal approval, and optional deposit.',
                'view' => 'operations.learn.advisor.remote-sell',
            ],
            [
                'slug' => 'lifecycle-transitions',
                'title' => 'Moving the RO through the shop',
                'summary' => 'Draft through close — who moves what and when.',
                'view' => 'operations.learn.advisor.lifecycle-transitions',
            ],
            [
                'slug' => 'deposits-and-invoicing',
                'title' => 'Deposits, invoice, and closeout',
                'summary' => 'Record deposits, generate invoice, email, close after handoff.',
                'view' => 'operations.learn.advisor.deposits-and-invoicing',
            ],
            [
                'slug' => 'portal-payment-links',
                'title' => 'Portal payment links',
                'summary' => 'Invoice balance pay links — not estimate deposits or remote sell links.',
                'view' => 'operations.learn.advisor.portal-payment-links',
            ],
            [
                'slug' => 'ro-printing',
                'title' => 'Printing from the RO',
                'summary' => 'Estimate PDF, tech sheet, key tag, oil sticker.',
                'view' => 'operations.learn.advisor.ro-printing',
            ],
            [
                'slug' => 'visit-posture',
                'title' => 'Visit posture and billing class',
                'summary' => 'Walk-in vs appointment, referral source, billing class impact.',
                'view' => 'operations.learn.advisor.visit-posture',
            ],
            [
                'slug' => 'soft-capacity-scheduling',
                'title' => 'Scheduling with soft capacity',
                'summary' => 'Book by shop capacity; bay and technician stay optional.',
                'view' => 'operations.learn.advisor.soft-capacity-scheduling',
            ],
            [
                'slug' => 'note-privacy',
                'title' => 'Customer-visible vs staff notes',
                'summary' => 'Line note privacy, scope notes, PDF and portal visibility.',
                'view' => 'operations.learn.advisor.note-privacy',
            ],
            [
                'slug' => 'labor-guides',
                'title' => 'Labor guide handoff',
                'summary' => 'AllData / ProDemand from RO toolbar; hours back into lines.',
                'view' => 'operations.learn.advisor.labor-guides',
            ],
            [
                'slug' => 'client-retention-growth',
                'title' => 'Client attrition and growth',
                'summary' => 'Why healthy shops always need new clients.',
                'view' => 'operations.learn.advisor.client-retention-growth',
            ],
            [
                'slug' => 'financial-literacy-basics',
                'title' => 'Financial literacy basics',
                'summary' => 'ARO, ELR, parts margin — what advisors should understand.',
                'view' => 'operations.learn.advisor.financial-literacy-basics',
            ],
        ];
    }

    public static function roleKey(): string
    {
        return ArkRole::Advisor->value;
    }
}
