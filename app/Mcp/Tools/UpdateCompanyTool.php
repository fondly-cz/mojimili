<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-company')]
#[Title('Upravit firmu')]
#[Description('Upraví existující firmu v CRM a její kontaktní osoby. Vyplň jen pole, která se mají změnit – ostatní zůstanou beze změny. ID firmy i kontaktních osob získáš nástrojem list-companies.')]
class UpdateCompanyTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'id' => 'required|integer|exists:companies,id',
            'name' => 'sometimes|string|max:255',
            'ico' => 'sometimes|nullable|string|max:20',
            'dic' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'phone' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'address' => 'sometimes|nullable|string',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:255',
            'industry' => 'sometimes|nullable|string|max:255',
            'employee_count' => 'sometimes|nullable|integer|min:0',
            'annual_revenue' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive,prospect',
            'contacts' => 'sometimes|array',
            'contacts.*.id' => 'sometimes|integer|distinct',
            'contacts.*.name' => 'required_without:contacts.*.id|string|max:255',
            'contacts.*.email' => 'required_without:contacts.*.id|email|max:255',
            'contacts.*.phone' => 'sometimes|nullable|string|max:255',
            'contacts.*.position' => 'sometimes|nullable|string|max:255',
            'remove_contact_ids' => 'sometimes|array',
            'remove_contact_ids.*' => 'integer|distinct',
        ], [
            'id.exists' => 'Firma s tímto ID neexistuje. Seznam získáš nástrojem list-companies.',
            'status.in' => 'Stav firmy musí být "active", "inactive" nebo "prospect".',
            'website.url' => 'Web musí být platná URL včetně https://.',
            'contacts.*.name.required_without' => 'U nové kontaktní osoby uveď jméno; existující osobu uprav přes její id.',
            'contacts.*.email.required_without' => 'U nové kontaktní osoby uveď e-mail; existující osobu uprav přes její id.',
        ]);

        $company = Company::with('employees')->findOrFail($validated['id']);
        unset($validated['id']);

        if ($validated === []) {
            return Response::error('Neuvedl jsi žádnou změnu. Vyplň alespoň jedno pole, které se má upravit.');
        }

        $contacts = $validated['contacts'] ?? [];
        $removeContactIds = $validated['remove_contact_ids'] ?? [];

        $foreignContactIds = collect($contacts)
            ->pluck('id')
            ->filter()
            ->merge($removeContactIds)
            ->diff($company->employees->modelKeys());

        if ($foreignContactIds->isNotEmpty()) {
            return Response::error(sprintf(
                'Kontaktní osoby s ID %s k této firmě nepatří. Jejich ID získáš nástrojem list-companies.',
                $foreignContactIds->implode(', '),
            ));
        }

        DB::transaction(function () use ($company, $validated, $contacts, $removeContactIds) {
            $company->update(collect($validated)->except(['contacts', 'remove_contact_ids'])->all());

            foreach ($contacts as $contact) {
                if (isset($contact['id'])) {
                    $company->employees->find($contact['id'])
                        ->update(collect($contact)->except('id')->all());
                } else {
                    $company->employees()->create($contact);
                }
            }

            $company->employees()->whereKey($removeContactIds)->delete();
        });

        $company->load('employees');

        $employeeLines = $company->employees
            ->map(fn (CompanyEmployee $employee) => sprintf(
                '  - %s (ID %d)%s',
                $employee->name,
                $employee->id,
                $employee->email ? ' – '.$employee->email : '',
            ))
            ->implode("\n");

        return Response::text(sprintf(
            "Firma \"%s\" byla upravena (company_id %d, stav %s).\nZměněná pole: %s%s\nDetail v CRM: %s",
            $company->name,
            $company->id,
            $company->status,
            implode(', ', array_keys($validated)),
            $company->employees->isNotEmpty()
                ? "\nKontaktní osoby:\n".$employeeLines
                : '',
            route('companies.show', $company),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('ID upravované firmy.')
                ->required(),

            'name' => $schema->string()
                ->description('Nový název firmy.'),

            'ico' => $schema->string()
                ->description('Nové IČO firmy. null ho smaže.'),

            'dic' => $schema->string()
                ->description('Nové DIČ firmy. null ho smaže.'),

            'email' => $schema->string()
                ->description('Nový hlavní e-mail firmy. null ho smaže.'),

            'phone' => $schema->string()
                ->description('Nový telefon firmy. null ho smaže.'),

            'website' => $schema->string()
                ->description('Nový web firmy včetně https://. null ho smaže.'),

            'address' => $schema->string()
                ->description('Nová ulice a číslo popisné. null je smaže.'),

            'city' => $schema->string()
                ->description('Nové město. null ho smaže.'),

            'state' => $schema->string()
                ->description('Nový kraj nebo region. null ho smaže.'),

            'postal_code' => $schema->string()
                ->description('Nové PSČ. null ho smaže.'),

            'country' => $schema->string()
                ->description('Nová země. null ji smaže.'),

            'industry' => $schema->string()
                ->description('Nový obor podnikání, například "IT", "Stavebnictví". null ho smaže.'),

            'employee_count' => $schema->integer()
                ->description('Nový počet zaměstnanců firmy. null ho smaže.'),

            'annual_revenue' => $schema->number()
                ->description('Nový roční obrat firmy v Kč. null ho smaže.'),

            'notes' => $schema->string()
                ->description('Nová interní poznámka k firmě – nahradí dosavadní. null ji smaže.'),

            'status' => $schema->string()
                ->enum(['active', 'inactive', 'prospect'])
                ->description('Nový stav firmy: aktivní zákazník, neaktivní, nebo prospekt.'),

            'contacts' => $schema->array()
                ->description('Kontaktní osoby k úpravě nebo přidání. Položka s id upraví existující osobu firmy (jen uvedená pole), položka bez id založí novou. Neuvedené osoby zůstanou beze změny.')
                ->items($schema->object([
                    'id' => $schema->integer()
                        ->description('ID existující kontaktní osoby této firmy. Vynech u nové osoby.'),

                    'name' => $schema->string()
                        ->description('Jméno kontaktní osoby. U nové osoby povinné.'),

                    'email' => $schema->string()
                        ->description('E-mail kontaktní osoby. U nové osoby povinný.'),

                    'phone' => $schema->string()
                        ->description('Telefon kontaktní osoby. null ho smaže.'),

                    'position' => $schema->string()
                        ->description('Pozice kontaktní osoby ve firmě. null ji smaže.'),
                ])),

            'remove_contact_ids' => $schema->array()
                ->description('ID kontaktních osob této firmy, které se mají smazat. Kalkulace a projekty navázané na smazanou osobu zůstanou, jen o kontaktní osobu přijdou.')
                ->items($schema->integer()),
        ];
    }
}
