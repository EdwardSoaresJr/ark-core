<div class="ops-learn-prose">
    <h3>Search is recognition</h3>
    <p><a href="{{ route('operations.customers.search') }}">Customer search</a> is how advisors answer “have we seen this person before?” in under three seconds. Search name, phone, email, plate, address fragment, or VIN — whichever fragment the customer gives you first.</p>
    <p>Check-in unified search uses the same authority during <a href="{{ route('operations.intake.create') }}">+ Check In</a> — results update as you type without leaving the recognition band. Pick the right row and you skip duplicate creation and wrong-vehicle mistakes.</p>
    <p>When search returns nothing, create deliberately — not because you were in a hurry. One bad duplicate pollutes texting, history, and portal links for years.</p>

    <x-operations.learn.figure
        role="advisor"
        article="customer-search"
        file="customer-search-results.png"
        alt="Customer search results with phone and vehicle hints"
        caption="Scan phone and vehicle columns before you click — similar names are common."
    />

    <h3>Avoiding duplicates</h3>
    <p>Before you create, try alternate spellings and the other phone on the account. Spouses and fleet contacts often share vehicles under one billing customer.</p>
    <p>While creating a new customer on intake, ARK surfaces <strong>similar customers on file</strong> when name, phone, email, or address is close — each row shows match reasons and address when on file. Tap <strong>Use this customer</strong> instead of splitting the file.</p>
    <p>Fleet and commercial accounts may have many vehicles under one customer record. Opening the wrong hub still beats splitting the customer in two.</p>

    <h3>After you land</h3>
    <p>Selecting a customer opens the <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'customer-hub']) }}">Customer Service Hub</a> — work, vehicles, comms, and history in one surface.</p>
    <p>From the hub you can start a draft RO, text the customer, or review open jobs without hunting the workboard first.</p>
    <p>Caller lookup from the phone pop uses the same search index — answer the call, then jump to hub or intake without retyping the number.</p>

    <x-operations.learn.video
        role="advisor"
        article="customer-search"
        file="walkthrough.mp4"
        video-key="main"
        title="Find the right customer in under ten seconds"
        poster-file="poster.jpg"
    />

    <h3>Floor habits</h3>
    <p>Train new staff to search twice, create once. The ten seconds of search saves hours of merged-account cleanup later.</p>
    <p>When a customer says “you have my other car on file,” search by phone first — vehicle plates change more often than numbers.</p>
    <p>Related: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'advisor-intake']) }}">Service counter check-in</a> and <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'incoming-calls-floor']) }}">Answering calls in ARK</a>.</p>
</div>
