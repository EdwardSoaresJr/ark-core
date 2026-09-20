<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ARK error {{ $reportId }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            font: 14px/1.5 ui-sans-serif, system-ui, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .card {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
        }
        h1 {
            margin: 0 0 8px;
            font-size: 20px;
        }
        .meta {
            margin: 0 0 16px;
            color: #475569;
        }
        code, pre, textarea {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        .id {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            background: #e2e8f0;
            font-weight: 700;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 16px 0;
        }
        button {
            appearance: none;
            border: 1px solid #334155;
            background: #0f172a;
            color: #fff;
            border-radius: 6px;
            padding: 8px 14px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        button.secondary {
            background: #fff;
            color: #0f172a;
        }
        button.ok {
            background: #166534;
            border-color: #166534;
        }
        textarea {
            width: 100%;
            min-height: 420px;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            resize: vertical;
            background: #f8fafc;
        }
        .commands {
            margin-top: 16px;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 6px;
        }
        .commands p {
            margin: 0 0 8px;
            font-weight: 600;
        }
        .status {
            min-height: 1.25rem;
            margin-top: 8px;
            color: #166534;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>ARK exception report</h1>
        <p class="meta">
            Report ID <span class="id">{{ $reportId }}</span>
            · {{ class_basename($exceptionClass) }}
            · {{ $exceptionMessage !== '' ? $exceptionMessage : 'No message provided.' }}
        </p>

        <div class="actions">
            <button type="button" id="copy-markdown">Copy markdown</button>
            <button type="button" class="secondary" id="select-markdown">Select all</button>
        </div>
        <p class="status" id="copy-status" aria-live="polite"></p>

        <textarea id="markdown-body" readonly>{{ $markdown }}</textarea>

        <div class="commands">
            <p>On the VPS</p>
            <pre><code>{{ $vpsCommand }}</code></pre>
            @if ($showCommand)
                <pre><code>{{ $showCommand }}</code></pre>
            @endif
        </div>
    </div>
</div>
<script>
(() => {
    const textarea = document.getElementById('markdown-body');
    const status = document.getElementById('copy-status');
    const copyButton = document.getElementById('copy-markdown');
    const selectButton = document.getElementById('select-markdown');

    const setStatus = (message, ok = false) => {
        status.textContent = message;
        copyButton.classList.toggle('ok', ok);
    };

    selectButton.addEventListener('click', () => {
        textarea.focus();
        textarea.select();
        setStatus('Markdown selected.');
    });

    copyButton.addEventListener('click', async () => {
        const text = textarea.value;

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                textarea.focus();
                textarea.select();
                document.execCommand('copy');
            }

            setStatus('Markdown copied to clipboard.', true);
        } catch {
            textarea.focus();
            textarea.select();
            setStatus('Select-all fallback ready — press ⌘/Ctrl+C.');
        }
    });
})();
</script>
</body>
</html>
