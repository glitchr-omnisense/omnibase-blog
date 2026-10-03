<?php

namespace Base\Blog\WordPress;

/**
 * What a WordPress page's HTML carries that is not its text: the scripts and
 * styles plugins add (share buttons, forms that need their own JavaScript),
 * the shortcodes left unexpanded, the empty paragraphs, the classes and
 * sizes of the theme.
 */
final class Cleaner
{
    public function clean(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|noscript|iframe(?![^>]*(youtube|vimeo|player)))\b[^>]*>.*?</\1>#is', '', $html);
        // Share-button blocks (Hupso, AddToAny, Jetpack's sharedaddy).
        $html = (string) preg_replace('#<div[^>]*class="[^"]*(hupso|addtoany|sharedaddy|share-buttons)[^"]*"[^>]*>.*?</div>\s*(</div>)?#is', '', $html);
        // Unexpanded shortcodes: [caption ...], [/caption], [gallery ids="1,2"].
        $html = (string) preg_replace('#\[/?[a-z_\-]+(?:\s[^\]]*)?\]#i', '', $html);
        $html = (string) preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html);
        // The theme's own: classes, inline sizes, srcset (the uploads are kept at their full size).
        $html = (string) preg_replace('#\s(class|style|srcset|sizes|decoding|data-[a-z\-]+)="[^"]*"#i', '', $html);
        $html = (string) preg_replace('#<img\b(?![^>]*\bloading=)#i', '<img loading="lazy"', $html);

        return trim($html);
    }
}
