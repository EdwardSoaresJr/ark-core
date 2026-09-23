# Active PR

**Track:** Core RC2 — Local Floor Acceptance  
**Status:** Floor use / validation  
**Surface:** Repair Order advisor workflow + Job Board

## Release set

Frozen. Do not add or remove commits unless floor use exposes a problem.

Oldest to newest. This is the release. Other commits on main are not part of it.

1. `253ecc54` Remove repair order missing-item recommendations.
2. `455845b2` Move work exceptions out of the concern worksheet.
3. `a01264ca` Set the repair order query ceiling to the measured exception load.
4. `2f842878` Keep the active PR note on the missing-item track.
5. `ef3b8a58` Lead the repair order with the job being written.
6. `162e1432` Stop the job board from repeating timezone, message, and lane lookups.
7. `220d256b` Keep print and paperwork on the repair order footer.

## Next gate

Use this Repair Order and Job Board during normal shop operation. Record actual workflow problems only. Do not reopen completed UI or performance work from code inspection.

## Job Board

179 queries / ~178 ms measured with 24 cards. No further optimization unless real use shows noticeable latency. If it does, profile customer phone matching, conversations, and leads next.

## Release

After floor acceptance, assemble exactly these seven commits from the accepted production lineage, run the combined release gate, and produce one immutable RC2 artifact. Do not release every commit ahead of origin. Do not deploy piecemeal.
