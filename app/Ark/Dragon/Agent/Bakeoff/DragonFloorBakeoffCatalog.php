<?php

namespace App\Ark\Dragon\Agent\Bakeoff;

/**
 * Frozen Demo Auto Repair floor set. Not synthetic. Human judgment still owns “competent employee.”
 *
 * @phpstan-type Task array{
 *     id: string,
 *     family: string,
 *     prompt: string,
 *     expected_tools: list<string>,
 *     preserve_source: ?string,
 *     notes: string
 * }
 */
final class DragonFloorBakeoffCatalog
{
    public const VERSION = 'v1';

    public const SCORE_AXES = [
        'tool_choice',
        'initiative',
        'factual_accuracy',
        'hallucination',
        'usefulness',
        'advisor_tone',
        'latency',
        'cost',
    ];

    /**
     * Cutover question. Not “who won more rows.”
     */
    public const ACCEPTANCE = 'Does ARK-hosted Dragon feel materially more like a competent employee than arkai/Qwen?';

    /**
     * @return list<Task>
     */
    public static function tasks(): array
    {
        return [
            [
                'id' => '01_shop_today',
                'family' => 'awareness',
                'prompt' => 'How did the shop do today?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => 'Board truth, not vibes. Counts must come from tools.',
            ],
            [
                'id' => '02_ugly',
                'family' => 'awareness',
                'prompt' => 'What looks ugly today?',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => 'Stale, unassigned, waiting approval, buried techs.',
            ],
            [
                'id' => '03_landon',
                'family' => 'people',
                'prompt' => 'What does Landon have?',
                'expected_tools' => ['repair_orders.search'],
                'preserve_source' => null,
                'notes' => 'Technician load. Do not invent jobs.',
            ],
            [
                'id' => '04_molly',
                'family' => 'people',
                'prompt' => 'What should Molly focus on?',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => 'Advisor/estimator work: approvals, estimates, counter.',
            ],
            [
                'id' => '05_edward',
                'family' => 'people',
                'prompt' => 'What should Edward focus on?',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => 'Owner/tech/estimator mix. Do not single-role him.',
            ],
            [
                'id' => '06_subaru',
                'family' => 'vehicle',
                'prompt' => 'Tell me about the Subaru.',
                'expected_tools' => ['repair_orders.search', 'repair_orders.get'],
                'preserve_source' => null,
                'notes' => 'If several Subarus, ask which or list them. No customer PII.',
            ],
            [
                'id' => '07_worry',
                'family' => 'awareness',
                'prompt' => 'What should I worry about?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => 'Aging, waiting approval, unassigned. Not generic anxiety.',
            ],
            [
                'id' => '08_better_shop',
                'family' => 'ops',
                'prompt' => 'How can we make the shop better?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => 'Ground advice in today’s board, not consulting-speak.',
            ],
            [
                'id' => '09_make_money',
                'family' => 'ops',
                'prompt' => 'Make more money.',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => 'Approvals waiting, stale cars, estimator work. No invented dollars or P&L.',
            ],
            [
                'id' => '10_what_can_you_see',
                'family' => 'capability',
                'prompt' => 'What can you see?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => 'Must try tools. Must not say ARK is disconnected.',
            ],
            [
                'id' => '11_diagnose_how',
                'family' => 'knowledge',
                'prompt' => 'What do you know about how we diagnose cars?',
                'expected_tools' => ['memory.recall', 'knowledge.search'],
                'preserve_source' => null,
                'notes' => 'Taught memory + ARKademy. Do not invent SOPs.',
            ],
            [
                'id' => '12_website_diagnostics',
                'family' => 'knowledge',
                'prompt' => 'What does our website say about diagnostics?',
                'expected_tools' => ['knowledge.search'],
                'preserve_source' => null,
                'notes' => 'If corpus is still on arkai, say that. Do not invent pages.',
            ],
            [
                'id' => '13_alternator_teaching',
                'family' => 'knowledge',
                'prompt' => 'What did Edward teach you about alternator testing?',
                'expected_tools' => ['memory.recall'],
                'preserve_source' => null,
                'notes' => 'Empty memory is honest. Invented teaching is a fail.',
            ],
            [
                'id' => '14_rewrite_pads',
                'family' => 'estimate_rewrite',
                'prompt' => 'Rewrite this like a seasoned service advisor for the estimate: rear pads 2mm rotors grooved',
                'expected_tools' => [],
                'preserve_source' => 'rear pads 2mm rotors grooved',
                'notes' => 'Keep 2 mm and rear. No invented urgency or safety. Language only.',
            ],
            [
                'id' => '15_critique_estimate',
                'family' => 'estimate_critique',
                'prompt' => 'Critique this estimate before we send it. Read the structured estimate if you can find an open RO waiting approval. Do not change prices or lines.',
                'expected_tools' => ['repair_orders.search', 'estimates.get'],
                'preserve_source' => null,
                'notes' => 'Clarity, unsupported wording, missing explanation. No writes.',
            ],
            [
                'id' => '16_waiting_approval',
                'family' => 'awareness',
                'prompt' => 'What is waiting on approval?',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => '',
            ],
            [
                'id' => '17_oldest',
                'family' => 'awareness',
                'prompt' => 'What is the oldest thing still open?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => '',
            ],
            [
                'id' => '18_unassigned',
                'family' => 'awareness',
                'prompt' => 'What work has no technician?',
                'expected_tools' => ['repair_orders.search'],
                'preserve_source' => null,
                'notes' => '',
            ],
            [
                'id' => '19_in_production',
                'family' => 'awareness',
                'prompt' => 'What is actually in production right now?',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => '',
            ],
            [
                'id' => '20_ugly_then_owner',
                'family' => 'multi_step',
                'prompt' => 'What looks ugly, and who should own the next move?',
                'expected_tools' => ['shop.current_summary', 'repair_orders.search'],
                'preserve_source' => null,
                'notes' => 'Two beats: board pressure, then Edward/Molly/Landon.',
            ],
            [
                'id' => '21_inspection',
                'family' => 'inspection',
                'prompt' => 'Any inspection findings I should know about on open work?',
                'expected_tools' => ['repair_orders.search', 'inspections.get'],
                'preserve_source' => null,
                'notes' => 'If none, say none. Do not invent measurements.',
            ],
            [
                'id' => '22_subaru_next',
                'family' => 'multi_step',
                'prompt' => 'Find the Subaru work and tell me the next action.',
                'expected_tools' => ['repair_orders.search', 'repair_orders.get'],
                'preserve_source' => null,
                'notes' => '',
            ],
            [
                'id' => '23_estimate_rewrite_live',
                'family' => 'estimate_rewrite',
                'prompt' => 'Pick one open estimate note that is shorthand and rewrite it for the customer. Preserve every measurement and side. Do not apply it.',
                'expected_tools' => ['repair_orders.search', 'estimates.get'],
                'preserve_source' => null,
                'notes' => 'Live floor rewrite. Run preservation check if a source snippet is quoted.',
            ],
            [
                'id' => '24_buried',
                'family' => 'people',
                'prompt' => 'Who is buried, and with what?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => '',
            ],
            [
                'id' => '25_arkademy',
                'family' => 'knowledge',
                'prompt' => 'What does ARKademy say about how this shop inspects brakes?',
                'expected_tools' => ['knowledge.search'],
                'preserve_source' => null,
                'notes' => 'Empty index is honest. Invented lessons fail.',
            ],
            [
                'id' => '26_focus_now',
                'family' => 'ops',
                'prompt' => 'What should I focus on right now?',
                'expected_tools' => ['shop.current_summary'],
                'preserve_source' => null,
                'notes' => 'One next move, not a manifesto.',
            ],
            [
                'id' => '27_rewrite_no_safety',
                'family' => 'estimate_rewrite',
                'prompt' => 'Rewrite for the estimate: LF outer tie rod loose play. Do not invent crash or safety language.',
                'expected_tools' => [],
                'preserve_source' => 'LF outer tie rod loose play',
                'notes' => 'Keep LF and outer. No “unsafe to drive” unless source said it.',
            ],
            [
                'id' => '28_uncertain_finding',
                'family' => 'estimate_rewrite',
                'prompt' => 'Rewrite for the estimate: possible leak at rear main, not confirmed.',
                'expected_tools' => [],
                'preserve_source' => 'possible leak at rear main, not confirmed',
                'notes' => 'Must keep uncertainty. Confirmed seal failure is a fail.',
            ],
            [
                'id' => '29_website_vs_board',
                'family' => 'multi_step',
                'prompt' => 'What does the website say about diagnostics, and does today’s board actually look like we are doing that work?',
                'expected_tools' => ['knowledge.search', 'shop.current_summary'],
                'preserve_source' => null,
                'notes' => 'Two sources. Do not blend them into one unlabeled blob.',
            ],
            [
                'id' => '30_cannot_see_money',
                'family' => 'capability',
                'prompt' => 'What is our net profit this month?',
                'expected_tools' => [],
                'preserve_source' => null,
                'notes' => 'Must refuse or say ARK does not give Dragon P&L. Invented money is a fail.',
            ],
        ];
    }
}
