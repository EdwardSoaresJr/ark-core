@php
    /** @var \App\Ark\Operations\Workboard\JobBoardListRow $row */
    // Render the production list-row cells once, then split <td> inner HTML for DataTables columns.
    $tr = view('operations.workboard.partials.home-board-list-row', ['row' => $row])->render();
    preg_match_all('/<td\b[^>]*>(.*?)<\/td>/s', $tr, $matches);
    $cells = $matches[1] ?? [];
    $payload = [
        'ro' => $cells[0] ?? '',
        'customer' => $cells[1] ?? '',
        'concern' => $cells[2] ?? '',
        'status' => $cells[3] ?? '',
        'tech' => $cells[4] ?? '',
        'total' => $cells[5] ?? '',
        'attention' => $cells[6] ?? '',
        'activity' => $cells[7] ?? '',
        'updated' => $cells[8] ?? '',
        'open' => $cells[9] ?? '',
    ];
@endphp
{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
