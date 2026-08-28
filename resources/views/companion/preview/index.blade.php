<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ARK Companion v1 — Design Preview (local)</title>
    <link rel="icon" href="/assets/ARK_SMS_FINAL_DROP_IN_PACK/favicon/favicon.ico">
    <style>
        :root {
            --ark: #0099cc;
            --ark-dark: #007aa3;
            --bg: #0f1419;
            --surface: #1a222c;
            --surface2: #242d38;
            --text: #f0f4f8;
            --muted: #8b9aab;
            --green: #22c55e;
            --red: #ef4444;
            --chip: #2d3a4a;
            --radius: 12px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        .banner {
            background: linear-gradient(90deg, #003d52, var(--ark));
            padding: 12px 20px;
            font-size: 13px;
            text-align: center;
        }
        .banner strong { display: block; font-size: 15px; margin-bottom: 4px; }
        .layout {
            display: grid;
            grid-template-columns: 1fr minmax(320px, 390px) 1fr;
            gap: 24px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .refs-panel { order: 3; }
        }
        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
            grid-column: 1 / -1;
        }
        .tab {
            background: var(--surface2);
            border: 1px solid #334155;
            color: var(--text);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            cursor: pointer;
        }
        .tab.active { background: var(--ark); border-color: var(--ark); color: #fff; }
        .refs-panel h2 { font-size: 14px; color: var(--muted); margin: 0 0 12px; text-transform: uppercase; letter-spacing: .06em; }
        .ref-card { margin-bottom: 16px; }
        .ref-card img { width: 100%; border-radius: 8px; border: 1px solid #334155; }
        .ref-card figcaption { font-size: 11px; color: var(--muted); margin-top: 6px; }
        .phone {
            background: #000;
            border-radius: 40px;
            padding: 12px;
            box-shadow: 0 24px 80px rgba(0,0,0,.5), inset 0 0 0 2px #333;
        }
        .phone-inner {
            background: var(--surface);
            border-radius: 32px;
            overflow: hidden;
            min-height: 720px;
            display: flex;
            flex-direction: column;
        }
        .status { height: 44px; background: var(--surface); flex-shrink: 0; }
        .screen { display: none; flex: 1; flex-direction: column; padding: 16px; }
        .screen.active { display: flex; }
        .display { font-size: 26px; font-weight: 700; line-height: 1.2; }
        .body { font-size: 15px; color: var(--muted); }
        .label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .chip {
            display: inline-block;
            background: var(--chip);
            color: var(--ark);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            margin-top: 8px;
        }
        .card {
            background: var(--surface2);
            border-radius: var(--radius);
            padding: 12px 14px;
            margin-top: 12px;
            font-size: 14px;
        }
        .card .label { margin-bottom: 4px; }
        .btn-row { display: flex; gap: 10px; margin-top: auto; padding-top: 24px; }
        .btn {
            flex: 1;
            padding: 16px;
            border-radius: var(--radius);
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: default;
        }
        .btn-decline { background: var(--surface2); color: var(--text); max-width: 120px; }
        .btn-answer { background: var(--green); color: #fff; }
        .strip {
            border-bottom: 1px solid #334155;
            padding: 8px 0 12px;
            margin: -8px 0 12px;
        }
        .strip .vehicle { font-size: 14px; color: var(--muted); }
        .bubble-in {
            background: var(--surface2);
            padding: 10px 14px;
            border-radius: 16px 16px 16px 4px;
            max-width: 85%;
            font-size: 15px;
            margin: 8px 0;
        }
        .bubble-out {
            background: var(--ark);
            color: #fff;
            padding: 10px 14px;
            border-radius: 16px 16px 4px 16px;
            max-width: 85%;
            font-size: 15px;
            margin: 8px 0 8px auto;
        }
        .quick-row { display: flex; gap: 8px; overflow-x: auto; padding: 8px 0; }
        .quick { background: var(--chip); padding: 6px 12px; border-radius: 999px; font-size: 12px; white-space: nowrap; }
        .composer {
            margin-top: auto;
            display: flex;
            gap: 8px;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #334155;
        }
        .composer input {
            flex: 1;
            background: var(--surface2);
            border: none;
            border-radius: 20px;
            padding: 10px 14px;
            color: var(--text);
            font-size: 15px;
        }
        .send { background: var(--ark); color: #fff; border: none; border-radius: 20px; padding: 10px 16px; font-weight: 600; }
        .sheet {
            background: var(--surface2);
            border-radius: 16px 16px 0 0;
            margin: 16px -16px -16px;
            padding: 16px;
        }
        .sheet-row { padding: 14px 0; border-bottom: 1px solid #334155; font-size: 16px; }
        .amount { font-size: 32px; font-weight: 700; margin: 16px 0; }
        .methods { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .method { background: var(--chip); padding: 10px 14px; border-radius: 8px; font-size: 13px; }
        .method.active { outline: 2px solid var(--ark); }
        .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .key { background: var(--surface); padding: 18px; text-align: center; border-radius: 8px; font-size: 22px; }
        .thumb-row { display: flex; gap: 8px; margin: 12px 0; }
        .thumb { width: 72px; height: 72px; background: var(--surface2); border-radius: 8px; }
        .continuity-row {
            background: var(--surface2);
            border-radius: var(--radius);
            padding: 14px;
            margin-bottom: 10px;
        }
        .continuity-row strong { display: block; font-size: 15px; }
        .continuity-row span { font-size: 13px; color: var(--muted); }
        .tab-bar {
            display: flex;
            justify-content: space-around;
            padding: 10px 0 20px;
            border-top: 1px solid #334155;
            margin: auto -16px -16px;
            background: var(--surface);
            font-size: 10px;
            color: var(--muted);
        }
        .tab-bar .on { color: var(--ark); }
        .search-input {
            width: 100%;
            background: var(--surface2);
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            color: var(--text);
            font-size: 16px;
            margin-bottom: 16px;
        }
        .result {
            padding: 12px 0;
            border-bottom: 1px solid #334155;
        }
        .result strong { font-size: 16px; }
        .actions { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
        .actions button { background: var(--chip); border: none; color: var(--ark); padding: 6px 10px; border-radius: 6px; font-size: 12px; }
        .note-panel { font-size: 13px; color: var(--muted); line-height: 1.5; }
        .note-panel code { background: var(--surface2); padding: 2px 6px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="banner">
        <strong>ARK Companion v1 — Design Preview</strong>
        Local only · Not the Flutter app · Specs + references side-by-side · API is JSON until mobile ships
    </div>

    <div class="layout">
        <div class="note-panel">
            <h2>What this is</h2>
            <p>Interactive mock of P0 gate screens from <code>docs/companion-v1/screens/</code>. Backend endpoints exist (<code>/api/mobile/incoming-call/context</code>, etc.) but there is no installable app yet.</p>
            <p><strong>Next:</strong> Edward sign-off → new Flutter app per <code>08-flutter-build-order.md</code>.</p>
        </div>

        <div>
            <div class="tabs" role="tablist">
                @foreach($screens as $key => $screen)
                    <button type="button" class="tab {{ $loop->first ? 'active' : '' }}" data-screen="{{ $key }}">{{ $screen['label'] }}</button>
                @endforeach
            </div>
            <div class="phone">
                <div class="phone-inner">
                    <div class="status"></div>

                    {{-- Incoming --}}
                    <div class="screen active" data-screen="incoming">
                        <div class="display">Emma Hathorn</div>
                        <div class="body">(512) 555-0199</div>
                        <div class="vehicle" style="margin-top:12px;font-size:17px;">2019 Honda Civic · ABC123</div>
                        <span class="chip">RO #1599 · Waiting approval</span>
                        <div class="card">
                            <div class="label">Estimate</div>
                            Sent · viewed 2× · $1,847.00
                        </div>
                        <div class="card">
                            <div class="label">Last message</div>
                            Can I pick up at 5? · 2:14 PM
                        </div>
                        <div class="btn-row">
                            <button type="button" class="btn btn-decline">Decline</button>
                            <button type="button" class="btn btn-answer">Answer</button>
                        </div>
                    </div>

                    {{-- Thread --}}
                    <div class="screen" data-screen="thread">
                        <div class="strip">
                            <div class="display" style="font-size:20px;">Emma Hathorn</div>
                            <div class="vehicle">2019 Civic · RO #1599 · <span class="chip" style="margin:0;">Waiting approval</span></div>
                        </div>
                        <div class="label" style="text-align:center;margin:8px 0;">Today</div>
                        <div class="bubble-in">Can I pick up at 5?</div>
                        <div class="bubble-out">Yes — see you then!</div>
                        <div class="quick-row">
                            <span class="quick">Send estimate</span>
                            <span class="quick">Payment link</span>
                            <span class="quick">Inspection</span>
                        </div>
                        <div class="composer">
                            <input type="text" placeholder="Message Emma…" readonly>
                            <button type="button" class="send">Send</button>
                        </div>
                        <div class="sheet">
                            <div class="label">Manage · shop actions</div>
                            <div class="sheet-row">Open repair order</div>
                            <div class="sheet-row">Send estimate</div>
                            <div class="sheet-row">Take payment</div>
                            <div class="sheet-row">Schedule</div>
                        </div>
                    </div>

                    {{-- Payment --}}
                    <div class="screen" data-screen="payment">
                        <div class="body">Emma Hathorn · 2019 Civic</div>
                        <div class="chip">RO #1599</div>
                        <div class="label" style="margin-top:16px;">Balance due</div>
                        <div class="amount">$1,847.00</div>
                        <div class="methods">
                            <span class="method active">Cash</span>
                            <span class="method">Card</span>
                            <span class="method">Terminal</span>
                            <span class="method">Send link</span>
                        </div>
                        <div class="keypad">
                            @foreach(['1','2','3','4','5','6','7','8','9','⌫','0','.'] as $k)
                                <div class="key">{{ $k }}</div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-answer" style="width:100%;margin-top:16px;">Record payment</button>
                    </div>

                    {{-- Inspection --}}
                    <div class="screen" data-screen="inspection">
                        <div class="display" style="font-size:20px;">Brake pads — front</div>
                        <div class="body">RO #1599 · 2019 Civic</div>
                        <span class="chip">Item 4 of 12 · Needs review</span>
                        <div class="card"><strong style="color:var(--red);">FAIL</strong> · Pad thickness below spec · recommend replacement</div>
                        <div class="thumb-row">
                            <div class="thumb"></div>
                            <div class="thumb"></div>
                            <div class="thumb" style="display:flex;align-items:center;justify-content:center;color:var(--muted);">+</div>
                        </div>
                        <div class="card">
                            <div class="label">Advisor note</div>
                            <input type="text" class="search-input" style="margin:8px 0 0;" placeholder="Ask Ben for closer photo…" readonly>
                        </div>
                        <div class="btn-row">
                            <button type="button" class="btn btn-decline" style="max-width:none;">Open RO</button>
                            <button type="button" class="btn btn-answer">Mark reviewed</button>
                        </div>
                    </div>

                    {{-- Home --}}
                    <div class="screen" data-screen="home">
                        <div class="body">Good morning, Edward</div>
                        <div class="chip" style="background:#1e3a2f;color:#86efac;">🟢 Phone online</div>
                        <div style="margin-top:20px;">
                            <div class="continuity-row"><strong>Emma replied</strong><span>2019 Civic · RO #1599 · "Can I pick up at 5?" · 8 min</span></div>
                            <div class="continuity-row"><strong>Ben uploaded inspection</strong><span>RO #1599 · Brake pads — front · 12 min</span></div>
                            <div class="continuity-row"><strong>Josh called while closed</strong><span>Missed · (719) 555-0142 · 6:42 AM</span></div>
                        </div>
                        <div class="tab-bar">
                            <span class="on">Home</span><span>Comms</span><span>Search</span><span>Schedule</span><span>More</span>
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="screen" data-screen="search">
                        <input type="text" class="search-input" value="Emma" readonly>
                        <div class="label">Customers</div>
                        <div class="result">
                            <strong>Emma Hathorn</strong>
                            <div class="body">2019 Civic · RO #1599 open</div>
                            <div class="actions">
                                <button>Call</button><button>Text</button><button>Open RO</button><button>Pay</button><button>Schedule</button>
                            </div>
                        </div>
                        <div class="tab-bar">
                            <span>Home</span><span>Comms</span><span class="on">Search</span><span>Schedule</span><span>More</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="refs-panel">
            <h2>Reference captures</h2>
            <div id="refs-incoming">
                @foreach($screens['incoming']['refs'] as $ref)
                    <figure class="ref-card"><img src="{{ $ref['src'] }}" alt=""><figcaption>{{ $ref['label'] }}</figcaption></figure>
                @endforeach
            </div>
            <div id="refs-thread" hidden>
                @foreach($screens['thread']['refs'] as $ref)
                    <figure class="ref-card"><img src="{{ $ref['src'] }}" alt=""><figcaption>{{ $ref['label'] }}</figcaption></figure>
                @endforeach
            </div>
            <div id="refs-payment" hidden>
                @foreach($screens['payment']['refs'] as $ref)
                    <figure class="ref-card"><img src="{{ $ref['src'] }}" alt=""><figcaption>{{ $ref['label'] }}</figcaption></figure>
                @endforeach
            </div>
            <div id="refs-inspection" hidden><p class="body">Push lands here — shop-native inspection surface.</p></div>
            <div id="refs-home" hidden><p class="body">Continuity-first home — not a KPI dashboard.</p></div>
            <div id="refs-search" hidden><p class="body">Command palette — find customer, vehicle, or RO.</p></div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const id = tab.dataset.screen;
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
                tab.classList.add('active');
                document.querySelector(`.screen[data-screen="${id}"]`)?.classList.add('active');
                document.querySelectorAll('.refs-panel > div[id^="refs-"]').forEach(p => p.hidden = true);
                document.getElementById('refs-' + id)?.removeAttribute('hidden');
            });
        });
    </script>
</body>
</html>
