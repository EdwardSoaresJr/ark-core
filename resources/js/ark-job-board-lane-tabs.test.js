import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('./ark-job-board-lane-tabs.js', import.meta.url), 'utf8');
const appSource = readFileSync(new URL('./app.js', import.meta.url), 'utf8');

test('lane tabs scroll helper is wired into app boot', () => {
    assert.match(source, /scrollIntoView/);
    assert.match(source, /data-job-board-lane-tabs-track/);
    assert.match(source, /inline:\s*'center'/);
    assert.match(source, /block:\s*'nearest'/);
    assert.match(appSource, /initJobBoardLaneTabs/);
});
