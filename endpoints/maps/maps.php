<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class MapsBaseResponse extends TemplateResponse
{
    protected  string $template   = 'maps';
    protected  string $pageName   = 'maps';
    protected ?int    $activeTab  = parent::TAB_TOOLS;
    protected  array  $breadcrumb = [1, 1];

    protected  array  $dataLoader = ['zones'];
    protected  array  $scripts    = [[SC_JS_FILE, 'js/maps.js'], [SC_CSS_STRING, 'zone-picker { margin-left: 4px }']];

    public array $instanceMaps = ['dungeons' => [], 'raids' => []];
    public array $mapLocales = [];

    protected function generate() : void
    {
        $this->h1 = Lang::maps('maps');

        array_unshift($this->title, $this->h1);

        $zones = DB::Aowow()->selectAssoc(
            'SELECT `id`, `category`, `name_loc0`, `name_loc2`, `name_loc3`, `name_loc4`, `name_loc6`, `name_loc8`
             FROM ::zones WHERE `category` IN %in AND `parentArea` = 0 AND (`cuFlags` & %i) = 0',
            [MAP_TYPE_DUNGEON, MAP_TYPE_RAID], CUSTOM_EXCLUDE_FOR_LISTVIEW
        );
        foreach ($zones ?: [] as $zone)
        {
            $id = (int)$zone['id'];
            if (!($imageLocale = MapImages::findLocale($id, Lang::getLocale())))
                continue;

            $group = $zone['category'] == MAP_TYPE_RAID ? 'raids' : 'dungeons';
            $this->instanceMaps[$group][$id] = Util::localizedString($zone, 'name', true);
            $this->mapLocales[$id] = $imageLocale->json();
        }

        parent::generate();
    }

    protected function generateMetadata(bool $useArticle = true) : void
    {
        $this->metaTags[] = ['property' => 'og:title', 'content' => $this->h1];
        $this->metaTags[] = ['property' => 'og:type',  'content' => 'website'];

        array_unshift($this->metaTags, ['name' => 'keywords', 'content' => [...Lang::meta('tags', 'maps'), ...Lang::meta('tags', 'generic')]]);

        $this->buildBasicMetadata();
    }
}

?>
