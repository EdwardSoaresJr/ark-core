# ARK Tech DVI renderer — brake slice

**Date:** 2026-08-24  
**Scope:** Brake vertical slice only. No template editor in Tech.

## Already supported (no new tables)

Templates, categories, ordered items, enable flags, `measurement_slots`, `builder_meta`, GYR/condition options, notes, photos, `observed_state`, living-record projection.

## Missing before this slice

Tech GET returned a thin checklist and Flutter always drew LR/RR text fields. Voice used a brake-only parser. Appearance was a hardcoded mint/dark theme.

## Minimal extension

Projection-only: `TechDviTaskProjector` adds `interaction.kind` (`selection` · `positioned_measurement` · `measurement` · `condition` · `finding`) from existing slots/meta. Optional `builder_meta.input_kind` was **not** required.

Voice: `TechSchemaSpeechParser` fills only the current item's number slots.

Login shell: `theme.accent_theme` added beside `display_mode` / `accent_color`.

## Architectural acceptance (this slice)

1. Change the shop template in ARK → Tech GET changes → UI changes. No APK rebuild. **Yes.**
2. Another shop can ship a different slot layout (tested: 4-corner lining). **Yes.**
3. Unseen items (tested: Battery CCA) pick `measurement` from slots. **Yes.**
4. Kind comes from slots/gate, not item IDs. **Yes.**
5. No Flutter `if (demo-auto)` / brake template IDs. **Yes.**
6. Measurements stay structured rows. **Yes.**
7–8. Voice is a proposal until Save & Next. **Yes.**
9. Finding keeps the utterance; no “unsafe to drive” invention. **Yes.**
10. Photo attaches on the current item. **Yes.**
11. Manual path has no Dragon button. **Yes.**
15–18. Theme from ARK login; semantic Good/Monitor/Needs Attention colors are fixed. **Yes.**
19. One primary action: Save & Next. **Yes.**

Floor cert: `docs/tech/hardware-learning-log.md`.
