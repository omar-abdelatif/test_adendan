<?php

namespace App\Modules\Content\Infrastructure;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans the HTML staff write in the news editor (CKEditor) before it is
 * stored, because the public news page prints it as HTML. Keeps formatting,
 * links, images, tables and YouTube embeds; drops scripts, event handlers,
 * javascript: links and anything else that could run in a visitor's browser.
 */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        return $this->purifier()->purify((string) $html);
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr', 'b', 'strong', 'i', 'em', 'u', 's', 'sub', 'sup', 'span[style]', 'div[style]',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
            'ul', 'ol', 'li', 'a[href|title|target]', 'img[src|alt|width|height|style]',
            // HTMLPurifier speaks HTML 4: <figure> wrappers from CKEditor are dropped, their <img> kept.
            'table', 'thead', 'tbody', 'tr', 'th[colspan|rowspan]', 'td[colspan|rowspan]',
            'iframe[src|width|height|frameborder]',
        ]));
        $config->set('CSS.AllowedProperties', ['text-align', 'direction', 'color', 'background-color', 'font-weight', 'font-style', 'text-decoration', 'width', 'height']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('HTML.TargetBlank', true);
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^https://(www\.)?(youtube\.com/embed/|youtube-nocookie\.com/embed/|player\.vimeo\.com/video/)%');
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        return $this->purifier = new HTMLPurifier($config);
    }
}
