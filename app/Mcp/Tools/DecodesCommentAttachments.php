<?php

namespace App\Mcp\Tools;

use App\Models\TodoComment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Comment attachments sent through MCP as base64, shared by the create and update tools.
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
            'attachments.*.name' => 'required|string|max:255',
            'attachments.*.content_base64' => 'required|string',
        ];
    }

    protected function attachmentsSchema(JsonSchema $schema): Type
    {
        return $schema->array()
            ->description('Přílohy komentáře – obrázky i jiné soubory, nejvýš 20 najednou a každá do 20 MB. Obrázky se v CRM zobrazí jako náhledy.')
            ->items($schema->object([
                'name' => $schema->string()->description('Název souboru včetně přípony, např. "logo.png".')->required(),
                'content_base64' => $schema->string()->description('Obsah souboru v base64.')->required(),
            ]));
    }

    /**
     * Decodes and size-checks the attachments; returns the files, or an error message.
     *
     * @param  array<int, array{name: string, content_base64: string}>  $attachments
     * @return array{0: list<array{name: string, content: string}>, 1: ?string}
     */
    protected function decodeAttachments(array $attachments): array
    {
        $files = [];

        foreach ($attachments as $attachment) {
            // Data URLs ("data:image/png;base64,...") are accepted as well.
            $encoded = preg_replace('/^data:[^,]*;base64,/', '', $attachment['content_base64']);
            $content = base64_decode($encoded, true);

            if ($content === false || $content === '') {
                return [[], sprintf('Příloha "%s" nemá platný obsah v base64.', $attachment['name'])];
            }

            if (strlen($content) > TodoComment::MAX_FILE_KILOBYTES * 1024) {
                return [[], sprintf('Příloha "%s" je větší než %d MB.', $attachment['name'], TodoComment::MAX_FILE_KILOBYTES / 1024)];
            }

            $files[] = ['name' => basename($attachment['name']), 'content' => $content];
        }

        return [$files, null];
    }
}
