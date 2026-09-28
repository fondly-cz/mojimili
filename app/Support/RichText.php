<?php

namespace App\Support;

use App\Models\Calculation;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML written in the rich editor (todo descriptions, comments), made safe to render with v-html.
 */
class RichText
{
    /**
     * Plain text or Markdown (e.g. from the MCP tools) is converted like a calculation
     * description; everything is sanitized and an untouched editor becomes null.
     */
    public static function toHtml(?string $value): ?string
    {
        $html = Calculation::descriptionToHtml($value);
        $html = $html === null ? '' : self::sanitize($html);

        return self::isBlank($html) ? null : $html;
    }

    public static function sanitize(string $html): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowAttribute('class', ['li', 'ul', 'ol', 'code', 'pre'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(500_000);

        return trim((new HtmlSanitizer($config))->sanitize($html));
    }

    /**
     * An editor left untouched still sends markup like "<p><br></p>"; that is no text.
     */
    private static function isBlank(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html, '<img><hr><table>'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }
}
