## Full regression

4849 tests, 224 failing; the known-failure baseline lists 200.

### Regressions: failing and not in the baseline (31)

- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::adding phone to legacy workstation without extension assigns extension automatically`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::adding phone to workstation opens device workspace without server error`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::creating workstation auto-assigns extension and advances to phone step`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::device provisioning controls stay hidden from non master admins`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::ensure extension reclaims legacy orphan extension row for same number`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::incoming routing updates ring targets by person not extension`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::settings manager can remove a communication device and return to voice setup`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::shop communications answers whether the shop can communicate`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::shop communications projects live calls in todays coverage`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::shop communications shows setup in progress when workstation lacks phone path`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::store communication device persists normalized mac and resolved model`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::store communication device rejects duplicate mac with validation error`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::store communication device rejects invalid mac with validation error`
- `tests/Feature/Communications/CommunicationsWorkspaceShellTest.php::stations and phones pages render in the Tabler shell with their forms`
- `tests/Feature/Communications/DiscoveredDeviceAssignTest.php::assigning pending device to station provisions without manual mac entry`
- `tests/Feature/Communications/DiscoveredDeviceAssignTest.php::pending device appears under needs attention when shop is operating`
- `tests/Feature/Communications/EndpointArchitecturePr1Test.php::shop assign extension route completes first contact operator path`
- `tests/Feature/Communications/MissedCallRescueTest.php::admin can save missed call rescue settings on hours tab`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::advisor home shows compact brief and full active repair order board`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::advisor home waiting approval column holds customer-decision repair orders`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::home card anchors RO number upper left before vehicle and customer`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::home card plus-count names the other attention items`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::home card surfaces promise time on the meta row`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::in progress cards hide empty labor progress and empty promise time`
- `tests/Feature/Operations/AdvisorHomeBoardTest.php::waiting approval cards surface configured status not estimate overlay`
- `tests/Feature/Operations/CommunicationsQuickReplyTemplatesTest.php::communications settings can add and delete canned responses`
- `tests/Feature/Operations/DailyOperationsTablerTest.php::ARK Cloud connecting renders in the Tabler shell and keeps its poller`
- `tests/Feature/Operations/DemoCompanionSurfaceTest.php::demo hides arkademy and platform surfaces`
- `tests/Feature/Operations/DemoCompanionSurfaceTest.php::shops outside the demo still show arkademy and platform`
- `tests/Feature/Operations/OwnerCallIntelligenceTest.php::core settings no longer expose call intelligence provider configuration`
- `tests/Feature/Operations/RepairOrderOpenProfileTest.php::opening a repair order reuses settlement catalog and messaging reads`

### Known failures still failing (193)

- `tests/Feature/Acceptance/RapidWorkflowAcceptanceTest.php::workflow communications saves settings and sends inspection through platform`
- `tests/Feature/Cloud/FabricIngressTest.php::fabric ingress accepts voice.incoming.started and broadcasts interrupts`
- `tests/Feature/Cloud/FabricIngressTest.php::fabric ingress rejects replay nonce`
- `tests/Feature/Communications/CommunicationsAdvisorNudgeTest.php::attention list shows call channel label for unhandled calls`
- `tests/Feature/Communications/CommunicationsAdvisorNudgeTest.php::attention workspace shows mark handled nudge for unhandled completed call`
- `tests/Feature/Communications/CommunicationsAdvisorNudgeTest.php::dismissed nudge stays hidden for eight hours then reappears`
- `tests/Feature/Communications/CommunicationsAdvisorNudgeTest.php::missed call shows mark handled nudge with higher priority than completed call analysis`
- `tests/Feature/Communications/CommunicationsAdvisorNudgeTest.php::sending sms analysis draft reply records acted response`
- `tests/Feature/Communications/CommunicationsAttentionTuningTest.php::analysis insight panel shows when mark handled blocks call analysis nudge`
- `tests/Feature/Communications/CommunicationsAttentionTuningTest.php::assigned conversation with sms analysis shows follow up nudge and draft`
- `tests/Feature/Communications/CommunicationsAttentionTuningTest.php::dismissed mark handled nudge reveals call analysis nudge with draft`
- `tests/Feature/Communications/CommunicationsContextTest.php::a hosted repair order send does not write a second core message`
- `tests/Feature/Communications/CommunicationsContextTest.php::repair order sends keep added inspection and payment references on the same message`
- `tests/Feature/Communications/CommunicationsInboxFeatureCodeTest.php::communications inbox excludes feature code call sessions`
- `tests/Feature/Communications/CommunicationsInboxFeatureCodeTest.php::communications inbox threads one row per customer when conversation and calls overlap`
- `tests/Feature/Communications/CommunicationsQueueWorkspaceCutTest.php::explicit open loads the existing relationship workspace`
- `tests/Feature/Communications/CommunicationsQueueWorkspaceCutTest.php::waiting queue does not auto-open the relationship workspace`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::assign extension rejects real conflict without mutating either workstation`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::device projection preview stays hidden from non master admins`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::device provisioning generates downloadable config without exposing credentials in ui`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::device workspace surfaces provisioning observability for bench certification`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::device workspace tells operational truth without transport details`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::person workspace lists assigned devices with name-only add form`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::shop communications manual device entry is limited to master admin support`
- `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php::shop communications surfaces attention when a device is offline`
- `tests/Feature/Communications/CommunicationsWorkspaceOutboundCallTest.php::handled call stays in inbox with full thread including outbound messages`
- `tests/Feature/Communications/CommunicationsWorkspaceShellTest.php::attention workspace thread renders inbound mms image attachments`
- `tests/Feature/Communications/CommunicationsWorkspaceSprintTest.php::advisor reply records first contact and keeps Needs attention until follow-up`
- `tests/Feature/Communications/CommunicationsWorkspaceSprintTest.php::conversation thread send payment records sms on the active conversation`
- `tests/Feature/Communications/CommunicationsWorkspaceSprintTest.php::customer inbound sms returns conversation to needs attention`
- … and 163 more (see the regression-failures artifact)

### Known failures now passing: remove them from the baseline (7)

- `tests/Feature/Operations/EstimateDocumentSnapshotTest.php::terminal repair order statuses snapshot a final pdf that no longer auto refreshes`
- `tests/Feature/Operations/MaintenanceEngineOilServiceTest.php::confirm installed creates append-only event; sticker print works before and after`
- `tests/Feature/Operations/RepairOrderLifecycleQueueTest.php::operations home survives dense active queue without full estimate graph dependency`
- `tests/Feature/Operations/TelephonyHealthSettingsTest.php::settings communications telephony page surfaces operational health on general tab`
- `tests/Feature/Operations/TelephonyHealthSettingsTest.php::settings managers can save primary telephony provider per shop`
- `tests/Feature/Operations/TelephonyHealthSettingsTest.php::settings managers can test incoming call from telephony page route`
- `tests/Unit/Operations/PdfRuntimeConfiguratorTest.php::it re-resolves chrome when cached path is stale`

