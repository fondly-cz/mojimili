<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCalculationTool;
use App\Mcp\Tools\CreateCompanyTool;
use App\Mcp\Tools\CreateServiceTool;
use App\Mcp\Tools\GetCalculationTool;
use App\Mcp\Tools\ListCalculationsTool;
use App\Mcp\Tools\ListCompaniesTool;
use App\Mcp\Tools\ListServicesTool;
use App\Mcp\Tools\UpdateServiceTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Fondly CRM')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Tento server zpřístupňuje CRM: katalog služeb (ceníkové položky) a kalkulace (nabídky pro zákazníky).

Postup při vytváření kalkulace:
1. Zavolej list-services a najdi ID služeb, které má kalkulace obsahovat. Nikdy si ID nevymýšlej.
2. Volitelně zavolej list-companies, pokud má být kalkulace navázaná na firmu v CRM. Když firma v CRM ještě
   není, založ ji nástrojem create-company a použij vrácené company_id (případně company_employee_id).
3. Zavolej create-calculation. Neuvedeš-li u položky cenu, dny nebo periodu platby, převezmou se z katalogu služby
   (cena = cost * (1 + margin/100)).
4. Vrácenou veřejnou URL (public_url) můžeš poslat zákazníkovi, aby si položky odsouhlasil.

Položky lze zanořovat: každé položce dej `key` a podřízené položce nastav `parent_key` na klíč rodiče.
Ceny jsou v Kč bez DPH. Správu služeb (create-service, update-service) smí volat pouze administrátor.
TEXT)]
class CrmServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListServicesTool::class,
        CreateServiceTool::class,
        UpdateServiceTool::class,
        ListCompaniesTool::class,
        CreateCompanyTool::class,
        ListCalculationsTool::class,
        GetCalculationTool::class,
        CreateCalculationTool::class,
    ];
}
