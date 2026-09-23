# Active PR

**Track:** Core RC2 - Local Floor Acceptance  
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

Floor acceptance. Use this Repair Order and Job Board during normal shop operation. No further development, profiling, or cleanup joins this set unless shop use exposes a release-blocking problem.

## Job Board

179 queries / ~178 ms measured with 24 cards. No further profiling unless shop use shows noticeable latency.

## Release

After floor acceptance:

1. Reconstruct these seven commits from the accepted production lineage.
2. Run the combined release gate.
3. Build and publish one immutable Core artifact.
4. Decide production rollout separately.

Do not release every commit ahead of origin.
