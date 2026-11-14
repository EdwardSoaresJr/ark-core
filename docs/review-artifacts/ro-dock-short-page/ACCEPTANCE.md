# RO sticky footer — short + long acceptance

**Alpha after deploy:** SaaS `a6daf96a1b25f2da5cb437f5af0b8bd8db16f3c4` · Core `de79dacd78d76e9cf256b15c324c4684fa49282f`

## Conditions

| Condition | Result | Evidence |
| --- | --- | --- |
| Short RO: dock stays at viewport bottom | **PASS** | RO #19 (0 lines). Before: mid-page gap (desktop 417px @ 1400h; tablet 125px @ 1600h; 320 271px @ 2200h). After: `position:fixed`, gap 0 at those viewports. |
| Long RO: scrolls; content clears dock | **PASS** | RO #41 @ 768: scroll works; content clears dock (~41px); body pad tracks measured dock height. |

## Fix

Core PR #203 / SaaS PR #251: `[data-ro-orientation-header]` uses `position: fixed; bottom: 0` (sidebar-aware from lg). Existing page-body dock reserve unchanged.

No Print / PartsTech / identity / pricing changes. No production deploy.
