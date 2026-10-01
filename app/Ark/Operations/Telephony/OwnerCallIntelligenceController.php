<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Communications\CommunicationIntelligenceIndexQuery;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceAnalyzer;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Communications\SmsIntelligenceQuery;
use App\Ark\Operations\Staff\StaffCoachingLogPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OwnerCallIntelligenceController
{
    public function index(Request $request, CommunicationIntelligenceIndexQuery $query): View
    {
        $paginator = $query->paginate($request);

        return view('operations.owner.call-intelligence', [
            'rows' => $paginator->getCollection(),
            'coachingRows' => $query->coachingQueue($request),
            'paginator' => $paginator,
            'filters' => [
                'from' => $request->query('from', now()->subDays(30)->toDateString()),
                'to' => $request->query('to', now()->toDateString()),
                'channel' => $request->query('channel', 'all'),
                'media' => $request->query('media', 'recorded'),
                'analysis' => $request->query('analysis', ''),
            ],
            'analysisEnabled' => CallSessionAnalyzer::enabled(),
        ]);
    }

    public function show(
        Request $request,
        CallSession $callSession,
        CommunicationIntelligenceIndexQuery $query,
        StaffCoachingLogPresenter $coachingLogs,
    ): View {
        $callSession->load(['customer', 'owner', 'repairOrder']);

        return view('operations.owner.call-intelligence-show', [
            'row' => $query->presentCallRow($callSession),
            'analysisEnabled' => CallSessionAnalyzer::enabled(),
            'listUrl' => route('operations.owner.call-intelligence', $request->only(['from', 'to', 'channel', 'media', 'analysis'])),
            'coachingStaffOptions' => $coachingLogs->coachableStaff(),
            'defaultCoachingStaffUserId' => $coachingLogs->defaultStaffUserIdForCall($callSession),
            'coachingLogs' => $coachingLogs->forCallSession($callSession),
            'showCoachingDebrief' => true,
        ]);
    }

    public function showSms(
        Request $request,
        ConversationSmsIntelligenceSlice $slice,
        SmsIntelligenceQuery $smsQuery,
    ): View {
        $slice->load([
            'conversation.owner',
            'conversation.participants.customer',
            'conversation.links',
        ]);

        return view('operations.owner.call-intelligence-show', [
            'row' => $smsQuery->presentRow($slice),
            'analysisEnabled' => ConversationSmsIntelligenceAnalyzer::enabled(),
            'listUrl' => route('operations.owner.call-intelligence', $request->only(['from', 'to', 'channel', 'media', 'analysis'])),
            'coachingStaffOptions' => [],
            'defaultCoachingStaffUserId' => null,
            'coachingLogs' => collect(),
            'showCoachingDebrief' => false,
        ]);
    }

    public function analyze(
        Request $request,
        CallSession $callSession,
        CallSessionAnalyzer $analyzer,
    ): RedirectResponse {
        $callSession->forceFill([
            'analysis_status' => null,
            'analysis_error' => null,
        ])->saveQuietly();

        $analyzer->queueIfEligible($callSession->fresh());

        return redirect()
            ->back()
            ->with('status', 'Call analysis queued.');
    }

    public function analyzeSms(
        Request $request,
        ConversationSmsIntelligenceSlice $slice,
        ConversationSmsIntelligenceAnalyzer $analyzer,
    ): RedirectResponse {
        $slice->forceFill([
            'analysis_status' => null,
            'analysis_error' => null,
        ])->saveQuietly();

        $analyzer->queueIfEligible($slice->fresh());

        return redirect()
            ->back()
            ->with('status', 'SMS thread analysis queued.');
    }
}
