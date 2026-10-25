<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class GuidePowerResponse extends TextResponse implements ICache
{
    use TrCache, TrTooltip { TrCache::loadCache as private loadTooltipCache; }

    private const string POWER_TEMPLATE = '$WowheadPower.registerGuide(%s, %d, %s);';

    protected int   $type        = Type::GUIDE;
    protected int   $typeId      = 0;
    protected int   $cacheType   = CACHE_TYPE_NONE;          // recheck publication state on every request

    protected array $expectedGET = array(
        'domain' => ['filter' => FILTER_CALLBACK, 'options' => [Locale::class, 'tryFromDomain']]
    );

    private string $url = '';
    private ?GuideList $guide = null;
    private string $publicationCacheKey = '';

    public function getCacheKeyComponents() : array
    {
        return [$this->type, $this->typeId, User::$groups, $this->publicationCacheKey];
    }

    public function loadCache(mixed &$fromCache) : bool
    {
        $this->guide = new GuideList([['id', $this->typeId]]);
        require_once __DIR__.'/../../includes/components/cachestore.class.php';
        if (!$this->guide->error && $this->guide->canBeViewed() && CacheStore::enabled() &&
            ($epoch = CacheStore::version('guides')))
        {
            $fields = [];
            foreach (['status', 'rev', 'name', 'title', 'description', 'locale', 'cuFlags'] as $field)
                $fields[$field] = $this->guide->getField($field);
            $this->publicationCacheKey = hash('sha256', serialize([$epoch, $this->url, $fields]));
            $this->cacheType = CACHE_TYPE_TOOLTIP;
        }
        return $this->loadTooltipCache($fromCache);
    }

    public function __construct(string $idOrName)
    {
        parent::__construct($idOrName);

        // temp locale
        if ($this->_get['domain'])
            Lang::load($this->_get['domain']);

        if (Util::checkNumeric($idOrName, NUM_CAST_INT))
            $this->typeId = $idOrName;
        else if ($id = DB::Aowow()->selectCell('SELECT `id` FROM ::guides WHERE `url` = %s', Util::lower($idOrName)))
        {
            $this->typeId = intVal($id);
            $this->url    = Util::lower($idOrName);
        }
    }

    protected function generate() : void
    {
        $opts = [];
        if ($this->typeId)
            if (!($guide = $this->guide ?? new GuideList(array(['id', $this->typeId])))->error && $guide->canBeViewed())
            {
                $approved = $guide->getField('status') === GuideMgr::STATUS_APPROVED;
                $name = $approved ? $guide->getField('name', true) : Lang::game('guide').' #'.$this->typeId;
                $opts = ['name' => $name, 'tooltip' => $approved ? $guide->renderTooltip() : Util::htmlEscape($name)];
            }

        if (!$opts)
            $this->cacheType = CACHE_TYPE_NONE;

        $this->result = new Tooltip(self::POWER_TEMPLATE, $this->url ?: $this->typeId, $opts);
    }
}

?>
