<div class="ops-learn-prose">
    <h3>Labor guides from the RO</h3>
    <p>Labor guides open from the repair order scoped to provider — ARK redirects with RO context so hours you pick land on the correct repair action. Do not look up hours in a browser tab detached from the worksheet.</p>
    <p>Guide hours are starting points — shop modifiers, overlap, and rust tax still belong in advisor judgment. Matrix and rate authority stay in Settings and line entry.</p>
    <p>After import, verify labor category and billed rate match shop policy — see <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'parts-and-labor']) }}">Parts and labor entry</a>.</p>

    <x-operations.learn.figure
        role="advisor"
        article="labor-guides"
        file="labor-guide-launch.png"
        alt="Launch labor guide from repair action on estimate worksheet"
        caption="Launch from the repair action — hours attach to the fix you are selling, not a floating orphan line."
    />

    <h3>Advisor discipline</h3>
    <p>One repair action per named fix before you open guides — otherwise hours attach to the wrong story on the PDF.</p>
    <p>Combine guide hours with complete parts lists from PartsTech — ELR leaks when diag hours are free but parts margin carries the job.</p>
    <p>Financial literacy: effective labor rate lives on posted work — <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'financial-literacy-basics']) }}">Financial literacy basics</a>.</p>

    <h3>Technician boundary</h3>
    <p>Techs may suggest hours in findings; advisors own billed hours on the estimate. Collaboration guide: <a href="{{ route('operations.learn.show', ['role' => 'technician', 'article' => 'worksheet-collaboration']) }}">Worksheet collaboration</a>.</p>
    <p>Do not discount labor to match an outdated guide printout — update the line and explain value to the customer.</p>

    <x-operations.learn.video
        role="advisor"
        article="labor-guides"
        file="walkthrough.mp4"
        video-key="main"
        title="Open labor guide and apply hours to repair action"
        poster-file="poster.jpg"
    />

    <h3>Related guides</h3>
    <p>Structure: <a href="{{ route('operations.learn.show', ['role' => 'advisor', 'article' => 'repair-actions']) }}">Repair actions</a>.</p>
    <p>Admin labor rate: <a href="{{ route('operations.learn.show', ['role' => 'admin', 'article' => 'financial-rules']) }}">Financial rules</a>.</p>
</div>
