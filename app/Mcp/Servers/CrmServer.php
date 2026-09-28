<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCalculationTool;
use App\Mcp\Tools\CreateCompanyTool;
use App\Mcp\Tools\CreateInvoiceTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateServiceTool;
use App\Mcp\Tools\CreateTodoCommentTool;
use App\Mcp\Tools\CreateTodolistFromCalculationTool;
use App\Mcp\Tools\CreateTodolistTool;
use App\Mcp\Tools\CreateTodoTool;
use App\Mcp\Tools\CreateUploadLinkTool;
use App\Mcp\Tools\CreateUserTool;
use App\Mcp\Tools\CreateWorkReportTool;
use App\Mcp\Tools\DeleteInvoiceTool;
use App\Mcp\Tools\DeleteTodoCommentTool;
use App\Mcp\Tools\DeleteWorkReportTool;
use App\Mcp\Tools\GetCalculationTool;
use App\Mcp\Tools\GetInvoiceTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListCalculationsTool;
use App\Mcp\Tools\ListCompaniesTool;
use App\Mcp\Tools\ListInvoicesTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListServicesTool;
use App\Mcp\Tools\ListUninvoicedWorkReportsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\UpdateCalculationTool;
use App\Mcp\Tools\UpdateInvoiceTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateServiceTool;
use App\Mcp\Tools\UpdateTodoCommentTool;
use App\Mcp\Tools\UpdateTodoTool;
use App\Mcp\Tools\UpdateWorkReportTool;
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

Existující kalkulaci uprav nástrojem update-calculation – vyplň jen pole, která se mají změnit. Uvedeš-li items,
nahradí se jimi všechny dosavadní položky (a zruší se u nich případné odsouhlasení zákazníkem); vynecháš-li items,
položky zůstanou beze změny.

Položky lze zanořovat: každé položce dej `key` a podřízené položce nastav `parent_key` na klíč rodiče.
Ceny jsou v Kč bez DPH. Správu služeb (create-service, update-service) smí volat pouze administrátor.

Projekty a úkoly (odsouhlasená kalkulace = zadání práce):
1. Zavolej list-projects a najdi projekt, případně založ nový nástrojem create-project.
2. Z kalkulace vytvoř seznam úkolů nástrojem create-todolist-from-calculation – uveď calculation_id
   a project_id (nebo project_name, chceš-li projekt rovnou založit; firma se převezme z kalkulace).
   Neuvedeš-li item_ids, převezmou se všechny položky, které zákazník odsouhlasil. Zanoření položek
   se do úkolů přenese a u každého úkolu zůstane vazba na zdrojovou položku kalkulace.
3. Ruční seznam úkolů založ nástrojem create-todolist (úkoly zanoříš stejně přes `key` a `parent_key`).
4. Detail projektu i ID jednotlivých úkolů získáš nástrojem get-project, jeden úkol pak upravíš
   nástrojem update-todo (dokončení, přiřazení řešitele, termín).
5. Odpracovaný čas k úkolu vykážeš nástrojem create-work-report (upravíš update-work-report, smažeš
   delete-work-report). K jednomu úkolu může vykazovat víc lidí – ID osob zjistíš nástrojem list-users.
   Sazba nového výkazu: vlastní sazba výkazu, jinak sazba osoby v projektu (user_rates u update-project),
   jinak výchozí sazba projektu (hourly_rate u create-project/update-project).
6. Komentáře k úkolu (jako ve Freelu) přidáš nástrojem create-todo-comment, i s obrázky a dalšími
   přílohami (víc souborů k jednomu komentáři). Přílohu předej jako url, kterou si server stáhne
   (např. dočasný odkaz z Freela), nebo lokální soubor nahraj na odkaz z create-upload-link (curl -F)
   a předej vrácené upload_id. Base64 použij jen tehdy, když nejde ani jedno. Upravíš je update-todo-comment, smažeš
   delete-todo-comment. Při přenosu z jiného systému zachovej původní datum (created_at); autora
   nejdřív najdi v list-users, případně ho založ nástrojem create-user, a uveď jeho user_id
   (jen jméno lze předat jako author_name). Komentáře úkolů vrací get-project. Popis úkolu může být HTML i Markdown.
7. Nové uživatele (kolegy, externisty) zakládá create-user. Bez role jsou jen osobami pro úkoly, výkazy
   a komentáře; roli s přístupem do CRM smí přidělit pouze administrátor.

Fakturace (CRM fakturu nevystavuje, jen eviduje její číslo a odkaz a označí výkazy jako vyfakturované):
1. Nevyfakturované výkazy najdeš nástrojem list-uninvoiced-work-reports (filtr podle projektu, firmy,
   osoby a období; vrací i součty).
2. Fakturu zaeviduj nástrojem create-invoice s ID výkazů. Jedna faktura smí obsahovat výkazy z více projektů
   i firem, každý výkaz ale smí být jen v jedné faktuře. Volitelná hromadná hourly_rate přepíše sazbu výkazů.
3. Faktury vypíšeš nástrojem list-invoices, detail s výkazy get-invoice. Výkazy do faktury přidáš nebo z ní
   odebereš nástrojem update-invoice, delete-invoice fakturu smaže a výkazy vrátí k fakturaci.
   Vyfakturovaný výkaz nelze upravit ani smazat, dokud ho z faktury neodebereš.
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
        UpdateCalculationTool::class,
        ListProjectsTool::class,
        GetProjectTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        CreateTodolistTool::class,
        CreateTodolistFromCalculationTool::class,
        CreateTodoTool::class,
        UpdateTodoTool::class,
        CreateTodoCommentTool::class,
        UpdateTodoCommentTool::class,
        DeleteTodoCommentTool::class,
        CreateUploadLinkTool::class,
        CreateWorkReportTool::class,
        UpdateWorkReportTool::class,
        DeleteWorkReportTool::class,
        ListUsersTool::class,
        CreateUserTool::class,
        ListUninvoicedWorkReportsTool::class,
        ListInvoicesTool::class,
        GetInvoiceTool::class,
        CreateInvoiceTool::class,
        UpdateInvoiceTool::class,
        DeleteInvoiceTool::class,
    ];
}
