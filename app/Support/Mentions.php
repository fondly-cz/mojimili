<?php

namespace App\Support;

use App\Models\Project;
use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;

/**
 * Turns "@Name" in rich text into a mention of a person assigned to the project.
 *
 * The editor and the MCP tools send mentions as plain text; a mention is stored as
 * <span class="mention" data-user-id="…">@Name</span>. RichText strips those attributes from
 * incoming HTML, so a mention is always re-checked against the project's current members.
 */
class Mentions
{
    public static function link(?string $html, ?Project $project): ?string
    {
        // The sanitizer writes "@" as an entity.
        if ($html === null || ! (str_contains($html, '@') || str_contains($html, '&#64;'))) {
            return $html;
        }

        // Longest names first, so "@Jan Novák ml." is not cut short at "@Jan Novák".
        $members = ($project?->members()->get(['users.id', 'users.name']) ?? collect())
            ->filter(fn ($user) => trim($user->name) !== '')
            ->sortByDesc(fn ($user) => mb_strlen($user->name))
            ->values();

        if ($members->isEmpty()) {
            return $html;
        }

        $names = $members->map(fn ($user) => implode('[\s\x{00A0}]+', array_map(
            fn ($word) => preg_quote($word, '/'),
            preg_split('/\s+/u', trim($user->name)),
        )));
        $pattern = '/(?<![\p{L}\p{N}_])@(?:'.$names->map(fn ($name) => "({$name})")->implode('|').')(?![\p{L}\p{N}_])/iu';

        $document = new DOMDocument;
        // The XML prolog makes DOMDocument read the fragment as UTF-8.
        @$document->loadHTML('<?xml encoding="UTF-8"><div id="mentions-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($document);

        // A mention sent back from the editor is a span without attributes by now.
        foreach (iterator_to_array($xpath->query('//span[not(@*)]')) as $span) {
            /** @var DOMElement $span */
            while ($span->firstChild) {
                $span->parentNode->insertBefore($span->firstChild, $span);
            }
            $span->parentNode->removeChild($span);
        }
        $document->documentElement->normalize();

        foreach (iterator_to_array($xpath->query('//text()[not(ancestor::a or ancestor::code or ancestor::pre)]')) as $text) {
            /** @var DOMText $text */
            if (! preg_match_all($pattern, $text->data, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            $fragment = $document->createDocumentFragment();
            $offset = 0;

            foreach ($matches as $match) {
                [$found, $start] = $match[0];
                // The matched group tells which member it is; unmatched groups before it are empty.
                $member = $members[count($match) - 2];

                $fragment->appendChild($document->createTextNode(substr($text->data, $offset, $start - $offset)));

                $mention = $document->createElement('span');
                $mention->setAttribute('class', 'mention');
                $mention->setAttribute('data-user-id', (string) $member->id);
                $mention->appendChild($document->createTextNode('@'.$member->name));
                $fragment->appendChild($mention);

                $offset = $start + strlen($found);
            }

            $fragment->appendChild($document->createTextNode(substr($text->data, $offset)));
            $text->parentNode->replaceChild($fragment, $text);
        }

        $result = '';
        foreach ($document->getElementById('mentions-root')->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }
}
