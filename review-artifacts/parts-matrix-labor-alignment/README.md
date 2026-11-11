# Parts Matrix Labor-aligned UI — staging evidence

**PR:** https://github.com/EdwardSoaresJr/ark-core/pull/186  
**Staging host:** `alpha.staging.arksms.com`  
**Staging Core pin:** `851f3f5a65f0b007d02268f8c83d8afc0806bfa9`  
**SaaS require:** `dev-pin` (checkout pin; composer require unchanged)

## Screenshots

| File | What |
| --- | --- |
| `01-default-aft-parts-desktop.png` | Default AFT Parts selected; left matrix nav + editor |
| `02-warranty-selected-desktop.png` | Warranty (No Markup) selected |
| `03-switch-warranty-save-success.png` | After matrix switch + Save → status `Saved` |
| `04-validation-save-needed.png` | Empty required cost → status `Save needed` |
| `05-mobile-parts-matrix.png` | Mobile: matrix list |
| `06-mobile-parts-editor.png` | Mobile: selected editor |
| `07-labor-reference-desktop.png` | Labor categories reference (design language) |

## Notes

- Presentation-only change. Pricing engine, Billing Class associations, and historical RO prices unchanged.
- Autosave serializes **all** matrices from Alpine state so a single visible editor cannot wipe siblings.
- No matrix create route exists; delete + default + tier edit remain.
- Production / LugsNPlugs untouched. PR left open for owner visual acceptance.
