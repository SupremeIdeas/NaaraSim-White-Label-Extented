<?php

namespace App\Livewire\Admin;

use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin error-log module (blueprint Section 17.5). Rolling in-app log with a
 * per-day filter and CSV / JSON export for any day — turning "something broke"
 * into "here is exactly what, when, and how often."
 */
#[Layout('components.layouts.admin')]
class ErrorLogViewer extends Component
{
    use WithPagination;

    public string $date = '';

    public string $severity = '';

    /**
     * Re-authorize on every request. The error log can carry sensitive context
     * (user IDs, stack traces), and its CSV/JSON export methods run on the shared
     * `/livewire/update` endpoint where the route's `role:` middleware is not
     * re-applied. booted() runs on the initial load and every update alike.
     */
    public function booted(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    private function query(): Builder
    {
        return ErrorLog::query()
            ->when($this->date !== '', fn ($q) => $q->whereDate('created_at', $this->date))
            ->when($this->severity !== '', fn ($q) => $q->where('severity', $this->severity))
            ->latest();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->query()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'code', 'severity', 'message', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->id, $row->code, $row->severity, $row->message, $row->created_at]);
            }
            fclose($out);
        }, "error-log-{$this->date}.csv", ['Content-Type' => 'text/csv']);
    }

    public function exportJson(): StreamedResponse
    {
        $rows = $this->query()->get()->map(fn ($row) => [
            'id' => $row->id,
            'code' => $row->code,
            'severity' => $row->severity,
            'message' => $row->message,
            'context' => $row->context,
            'created_at' => (string) $row->created_at,
        ]);

        return response()->streamDownload(
            fn () => print($rows->toJson(JSON_PRETTY_PRINT)),
            "error-log-{$this->date}.json",
            ['Content-Type' => 'application/json'],
        );
    }

    public function render()
    {
        return view('livewire.admin.error-log', ['logs' => $this->query()->paginate(20)]);
    }
}
