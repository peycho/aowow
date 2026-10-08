<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


// expects non-200 header on error
class CommentFlagreplyResponse extends TextResponse
{
    protected bool  $requiresLogin = true;

    protected array $expectedPOST  = array(
        'id' => ['filter' => FILTER_VALIDATE_INT]
    );

    protected function generate() : void
    {
        if (!$this->assertPOST('id') || !Report::canCreateContent())
            $this->generate404(Lang::main('intError'));

        $replyOwner = DB::Aowow()->selectCell('SELECT `userId` FROM ::comments WHERE `id` = %i', $this->_post['id']);
        if (!$replyOwner)
            $this->generate404(Lang::main('intError'));

        // ui element should not be present
        if ($replyOwner == User::$id)
            $this->generate404();

        $report = new Report(Report::MODE_COMMENT, Report::CO_INAPPROPRIATE, $this->_post['id']);
        if (!$report->create('Report Reply Button Click'))
            $this->generate404($report->getError() === Report::ERR_LIMIT ? Lang::main('contributionLimit') : Lang::main('intError'));
        else if (count($report->getSimilar()) >= CommunityContent::REPORT_THRESHOLD_AUTO_DELETE)
            DB::Aowow()->qry('UPDATE ::comments SET `flags` = `flags` | %i WHERE `id` = %i', CC_FLAG_DELETED, $this->_post['id']);
    }
}

?>
