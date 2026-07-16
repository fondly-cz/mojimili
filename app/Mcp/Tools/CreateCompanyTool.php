<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use App\Models\CompanyEmployee;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-company')]
#[Title('Přidat firmu')]
#[Description('Založí novou firmu v CRM a volitelně k ní přidá kontaktní osoby. Vrácené company_id (a company_employee_id) použij v nástroji create-calculation, aby byla kalkulace navázaná na skutečný záznam firmy, ne jen na volný text.')]
class CreateCompanyTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ico' => 'nullable|string|max:20',
            'dic' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:0',
            'annual_revenue' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,prospect',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:255',
            'contacts.*.position' => 'nullable|string|max:255',
        ], [
            'status.in' => 'Stav firmy musí být "active", "inactive" nebo "prospect".',
            'website.url' => 'Web musí být platná URL včetně https://.',
            'contacts.*.name.required' => 'U každé kontaktní osoby uveď alespoň jméno.',
        ]);

        $contacts = $validated['contacts'] ?? [];
        unset($validated['contacts']);

        $company = Company::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
        ]);

        foreach ($contacts as $contact) {
            $company->employees()->create($contact);
        }

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
            "Firma \"%s\" byla založena (company_id %d, stav %s%s).%s\n".
            'Detail v CRM: %s',
            $company->name,
            $company->id,
            $company->status,
            $company->ico ? ', IČO '.$company->ico : '',
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
            'name' => $schema->string()
                ->description('Název firmy.')
                ->required(),

            'ico' => $schema->string()
                ->description('IČO firmy.'),

            'dic' => $schema->string()
                ->description('DIČ firmy.'),

            'email' => $schema->string()
                ->description('Hlavní e-mail firmy.'),

            'phone' => $schema->string()
                ->description('Telefon firmy.'),

            'website' => $schema->string()
                ->description('Web firmy včetně https://.'),

            'address' => $schema->string()
                ->description('Ulice a číslo popisné.'),

            'city' => $schema->string()
                ->description('Město.'),

            'state' => $schema->string()
                ->description('Kraj nebo region.'),

            'postal_code' => $schema->string()
                ->description('PSČ.'),

            'country' => $schema->string()
                ->description('Země.'),

            'industry' => $schema->string()
                ->description('Obor podnikání, například "IT", "Stavebnictví".'),

            'employee_count' => $schema->integer()
                ->description('Počet zaměstnanců firmy.'),

            'annual_revenue' => $schema->number()
                ->description('Roční obrat firmy v Kč.'),

            'notes' => $schema->string()
                ->description('Interní poznámka k firmě.'),

            'status' => $schema->string()
                ->enum(['active', 'inactive', 'prospect'])
                ->description('Stav firmy: aktivní zákazník, neaktivní, nebo prospekt.')
                ->default('active'),

            'contacts' => $schema->array()
                ->description('Kontaktní osoby firmy, které se rovnou založí. Jejich ID pak lze použít jako company_employee_id v kalkulaci.')
                ->items($schema->object([
                    'name' => $schema->string()
                        ->description('Jméno kontaktní osoby.')
                        ->required(),

                    'email' => $schema->string()
                        ->description('E-mail kontaktní osoby.'),

                    'phone' => $schema->string()
                        ->description('Telefon kontaktní osoby.'),

                    'position' => $schema->string()
                        ->description('Pozice kontaktní osoby ve firmě.'),
                ])),
        ];
    }
}
