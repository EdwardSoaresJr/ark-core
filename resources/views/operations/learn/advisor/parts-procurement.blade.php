<div class="ops-learn-prose">
    <h3>Parts status is a promise</h3>
    <p>Waiting-parts posture on the RO tells the workboard, the tech, and the customer the same story: approved work exists but the bay cannot finish yet. Lie about parts status and you lose trust twice — with techs and with customers calling for updates.</p>
    <p>Move to waiting-parts when the order is placed, not when you “think you’ll order later.” Move out when parts are in hand or the job is cancelled — not when the vendor says shipped unless that is your shop’s explicit rule.</p>
    <p>The workboard <strong>Waiting parts</strong> lane aggregates this posture — scan it after morning queue and before promising pickup times.</p>

    <x-operations.learn.figure
        role="advisor"
        article="parts-procurement"
        file="waiting-parts-lane.png"
        alt="Workboard waiting parts lane with aging indicators"
        caption="Oldest waiting-parts jobs deserve first call — customer silence breeds bad reviews."
    />

    <h3>Advisor procurement rhythm</h3>
    <p>PartsTech import is quoting; procurement is fulfillment. After import, confirm vendor, ETA, and backorder risk before you text the customer an pickup date.</p>
    <p>With a personal PartsTech login on your profile, each advisor can shop their own RO concurrently. With the shop default login only, finish one RO’s catalog session before another advisor opens PartsTech on a different RO — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'partstech-workflow']) }}">PartsTech workflow</a>.</p>
    <p>Partial shipments happen — update scope notes or internal notes so the tech knows what is on the bench vs still on the truck.</p>
    <p>When parts arrive, transition lifecycle before the customer shows up unannounced. See <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'lifecycle-transitions']) }}">Lifecycle transitions</a>.</p>

    <h3>Communication</h3>
    <p>Proactive texts beat reactive apologies. A short MMS of the part on the shelf resets customer anxiety better than “still waiting on parts” weekly.</p>
    <p>Use Quick Reply from hub or RO — thread history shows you kept them informed if they dispute timeline later.</p>
    <p>Deferred work from inspections belongs on follow-up, not fake waiting-parts — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'inspection-to-aro']) }}">Inspection to ARO</a>.</p>

    <x-operations.learn.video
        role="advisor"
        article="parts-procurement"
        file="walkthrough.mp4"
        video-key="main"
        title="Waiting-parts posture and procurement updates"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Quoting: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'partstech-workflow']) }}">PartsTech catalog and quotes</a>.</p>
    <p>Lane scan: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'workboard-lanes']) }}">Workboard lanes</a>.</p>
    <p>Line procurement fields update from the worksheet editor on each part line — status must match what is on the shelf or on order.</p>
</div>
