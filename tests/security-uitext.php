<?php

// Synthetic game text, real SimpleHTML formatting and book-page serialization.
namespace Aowow {
    class Cfg {
        public static function get(string $key) : string { return '/static'; }
    }
    class Lang {
        public const int FMT_RAW = 0, FMT_HTML = 1, FMT_MARKUP = 2;
    }
}

namespace {
    use Aowow\{Book, Lang, UIText};

    define('AOWOW_REVISION', 69);
    require __DIR__.'/../includes/utilities.php';
    require __DIR__.'/../includes/game/uitext.class.php';
    require __DIR__.'/../includes/components/frontend/book.class.php';
    set_error_handler(function (int $level, string $message) : never { throw new ErrorException($message, 0, $level); });

    $checks = 0;
    function check(bool $condition, string $message) : void {
        global $checks;
        ++$checks;
        if (!$condition) throw new RuntimeException($message);
    }
    function simpleHtml(string $text, int $format) : string {
        return UIText::format('<HTML><BODY>'.$text.'</BODY></HTML>', $format);
    }

    $image = '<IMG src="Interface\\Pictures\\sample" width="64">';
    $expectedImage = '<IMG src="/static/images/wow/Interface/Pictures/sample.png" width="64">';
    check(simpleHtml($image, Lang::FMT_HTML) === $expectedImage, 'Valid SimpleHTML images render with the local rewritten source');
    check(simpleHtml($image, Lang::FMT_MARKUP) === '['.substr($expectedImage, 1, -1).']', 'Valid images become image markup');
    check(simpleHtml($image, Lang::FMT_RAW) === '', 'Raw image output stays unformatted');

    foreach ([
        '<img SRC="picture" height="32" />',
        "<img src='picture' width='32'>",
        '<img width=32 src=picture>',
        "<img\tsrc = \"picture\"\theight = '32'>",
        '<img title="A title with spaces" src="picture">',
    ] as $tag) {
        check(str_starts_with(simpleHtml($tag, Lang::FMT_HTML), '<img'), 'Required source accepts case, whitespace and quoted/unquoted attributes');
        check(str_starts_with(simpleHtml($tag, Lang::FMT_MARKUP), '[img'), 'Valid attribute variants produce image markup');
    }

    $anchor = '<a href="https://example.test"><span>Link</span></a>';
    check(simpleHtml($anchor, Lang::FMT_HTML) === $anchor, 'Anchors with nested content retain their opening and closing tags');
    check(simpleHtml($anchor, Lang::FMT_MARKUP) === '[a href="https://example.test"][span]Link[/span][/a]', 'Anchor closing tags do not require href');
    check(simpleHtml($anchor, Lang::FMT_RAW) === 'Link', 'Raw anchors retain only their text');
    check(simpleHtml('<a href="https://example.test">Link</a>', Lang::FMT_HTML) === 'Link', 'Existing plain-anchor stripping is preserved');

    foreach ([
        '<img>', '<img src="">', '<img src="   ">', '<img data-src="picture">',
        '<img title="src=picture">', '<img src>', '<img src="picture>',
        '<img src="picture" stray>', '<img src="picture"src="other">',
        '<img src="picture" src="">', '</img>',
        '<a>', '<a href="">', '<a title="href=target">', '</a href="target">',
        '<script src="picture">', '</script>',
    ] as $tag) {
        check(str_starts_with(simpleHtml($tag, Lang::FMT_HTML), '&lt;'), 'Invalid or unsupported tags remain escaped: '.$tag);
        check(!str_starts_with(simpleHtml($tag, Lang::FMT_MARKUP), '['), 'Invalid tags do not become supported markup: '.$tag);
    }

    $heading = '<H1>Heading</H1><P>Text</P>';
    check(simpleHtml($heading, Lang::FMT_HTML) === $heading, 'Supported heading and paragraph HTML is preserved');
    check(simpleHtml($heading, Lang::FMT_MARKUP) === '[H1]Heading[/H1][P]Text[/P]', 'Supported heading and paragraph markup is preserved');
    check(simpleHtml($heading, Lang::FMT_RAW) === 'HeadingText', 'Raw headings retain text');
    check(UIText::format('Hello <hic> & friends', Lang::FMT_HTML) === 'Hello &lt;hic&gt; &amp; friends', 'Upstream plain-text escaping is retained');
    check(UIText::format('|cffff0000Red|r', Lang::FMT_HTML) === '<span style="color: #ff0000ff;">Red</span>', 'UI colors retain generated HTML');
    check(UIText::format('|cffff0000Red|r', Lang::FMT_MARKUP) === '[span color=#ff0000]Red[/span]', 'UI colors retain generated markup');
    check(UIText::format("First\nSecond", Lang::FMT_HTML) === 'First<br />Second', 'Line breaks remain supported');

    $book = new Book(['<HTML><BODY>'.$image.'</BODY></HTML>']);
    check($book->jsonSerialize()['pages'] === [$expectedImage], 'Real book pages retain image HTML');
    check(array_values(iterator_to_array($book->iterate())) === [$expectedImage], 'Book iteration supplies renderable images');
    check(json_decode(json_encode($book, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR)['pages'] === [$expectedImage], 'Image HTML survives book JSON serialization');

    echo 'PASS: '.$checks." UI text/image/anchor/book checks\n";
}
