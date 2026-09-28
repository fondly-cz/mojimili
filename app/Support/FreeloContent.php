<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Converts comment HTML exported from Freelo into the CRM's rich text.
 *
 * Freelo marks mentions as <span data-freelo-mention data-freelo-user-id="…"> and inline files
 * as <a data-freelo-file='{"uuid": …}'> without a href. Mentions become plain "@Name" text and
 * files link to the imported attachment; the result still goes through RichText when stored.
 */
class FreeloContent
{
    /**
     * @param  array<int, string>  $userNames  Freelo user ID => name to show in the mention
     * @param  array<string, string>  $fileUrls  Freelo file UUID => URL of the imported attachment
     */
    public static function toHtml(?string $html, array $userNames = [], array $fileUrls = []): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;
        // The XML prolog makes DOMDocument read the fragment as UTF-8.
        @$document->loadHTML('<?xml encoding="UTF-8"><div id="freelo-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//span[@data-freelo-mention]')) as $mention) {
            /** @var DOMElement $mention */
            $name = $userNames[(int) $mention->getAttribute('data-freelo-user-id')] ?? null;
            $text = $name !== null ? '@'.$name : $mention->textContent;
            $mention->parentNode->replaceChild($document->createTextNode($text), $mention);
        }

        foreach (iterator_to_array($xpath->query('//a[@data-freelo-file]')) as $link) {
            /** @var DOMElement $link */
            $file = json_decode($link->getAttribute('data-freelo-file'), true);
            $url = is_array($file) ? ($fileUrls[$file['uuid'] ?? ''] ?? null) : null;

            if ($url === null) {
                // The file was not imported; keep its name so the text still makes sense.
                $link->parentNode->replaceChild($document->createTextNode($link->textContent), $link);

                continue;
            }

            $link->setAttribute('href', $url);
            $link->removeAttribute('data-freelo-file');
            $link->removeAttribute('data-filename');
        }

        $root = $document->getElementById('freelo-root');
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /**
     * Freelo stores a task's description as a comment flagged is_description.
     *
     * @param  list<array<string, mixed>>  $comments
     * @return array{0: ?array<string, mixed>, 1: list<array<string, mixed>>} The description comment and the rest.
     */
    public static function splitDescription(array $comments): array
    {
        $description = null;
        $rest = [];

        foreach ($comments as $comment) {
            if ($description === null && ! empty($comment['is_description'])) {
                $description = $comment;
            } else {
                $rest[] = $comment;
            }
        }

        return [$description, $rest];
    }
}
