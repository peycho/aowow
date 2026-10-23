<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class GuidesBaseResponse extends TemplateResponse // implements ICache
{
    use TrListPage/* , TrCache */;

 // protected  int    $cacheType  = CACHE_TYPE_LIST_PAGE; // really do? cache would need to be destroyed externally with each guide status update
    protected  int    $type       = Type::GUIDE;

    protected  string $template   = 'list-page-generic';
    protected  string $pageName   = 'guides';
    protected ?int    $activeTab  = parent::TAB_GUIDES;
    protected  array  $breadcrumb = [6];

    protected  array  $validCats  = [null, 1, 2, 3, 4, 5, 6, 7, 8, 9];

    public string $guideIntro = '';
    public array $guideCategories = [];

    public function __construct(string $rawParam)
    {
        $this->getCategoryFromUrl($rawParam);

        parent::__construct($rawParam);
    }

    protected function generate() : void
    {
        $this->h1 = Util::ucFirst(Lang::game('guides'));


        if ($this->category)
            $this->breadcrumb[] = $this->category[0];


        array_unshift($this->title, $this->h1);
        if ($this->category)
        {
            array_unshift($this->title, Lang::guide('category', $this->category[0]));
            $this->h1 = Lang::guide('category', $this->category[0]);
        }

        require_once __DIR__.'/../../includes/components/guidediscovery.class.php';
        $this->guideIntro = $this->listPage === 1 ? Lang::guide('intro', $this->category[0] ?? 0) : '';
        foreach (GuideDiscovery::categories(Lang::getLocale()) as $category)
            $this->guideCategories[] = ['category' => $category, 'name' => Lang::guide('category', $category),
                'href' => Template\PageTemplate::canonicalUrl(['guides' => (string)$category, 'locale' => Lang::getLocale()->value])];


        $conditions = array(
            ['locale', Lang::getLocale()->value],
            ['status', GuideMgr::STATUS_ARCHIVED, '!'],     // never archived guides
            [
                DB::OR,
                ['status', GuideMgr::STATUS_APPROVED],      // currently approved
                ['rev', 0, '>']                             // has previously approved revision
            ]
        );
        if ($this->category)
            $conditions[] = ['category', $this->category[0]];

        $this->redButtons = [BUTTON_GUIDE_NEW => User::canWriteGuide()];

        $guides = new GuideList($conditions, ['pagination' => $this->paginationOptions()]);
        $this->setPagination($guides->getMatches());

        $this->lvTabs = new Tabs(['parent' => new JsExpression("\$WH.ge('tabs-generic')")]);

        $this->lvTabs->addListviewTab(new Listview(array(
            'data'       => $guides->getListviewData(),
            'name'       => Util::ucFirst(Lang::game('guides')),
            'hiddenCols' => ['patch'],                      // pointless: display date instead
            'extraCols'  => [new JsExpression('Listview.extraCols.date')]    // ok
        ), GuideList::$brickFile));

        parent::generate();
        if ($this->article)
            $this->guideIntro = '';                         // existing editorial copy takes precedence
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        $keywords = [Util::ucFirst(Lang::game('guides'))];
        if (isset($this->category[0]))
            $keywords[] = Lang::guide('category', $this->category[0]);

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => $keywords]);

        $this->buildBasicMetadata($this->guideIntro ?: Lang::meta('description', 'genList', [$this->h1]));
    }
}

?>
