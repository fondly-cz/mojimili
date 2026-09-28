<?php

namespace App\Mcp\Tools;

use App\Support\McpUpload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-upload-link')]
#[Title('Odkaz pro nahrání souborů')]
#[Description('Vrátí dočasný odkaz (platí 60 minut), na který nahraješ lokální soubory obyčejným multipart POSTem (curl -F). Odpověď obsahuje upload_id každého souboru – ten pak předej jako přílohu v create-todo-comment nebo update-todo-comment. Soubory tak nemusíš převádět do base64.')]
class CreateUploadLinkTool extends Tool
{
    use InteractsWithCrmUser;

    public function handle(Request $request): Response
    {
        if (! $user = $this->crmUser($request)) {
            return $this->accessDenied();
        }

        $url = URL::temporarySignedRoute('mcp-uploads.store', now()->addMinutes(McpUpload::LINK_MINUTES), ['user' => $user]);

        return Response::text(sprintf(
            "Odkaz pro nahrání (platí %d minut):\n%s\n\n"
            ."Jeden soubor:   curl -sS -F 'file=@/cesta/obrazek.png' '%s'\n"
            ."Více souborů:   curl -sS -F 'files[]=@a.png' -F 'files[]=@b.pdf' '%s'\n\n"
            .'Odpověď je JSON {"uploads": [{"upload_id", "name", "size"}]}. upload_id předej v attachments '
            .'nástroje create-todo-comment. Nahrané soubory se po 24 hodinách mažou.',
            McpUpload::LINK_MINUTES,
            $url,
            $url,
            $url,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
