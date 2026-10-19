<?php

namespace App\Models;

/**
 * Czech sentence for an activity entry. The person is shown next to it, so the wording is impersonal.
 */
class ActivityText
{
    private const FIELDS = [
        'name' => 'název', 'description' => 'popis', 'days' => 'odhad (dny)', 'assigned_user_id' => 'řešitel',
        'due_date' => 'termín', 'due_time' => 'čas termínu', 'priority' => 'priorita', 'estimated_minutes' => 'odhad času',
        'parent_id' => 'nadřazený úkol', 'todolist_id' => 'seznam', 'status' => 'stav', 'hourly_rate' => 'sazba',
        'budget' => 'rozpočet', 'budget_minutes' => 'rozpočet (čas)', 'company_id' => 'firma', 'body' => 'text',
        'content' => 'obsah', 'minutes' => 'doba', 'date' => 'datum', 'started_at' => 'začátek', 'ended_at' => 'konec',
        'user_id' => 'osoba', 'invoice_id' => 'faktura', 'caption' => 'popisek', 'role' => 'role', 'email' => 'e-mail',
        'phone' => 'telefon', 'labels' => 'štítky', 'price' => 'cena', 'payment_period' => 'platba',
        'is_required' => 'povinná', 'item_parent' => 'zařazení', 'customer_name' => 'jméno', 'customer_email' => 'e-mail',
        'customer_phone' => 'telefon', 'customer_company' => 'firma', 'show_vat' => 'ceny s DPH', 'note' => 'poznámka',
        'valid_days' => 'platnost (dny)',
    ];

    private const NOUNS = [
        'project' => 'projekt', 'todolist' => 'seznam úkolů', 'todo' => 'úkol', 'comment' => 'komentář',
        'work_report' => 'výkaz času', 'document' => 'soubor projektu', 'note' => 'poznámka projektu', 'todo_attachment' => 'příloha úkolu',
        'comment_attachment' => 'příloha komentáře', 'invoice' => 'faktura', 'calculation' => 'kalkulace',
        'company' => 'firma', 'contact' => 'kontakt', 'service' => 'služba', 'user' => 'uživatel',
    ];

    public static function describe(Activity $activity): string
    {
        $p = $activity->properties ?? [];
        $label = $activity->subject_label ? "„{$activity->subject_label}“" : '';
        [$subject, $action] = array_pad(explode('.', $activity->event, 2), 2, '');

        $special = match ($activity->event) {
            'todo.created' => ($p['subtask'] ?? false ? 'Vytvořen podúkol ' : 'Vytvořen úkol ').$label.(isset($p['todolist']) ? " v seznamu „{$p['todolist']}“" : ''),
            'todo.completed' => "Dokončen úkol {$label}",
            'todo.reopened' => "Znovu otevřen úkol {$label}",
            'comment.created' => "Přidán komentář k úkolu {$label}",
            'comment.updated' => "Upraven komentář k úkolu {$label}",
            'comment.deleted' => "Smazán komentář k úkolu {$label}",
            'work_report.created' => 'Vykázán čas '.self::minutes($p['minutes'] ?? 0).self::reportTail($p, $label),
            'work_report.updated' => "Upraven výkaz času u úkolu {$label}".self::changes($p),
            'work_report.deleted' => 'Smazán výkaz času '.self::minutes($p['minutes'] ?? 0)." u úkolu {$label}",
            'todolist.archived' => "Archivován seznam úkolů {$label}",
            'todolist.unarchived' => "Obnoven z archivu seznam úkolů {$label}",
            'todolist.deleted' => "Smazán seznam úkolů {$label}".(isset($p['todos']) ? " ({$p['todos']} úkolů)" : ''),
            'project.member_added' => "Do projektu přidán(a) {$label}".self::access($p),
            'project.member_access_changed' => "Změněn přístup osoby {$label}".self::access($p),
            'project.member_removed' => "Z projektu odebrán(a) {$label}",
            'project.invited' => "Pozvána e-mailem osoba {$label}".self::access($p),
            'project.invitation_cancelled' => "Zrušena pozvánka pro {$label}",
            'calculation.confirmed' => "Potvrzena kalkulace {$label}",
            'calculation.unconfirmed' => "Zrušeno potvrzení kalkulace {$label}",
            'calculation.item_added' => 'Přidána položka „'.($p['item'] ?? '').'“'.(isset($p['price']) ? ' ('.self::value($p['price']).' Kč)' : ''),
            'calculation.item_removed' => 'Odebrána položka „'.($p['item'] ?? '').'“'.(isset($p['price']) ? ' ('.self::value($p['price']).' Kč)' : ''),
            'calculation.item_changed' => 'Upravena položka „'.($p['item'] ?? '').'“'.self::changes($p),
            'calculation.items_replaced' => 'Nahrazeny všechny položky kalkulace ('.($p['count'] ?? 0).')',
            default => null,
        };

        if ($special !== null) {
            return trim($special);
        }

        $noun = self::NOUNS[$subject] ?? $subject;

        return trim(match ($action) {
            'created' => "Vytvořen(a) {$noun} {$label}",
            'deleted' => "Smazán(a) {$noun} {$label}",
            default => "Upraven(a) {$noun} {$label}".self::changes($p),
        });
    }

    private static function minutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? ($m > 0 ? "{$h} h {$m} min" : "{$h} h") : "{$m} min";
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function reportTail(array $p, string $label): string
    {
        return ($label !== '' ? " u úkolu {$label}" : '').(! empty($p['person']) ? " ({$p['person']})" : '');
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function access(array $p): string
    {
        if (! array_key_exists('all_todolists', $p)) {
            return '';
        }

        if ($p['all_todolists']) {
            return ' – vidí všechny seznamy';
        }

        $lists = $p['todolists'] ?? [];

        return $lists === [] ? ' – nevidí žádný seznam' : ' – vidí seznamy: '.implode(', ', $lists);
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private static function changes(array $p): string
    {
        $parts = [];

        foreach ($p['changes'] ?? [] as $field => $change) {
            $name = self::FIELDS[$field] ?? $field;
            $parts[] = array_key_exists('to', $change)
                ? sprintf('%s: %s → %s', $name, self::value($change['from'] ?? null), self::value($change['to']))
                : $name;
        }

        return $parts === [] ? '' : ' ('.implode(', ', $parts).')';
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '—',
            is_bool($value) => $value ? 'ano' : 'ne',
            default => (string) $value,
        };
    }
}
