<?php

namespace App\Mcp\Tools;

use App\Models\TodoComment;
use App\Support\RemoteFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use RuntimeException;

/**
 * Comment attachments sent through MCP, shared by the create and update tools. A client
 * passes either a URL the server downloads itself, or the file content in base64.
 */
trait DecodesCommentAttachments
{
    /**
     * @return array<string, string>
     */
    protected function attachmentRules(): array
    {
        return [
            'attachments' => 'nullable|array|max:20',
            'attachments.*.name' => 'required_without:attachments.*.url|nullable|string|max:255',
            'attachments.*.url' => 'required_without:attachments.*.content_base64|nullable|string|max:4096',
            'attachments.*.content_base64' => 'required_without:attachments.*.url|nullable|string',
            'attachments.*.caption' => 'nullable|string|max:255',
        ];
    }

    protected function attachmentsSchema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->description('Přílohy komentáře – obrázky i jiné soubory, nejvýš 20 najednou a každá do 20 MB. Obrázky se v CRM zobrazí jako náhledy. U každé přílohy uveď buď url (preferované), nebo content_base64.')
            ->items($schema->object([
                'url' => $schema->string()->description('Veřejná https adresa souboru, který si server sám stáhne (např. dočasný odkaz z Freela). Přednostně používej tohle místo base64.'),
                'name' => $schema->string()->description('Název souboru včetně přípony, např. "logo.png". U url je volitelný – jinak se vezme z odpovědi serveru.'),
                'content_base64' => $schema->string()->description('Obsah souboru v base64 – jen když soubor nemá adresu ke stažení.'),
                'caption' => $schema->string()->description('Volitelný popisek přílohy.'),
            ]));
    }

    /**
     * Downloads or decodes the attachments and checks their size; returns the files, or an error message.
     *
     * @param  array<int, array{name?: ?string, url?: ?string, content_base64?: ?string, caption?: ?string}>  $attachments
     * @return array{0: list<array{name: string, content: string, caption: ?string}>, 1: ?string}
     */
    protected function decodeAttachments(array $attachments): array
    {
        $files = [];
        $maxBytes = TodoComment::MAX_FILE_KILOBYTES * 1024;

        foreach ($attachments as $attachment) {
            $name = $attachment['name'] ?? null;

            if (! empty($attachment['url'])) {
                try {
                    $download = app(RemoteFile::class)->download($attachment['url'], $maxBytes);
                } catch (RuntimeException $e) {
                    return [[], sprintf('Přílohu z %s se nepodařilo stáhnout: %s', $attachment['url'], $e->getMessage())];
                }

                $content = $download['content'];
                $name = $name ?: ($download['name'] ?? 'priloha');
            } else {
                // Data URLs ("data:image/png;base64,...") are accepted as well.
                $encoded = preg_replace('/^data:[^,]*;base64,/', '', (string) ($attachment['content_base64'] ?? ''));
                $content = base64_decode($encoded, true);

                if ($content === false || $content === '') {
                    return [[], sprintf('Příloha "%s" nemá platný obsah v base64.', $name)];
                }

                if (strlen($content) > $maxBytes) {
                    return [[], sprintf('Příloha "%s" je větší než %d MB.', $name, TodoComment::MAX_FILE_KILOBYTES / 1024)];
                }
            }

            $files[] = ['name' => basename($name), 'content' => $content, 'caption' => $attachment['caption'] ?? null];
        }

        return [$files, null];
    }
}
