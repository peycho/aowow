<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class ContactusBaseResponse extends TextResponse
{
    protected array $expectedPOST = array(
        'mode'       => ['filter' => FILTER_VALIDATE_INT                                                  ],
        'reason'     => ['filter' => FILTER_VALIDATE_INT                                                  ],
        'ua'         => ['filter' => FILTER_CALLBACK,        'options' => [self::class, 'checkTextLine']  ],
        'appname'    => ['filter' => FILTER_CALLBACK,        'options' => [self::class, 'checkTextLine']  ],
        'page'       => ['filter' => FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => '/^[[:print:]]+$/']],
        'desc'       => ['filter' => FILTER_CALLBACK,        'options' => [self::class, 'checkTextBlob']  ],
        'id'         => ['filter' => FILTER_VALIDATE_INT                                                  ],
        'relatedurl' => ['filter' => FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => '/^[[:print:]]+$/']],
        'email'      => ['filter' => FILTER_SANITIZE_EMAIL                                                ]
    );

    /* responses
        0: success
        1: captcha invalid
        2: description too long
        3: reason missing
        7: already reported
        $: prints response
    */
    protected function generate() : void
    {
        if (!Cfg::get('FEEDBACK_ENABLE') && (int)($this->_post['mode'] ?? 0) === Report::MODE_GENERAL)
            $this->generate404();

        if (!$this->assertPOST('mode', 'reason') || !is_int($this->_post['mode']) || !is_int($this->_post['reason']))
        {
            $this->result = 4;
            return;
        }

        if ($this->_post['mode'] !== Report::MODE_GENERAL && !Report::canCreateContent())
        {
            $this->result = Lang::main('intError');
            return;
        }

        if (!is_string($this->_post['desc'] ?? null))
        {
            $this->result = 3;
            return;
        }
        foreach (['ua', 'appname', 'page', 'relatedurl', 'email'] as $key)
            if (isset($this->_post[$key]) && !is_string($this->_post[$key]))
            {
                $this->result = 4;
                return;
            }
        $subject = $this->_post['id'] ?? null;
        if (($subject !== null && !is_int($subject)) || ($this->_post['mode'] !== Report::MODE_GENERAL && $subject === null))
        {
            $this->result = 4;
            return;
        }

        $report = new Report($this->_post['mode'], $this->_post['reason'], $subject);
        if ($report->create($this->_post['desc'], $this->_post['ua'] ?? null, $this->_post['appname'] ?? null,
            $this->_post['page'] ?? null, $this->_post['relatedurl'] ?? null, $this->_post['email'] ?? null, $_POST['cf-turnstile-response'] ?? null))
            $this->result = 0;
        else if (($e = $report->getError()) > 0)
            $this->result = match ($e) {
                Report::ERR_LIMIT => Lang::main('contributionLimit'),
                Report::ERR_INVALID_CAPTCHA => Lang::main('captchaError'),
                default => $e
            };
        else
            $this->result = Lang::main('intError');
    }
}

?>
