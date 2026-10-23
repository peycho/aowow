<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');
if (!CLI)
    die('not in cli mode');

CLISetup::registerSetup('build', new class extends SetupScript
{
    protected array $info = [
        'guidereferences' => [[], CLISetup::ARGV_PARAM, 'Rebuilds published guide references for related-guide links.']
    ];

    public function generate() : bool
    {
        require_once __DIR__.'/../../../includes/components/guidediscovery.class.php';
        $after = 0;
        do
        {
            $ids = DB::Aowow()->selectCol('SELECT `id` FROM ::guides WHERE `id` > %i AND `status` != %i
                AND (`status` = %i OR `rev` > 0) ORDER BY `id` LIMIT 100', $after, GuideMgr::STATUS_ARCHIVED, GuideMgr::STATUS_APPROVED);
            if ($ids === null)
                return false;
            foreach ($ids as $id)
                if (!GuideDiscovery::indexGuide($id))
                    return false;
            if ($ids)
                $after = (int)end($ids);
        } while (count($ids) === 100);
        return true;
    }
});
