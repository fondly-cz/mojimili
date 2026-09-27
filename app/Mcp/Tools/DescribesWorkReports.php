<?php

namespace App\Mcp\Tools;

use App\Models\WorkReport;

/**
 * Shared JSON shape of a work report for the billing and invoice tools.
 */
trait DescribesWorkReports
{
    /**
     * Relations needed by describeWorkReport(), for eager loading.
     *
     * @return array<int, string>
     */
    protected function workReportRelations(string $prefix = ''): array
    {
        return array_map(fn (string $relation) => $prefix.$relation, [
            'user:id,name',
            'invoice:id,number',
            'todo:id,todolist_id,name',
            'todo.todolist:id,project_id,name',
            'todo.todolist.project:id,name,company_id',
            'todo.todolist.project.company:id,name',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeWorkReport(WorkReport $report): array
    {
        $project = $report->todo?->todolist?->project;

        return [
            'id' => $report->id,
            'date' => $report->date->toDateString(),
            'started_at' => $report->started_at?->format('Y-m-d H:i'),
            'ended_at' => $report->ended_at?->format('Y-m-d H:i'),
            'user_id' => $report->user_id,
            'user_name' => $report->user?->name,
            'minutes' => $report->minutes,
            'hourly_rate' => $report->hourly_rate,
            'amount' => $report->amount,
            'description' => $report->description,
            'todo_id' => $report->todo_id,
            'todo_name' => $report->todo?->name,
            'project_id' => $project?->id,
            'project_name' => $project?->name,
            'company_id' => $project?->company_id,
            'company_name' => $project?->company?->name,
            'invoice_id' => $report->invoice_id,
            'invoice_number' => $report->invoice?->number,
        ];
    }
}
