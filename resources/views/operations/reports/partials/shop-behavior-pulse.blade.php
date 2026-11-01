<section class="mb-3" aria-labelledby="report-shop-behavior-pulse" data-report-section="shop-behavior-pulse">
    <h3 class="mb-0" id="report-shop-behavior-pulse">Shop Behavior Pulse</h3>
    <p class="text-secondary small mb-2">What is stuck in the building right now - counts only, not revenue.</p>
    <div class="row row-cards">
        @foreach ($shopBehaviorPulse as $row)
            <div class="col-12 col-sm-6 col-lg">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-baseline justify-content-between gap-2">
                            <div class="subheader">{{ $row['label'] }}</div>
                            <div class="h3 mb-0 ark-money">{{ $row['count'] }}</div>
                        </div>
                        <div class="small text-secondary mt-1">{{ $row['hint'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

@if (($frontDoorLanding['total'] ?? 0) > 0)
    <section class="mb-3" aria-labelledby="report-front-door" data-report-section="front-door">
        <h3 class="mb-0" id="report-front-door">Front Door Experiment</h3>
        <p class="text-secondary small mb-2">First operational surface opened per session - last {{ $frontDoorLanding['days'] }} days.</p>
        <div class="row row-cards">
            <div class="col-12 col-sm-4">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Work first</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $frontDoorLanding['attention'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Job Board first</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $frontDoorLanding['workboard'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Work share</div>
                        <div class="h3 mb-0 mt-1 ark-money">
                            @if ($frontDoorLanding['attention_share'] !== null)
                                {{ $frontDoorLanding['attention_share'] }}%
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
