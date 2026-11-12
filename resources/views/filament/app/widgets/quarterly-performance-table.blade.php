    <x-filament::section :heading="$heading">
        <div class="fi-ta-content-ctn" style="overflow-x: auto">
            <div class="fi-ta-content">
            <table class="fi-ta-table">
                <thead>
                    <tr class="fi-ta-table-head-groups-row">
                        <th scope="colgroup" colspan="2" class="fi-ta-header-group-cell" style="text-align: center">OPB</th>
                        <th scope="colgroup" colspan="1" class="fi-ta-header-group-cell" style="text-align: center">Target</th>
                        <th scope="colgroup" colspan="5" class="fi-ta-header-group-cell" style="text-align: center">Accomplishment</th>
                    </tr>
                    <tr>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right">Quarter</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right">OPB Compliance %</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right">Target</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right; color: var(--success-600)">Met</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right; color: var(--success-600)">Exceeded</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right; color: var(--danger-600)">Unmet</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right; color: var(--warning-600)">Deferred</th>
                        <th scope="col" class="fi-ta-header-cell" style="text-align: right">N/A</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="fi-ta-row">
                            <th scope="row" class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right">Q{{ $row['quarter'] }}</th>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right">
                                @if ($row['compliance'] === null)
                                    —
                                @else
                                    <x-filament::badge :color="match (true) {
                                        $row['compliance'] < 50 => 'danger',
                                        $row['compliance'] < 85 => 'warning',
                                        default => 'success',
                                    }">
                                        {{ number_format($row['compliance'], 2) }}%
                                    </x-filament::badge>
                                @endif
                            </td>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right">{{ $row['target'] }}</td>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right; color: var(--success-600)">{{ $row['met'] }}</td>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right; color: var(--success-600)">{{ $row['exceeded'] }}</td>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right; color: var(--danger-600)">{{ $row['unmet'] }}</td>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right; color: var(--warning-600)">{{ $row['deferred'] }}</td>
                            <td class="fi-ta-cell" style="padding: 0.75rem 1rem; text-align: right">{{ $row['na'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </x-filament::section>
