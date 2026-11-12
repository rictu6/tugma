<x-filament-widgets::widget>
    <div style="display: grid; gap: 1.5rem">
        @include('filament.app.widgets.quarterly-performance-table', ['heading' => 'Quarterly OPB performance', 'description' => null, 'rows' => $rows])
        @include('filament.app.widgets.quarterly-performance-table', [
            'heading' => 'Cumulative Quarterly OPB performance',
            'rows' => $cumulativeRows,
        ])
    </div>
</x-filament-widgets::widget>
