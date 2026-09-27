# Freelo data model (migration reference)

Snapshot of what Freelo stores, taken from the Freelo API on 2026-09-27 using
the project "🎻PODRAZIL ATLANTIS + FONDLY" (id 524850) and the issued-invoices
list. It is the reference for a future import into this CRM. Values below are
synthetic or shortened. Never copy comment bodies verbatim, because some of them
contain credentials.

Money in Freelo is always an integer in **hundredths of CZK**: `"6250"` = 62.50 Kč.

## Project

| Freelo field | Example | CRM target |
|---|---|---|
| `id` | 524850 | new column `projects.freelo_id` |
| `name` | "🎻PODRAZIL ATLANTIS + FONDLY" | `projects.name` |
| `date_add`, `date_edited_at` | ISO 8601 datetime | `created_at`, `updated_at` |
| `due_date` | null | — (not modelled) |
| `state` | `{id: 1, state: "active"}` (1 active, 2 archived, 3 template) | `projects.status` (`active` / `archived`) |
| `owner.id` | 41977 | `projects.user_id` (after user mapping) |
| `client` | null | `projects.company_id`, matched by hand. Freelo usually has no client set, and the customer is encoded in the project or tasklist name ("… \|\| Chicory s.r.o.") |
| `budget`, `minutes_budget` | null | — |
| `real_minutes_spent`, `real_cost` | 495, 137917 | computed, not imported |
| `workers[]` | `{id, fullname, hour_rate: {amount, currency, is_fixed}}` | `project_user_rates` (user rate within the project) |
| `tasklists[]` | see below | `todolists` |

Workers with a project rate: `get_project` returns `workers[].hour_rate.amount`
in hundredths (Karel 25000 = 250 Kč/h, Martin 0). There is no project-level
rate in Freelo. The CRM adds `projects.hourly_rate` as the fallback.

## Tasklist

| Freelo field | Example | CRM target |
|---|---|---|
| `id` | 1348724 | `todolists.freelo_id` |
| `name` | "podrazil-orchestr-atlantis.cz \| Podrazil" | `todolists.name` |
| `project_id` | 524850 | `todolists.project_id` |
| `date_add`, `date_edited_at` | datetime | timestamps |
| `worker_id` | null | — |
| `minutes`, `cost` | aggregates | computed |

The shared project "《fondly》SPOLUPRÁCE" uses one tasklist per customer
("CH - chicory.cz \|\| Chicory s.r.o."). An import should either split these
into separate CRM projects per company or keep them as todolists and attach the
company manually.

## Task

| Freelo field | Example | CRM target |
|---|---|---|
| `id` | 32649987 | `todos.freelo_id` |
| `name` | "Měsíční údržba" | `todos.name` |
| `parent_task_id` | null / 32649987 | `todos.parent_id` |
| `date_add` | datetime | `created_at` |
| `due_date`, `due_date_end` | datetime / null | `todos.due_date` (no range support) |
| `date_finished`, `finished_by` | datetime, `{id, fullname}` | `todos.completed_at`, `is_done` |
| `state` | `{id: 1, "active"}`, `{id: 5, "finished"}` | `todos.is_done` |
| `author` | `{id, fullname}` | — (not modelled) |
| `worker` | `{id, fullname}` / null | `todos.assigned_user_id` |
| `priority_enum` | null | — |
| `labels[]` | `{uuid, name: "Automatická pravidelná faktura", color: "#15acc0"}` | — (not modelled yet) |
| `tracking_users[]` | users who logged time | derived from work reports |
| `copied_from_task` | `{id: 20069772, project: {...}}` | — (recurring monthly task is a copy of a template task) |
| `multi_project_task` | `is_multi_project: false` | — |
| `relations[]`, `custom_fields[]` | empty in this project | — |
| `total_time_estimate`, `users_time_estimates[]` | null / [] | `todos.days` holds only a day estimate |
| `count_subtasks` | 3 | computed |
| `minutes`, `cost` | aggregates | computed |

The description is the first comment (`is_description`). For some tasks
`get_task_description` returns 404, meaning there is no description.

## Subtask (taskcheck)

`get_subtasks` returns `{id (taskcheck row), type: "subtask", task_id, name,
date_add, due_date, worker, state, count_comments, labels}`. Smart subtasks
have a real `task_id` and behave like tasks. Import them as `todos` with
`parent_id`. Simple checks (`task_id: null`) become child todos too.

## Comment

| Freelo field | Notes |
|---|---|
| `id`, `date_add`, `date_edited_at` | |
| `author` | `{id, fullname}` |
| `content` | HTML with `<a data-freelo-file='{…json…}'>` attachments and `<span data-freelo-mention data-freelo-user-id>` mentions |
| `task`, `tasklist`, `project` | parent references |
| `files[]` | `{uuid, filename, caption, description, date_add, size}` |

The CRM has no comments or files yet. If they are imported, file binaries must
be downloaded through `download_file` by UUID. Comment bodies can hold secrets:
comment 32229820 contains WordPress credentials.

## Work report (výkaz)

| Freelo field | Example | CRM target |
|---|---|---|
| `id` | 11069466 | `work_reports.freelo_id` |
| `date_reported` | `2026-09-15T15:43:33+02:00` | `work_reports.date` (the time part is dropped) |
| `date_add`, `date_edited_at` | datetime | timestamps |
| `minutes` | 15 | `work_reports.minutes` |
| `cost` | `{amount: "6250", currency: "CZK"}` | `work_reports.hourly_rate = cost / 100 / (minutes / 60)` |
| `note` | "Ořez 2 videí" / null | `work_reports.description` |
| `worker` | `{id, fullname}` | `work_reports.user_id` |
| `author` | `{id, fullname}` | — (who logged it, usually the same as the worker) |
| `task`, `tasklist`, `project` | refs + task labels | `work_reports.todo_id` |

`cost` is frozen at the rate valid when the report was logged, so recomputing
the rate from `cost / minutes` preserves history.

## Issued invoice

| Freelo field | Example | CRM target |
|---|---|---|
| `id` | 81858 | `invoices.freelo_id` |
| `date_add` | datetime | `invoices.issued_at` |
| `note` | "2026-08-podrazil" | `invoices.number` (the notes are used as invoice labels) |
| `subject` | null | `invoices.note` |
| `extern_invoice` | `[]` or `{url, subject}` | `invoices.url` |
| `currency`, `price`, `minutes` | totals | computed |
| `inv_items[]` | `{id, name (project name), minutes, price, reports[]}` | not stored, grouped by project on the fly |
| `inv_items[].reports[]` | `{id, work_report_id, project_name, tasklist_name, name, minutes, price}` | set `work_reports.invoice_id` via `work_report_id` |

Important: an invoice item price can differ from the sum of its reports. For
example, "2026-08-podrazil" has 300 minutes for 6 000 Kč (1 200 Kč/h), while the
underlying reports were logged at 0 Kč/h. On import, reports should take the
**invoiced** rate (`item.price / item.minutes`) so that CRM totals match the
real invoices. The CRM supports this directly by setting a rate for all
selected reports when invoicing.

Invoices named "…-karel" / "…-pilar" are colleagues' invoices, so they cover
costs rather than revenue. They use the same structure and import the same way.

## Users

Freelo user ids seen: 41977 (Martin Kokeš), 271428 (Karel Nakládal), 38003 (Jan
Pilař), 190814 (Vít Fencl). Map them to CRM users by email (`get_project_workers`
returns `email`).
