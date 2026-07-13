<?php

namespace App\Mcp\Tools;

use App\Models\Company;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-companies')]
#[Title('Seznam firem')]
#[Description('Vyhledá firmy v CRM včetně jejich kontaktních osob. Použij pro zjištění company_id a company_employee_id před vytvořením kalkulace.')]
class ListCompaniesTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,prospect',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $companies = Company::query()
            ->with('employees')
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ico', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->limit($validated['limit'] ?? 25)
            ->get();

        if ($companies->isEmpty()) {
            return Response::text('Nenalezena žádná firma odpovídající zadání.');
        }

        return Response::text($companies->map(fn (Company $company) => [
            'id' => $company->id,
            'name' => $company->name,
            'ico' => $company->ico,
            'email' => $company->email,
            'phone' => $company->phone,
            'status' => $company->status,
            'employees' => $company->employees->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
            ])->all(),
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Hledaný výraz v názvu firmy, IČO nebo e-mailu.'),

            'status' => $schema->string()
                ->enum(['active', 'inactive', 'prospect'])
                ->description('Omezení na firmy v daném stavu.'),

            'limit' => $schema->integer()
                ->description('Maximální počet vrácených firem (1-100).')
                ->default(25),
        ];
    }
}
