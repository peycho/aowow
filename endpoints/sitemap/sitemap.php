<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class SitemapBaseResponse extends TextResponse
{
    protected string $contentType = MIME_TYPE_XML;

    protected array $expectedGET = array(
        'page' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1]]
    );

    private string $page;

    public function __construct(string $pageParam)
    {
        $this->page = $pageParam;

        parent::__construct($pageParam);
    }

    protected function generate() : void
    {
        // The generator caches XML with catalog epochs and a live guide-publication identity.
        if (array_key_exists('page', $_GET) && !is_int($this->_get['page']))
            (new TemplateResponse($this->page))->generateNotFound(Sitemap::ERR_TITLE, Sitemap::ERR_PAGE);

        $locale = Locale::tryFromRequest($_GET['locale'] ?? null) ?? Locale::getFallback();
        if ($xml = Sitemap::generate($this->page, $this->_get['page'] ?? 1, $locale))
            $this->result = $xml;
        else if (Sitemap::$maxPage)
            (new TemplateResponse($this->page))->generateNotFound(Sitemap::ERR_TITLE, sprintf(Sitemap::ERR_OFFSET, Sitemap::$maxPage));
        else
            (new TemplateResponse($this->page))->generateNotFound(Sitemap::ERR_TITLE, Sitemap::ERR_PAGE);
    }
}

?>
