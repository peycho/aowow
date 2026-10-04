<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


/*
 * accessed via account settings form submit
 * write status to session and redirect to account settings
 */

class AccountUpdategeneralsettingsResponse extends TextResponse
{
    protected ?string $redirectTo    = '?account#general';
    protected  bool   $requiresLogin = true;

    protected  array  $expectedPOST  = array(
        'idsInLists'  => ['filter' => FILTER_CALLBACK,     'options' => [self::class, 'checkCheckbox']                       ]
    );

    private bool $success = false;

    protected function generate() : void
    {
        if (User::isBanned())
            return;

        if ($message = $this->updateGeneral())
            $_SESSION['msg'] = ['general', $this->success, $message];
    }

    private function updateGeneral() : string
    {
        // Viewer fields from old forms are ignored; only the remaining list preference is saved.
        // int > number of edited rows > no changes is still success
        if (!is_int(DB::Aowow()->qry('UPDATE ::account SET `debug` = %i WHERE `id` = %i', $this->_post['idsInLists'] ? 1 : 0, User::$id)))
            return Lang::main('intError');

        $this->success = true;
        return Lang::account('updateMessage', 'general');
    }
}

?>
