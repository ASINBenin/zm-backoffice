<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Mise en forme saisie dans l'éditeur (Quill) : le HTML est reconstruit avec une liste fermée de balises, sans aucun
 * attribut sauf le lien d'un <a> en http, https ou mailto. Le texte des balises refusées est gardé, échappé.
 * h1 et div restent acceptés pour les commentaires écrits avec l'ancien éditeur (Trix).
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'div', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'a',
    ];
    private const RENAMED_TAGS = ['b' => 'strong', 'i' => 'em', 'del' => 's', 'strike' => 's'];
    /** Balises dont le contenu n'est pas du texte à afficher. */
    private const DROPPED_TAGS = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'svg', 'math', 'head'];
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $source = new DOMDocument();
        $previousErrorsSetting = libxml_use_internal_errors(true);
        // L'en-tête force la lecture en UTF-8 ; LIBXML_NONET interdit tout accès réseau
        $source->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorsSetting);

        $output = new DOMDocument('1.0', 'UTF-8');
        $root = $output->appendChild($output->createElement('div'));
        $body = $source->getElementsByTagName('body')->item(0);
        if ($body !== null) {
            $this->copyChildren($body, $root, $output);
        }
        // Un éditeur vidé laisse des balises sans texte
        if (trim($root->textContent) === '') {
            return '';
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $output->saveHTML($child);
        }

        return trim($result);
    }

    private function copyChildren(DOMNode $from, DOMNode $to, DOMDocument $output): void
    {
        foreach ($from->childNodes as $node) {
            if ($node instanceof DOMText) {
                $to->appendChild($output->createTextNode($node->textContent));
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            $tag = self::RENAMED_TAGS[$tag] ?? $tag;
            if (\in_array($tag, self::DROPPED_TAGS, true)) {
                continue;
            }
            $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';
            if (!\in_array($tag, self::ALLOWED_TAGS, true) || ($tag === 'a' && !$this->isAllowedUrl($href))) {
                $this->copyChildren($node, $to, $output);
                continue;
            }

            $element = $output->createElement($tag);
            if ($tag === 'a') {
                $element->setAttribute('href', $href);
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
            $to->appendChild($element);
            $this->copyChildren($node, $element, $output);
        }
    }

    private function isAllowedUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return \is_string($scheme) && \in_array(strtolower($scheme), self::ALLOWED_URL_SCHEMES, true);
    }
}
