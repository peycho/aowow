<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class Report
{
    public const int MODE_GENERAL         = 0;
    public const int MODE_COMMENT         = 1;
    public const int MODE_FORUM_POST      = 2;
    public const int MODE_SCREENSHOT      = 3;
    public const int MODE_CHARACTER       = 4;
    public const int MODE_VIDEO           = 5;
    public const int MODE_GUIDE           = 6;

    public const int GEN_FEEDBACK         = 1;
    public const int GEN_BUG_REPORT       = 2;
    public const int GEN_TYPO_TRANSLATION = 3;
    public const int GEN_OP_ADVERTISING   = 4;
    public const int GEN_OP_PARTNERSHIP   = 5;
    public const int GEN_PRESS_INQUIRY    = 6;
    public const int GEN_MISCELLANEOUS    = 7;
    public const int GEN_MISINFORMATION   = 8;
    public const int CO_ADVERTISING       = 15;
    public const int CO_INACCURATE        = 16;
    public const int CO_OUT_OF_DATE       = 17;
    public const int CO_SPAM              = 18;
    public const int CO_INAPPROPRIATE     = 19;
    public const int CO_MISCELLANEOUS     = 20;
    public const int FO_ADVERTISING       = 30;
    public const int FO_AVATAR            = 31;
    public const int FO_INACCURATE        = 32;
    public const int FO_OUT_OF_DATE       = 33;
    public const int FO_SPAM              = 34;
    public const int FO_STICKY_REQUEST    = 35;
    public const int FO_INAPPROPRIATE     = 36;
    public const int FO_MISCELLANEOUS     = 37;
    public const int SS_INACCURATE        = 45;
    public const int SS_OUT_OF_DATE       = 46;
    public const int SS_INAPPROPRIATE     = 47;
    public const int SS_MISCELLANEOUS     = 48;
    public const int PR_INACCURATE_DATA   = 60;
    public const int PR_MISCELLANEOUS     = 61;
    public const int VI_INACCURATE        = 45;
    public const int VI_OUT_OF_DATE       = 46;
    public const int VI_INAPPROPRIATE     = 47;
    public const int VI_MISCELLANEOUS     = 48;
    public const int AR_INACCURATE        = 45;
    public const int AR_OUT_OF_DATE       = 46;
    public const int AR_MISCELLANEOUS     = 48;

    private array $context = array(
        self::MODE_GENERAL => array(
            self::GEN_FEEDBACK         => true,
            self::GEN_BUG_REPORT       => true,
            self::GEN_TYPO_TRANSLATION => true,
            self::GEN_OP_ADVERTISING   => true,
            self::GEN_OP_PARTNERSHIP   => true,
            self::GEN_PRESS_INQUIRY    => true,
            self::GEN_MISCELLANEOUS    => true,
            self::GEN_MISINFORMATION   => true
        ),
        self::MODE_COMMENT => array(
            self::CO_ADVERTISING   => U_GROUP_MODERATOR,
            self::CO_INACCURATE    => true,
            self::CO_OUT_OF_DATE   => true,
            self::CO_SPAM          => U_GROUP_MODERATOR,
            self::CO_INAPPROPRIATE => U_GROUP_MODERATOR,
            self::CO_MISCELLANEOUS => U_GROUP_MODERATOR
        ),
        self::MODE_FORUM_POST => array(
            self::FO_ADVERTISING    => U_GROUP_MODERATOR,
            self::FO_AVATAR         => true,
            self::FO_INACCURATE     => true,
            self::FO_OUT_OF_DATE    => U_GROUP_MODERATOR,
            self::FO_SPAM           => U_GROUP_MODERATOR,
            self::FO_STICKY_REQUEST => U_GROUP_MODERATOR,
            self::FO_INAPPROPRIATE  => U_GROUP_MODERATOR
        ),
        self::MODE_SCREENSHOT => array(
            self::SS_INACCURATE    => true,
            self::SS_OUT_OF_DATE   => true,
            self::SS_INAPPROPRIATE => U_GROUP_MODERATOR,
            self::SS_MISCELLANEOUS => U_GROUP_MODERATOR
        ),
        self::MODE_CHARACTER => array(
            self::PR_INACCURATE_DATA => true,
            self::PR_MISCELLANEOUS   => true
        ),
        self::MODE_VIDEO => array(
            self::VI_INACCURATE    => true,
            self::VI_OUT_OF_DATE   => true,
            self::VI_INAPPROPRIATE => U_GROUP_MODERATOR,
            self::VI_MISCELLANEOUS => U_GROUP_MODERATOR
        ),
        self::MODE_GUIDE => array(
            self::AR_INACCURATE    => true,
            self::AR_OUT_OF_DATE   => true,
            self::AR_MISCELLANEOUS => true
        )
    );

    private const int ERR_NONE             = 0;             // aka: success
    public const int ERR_INVALID_CAPTCHA   = 1;
    private const int ERR_DESC_TOO_LONG    = 2;
    private const int ERR_NO_DESC          = 3;
    private const int ERR_ALREADY_REPORTED = 7;
    public const int ERR_LIMIT             = 8;
    private const int ERR_MISCELLANEOUS    = -1;

    public const int STATUS_OPEN           = 0;
    public const int STATUS_ASSIGNED       = 1;
    public const int STATUS_CLOSED_WONTFIX = 2;
    public const int STATUS_CLOSED_SOLVED  = 3;

    private int $errorCode = self::ERR_NONE;

    public static function canCreateContent() : bool
    {
        return User::isLoggedIn() && !User::isBanned() && !(User::$groups & U_GROUP_PENDING);
    }


    public function __construct(private int $mode, private int $reason, private ?int $subject = 0)
    {
        if ($mode < 0 || $reason <= 0)
        {
            $this->errorCode = self::ERR_MISCELLANEOUS;
            return;
        }

        if (!isset($this->context[$mode][$reason]))
        {
            $this->errorCode = self::ERR_MISCELLANEOUS;
            return;
        }

        if (($mode !== self::MODE_GENERAL && !self::canCreateContent()) || (!User::isLoggedIn() && !User::$ip))
        {
            $this->errorCode = self::ERR_MISCELLANEOUS;
            return;
        }

        $this->subject ??= 0;                               // 0 for utility, tools and misc pages?
    }

    private function checkTargetContext(?string $url) : int
    {
        $where = array(
            ['`mode` = %i ', $this->mode],
            ['`reason`= %i ', $this->reason],
            ['`subject` = %i', $this->subject],
        );
        if (User::isLoggedIn())                             // check already reported
            $where[] = ['`userId` = %i', User::$id];
        else
            $where[] = ['`ip` = %s', User::$ip];
        // Content identity does not depend on the supplied page URL or reason.
        if ($this->mode !== self::MODE_GENERAL)
            unset($where[1]);
        else if ($url)
            $where[] = ['`url` = %s', $url];

        if (DB::Aowow()->query('SELECT 1 FROM ::reports WHERE %and', $where)->fetchSingle())
            return self::ERR_ALREADY_REPORTED;

        return self::ERR_NONE;                             // content target/author policy is checked under the transaction
    }

    public function create(string $desc, ?string $userAgent = null, ?string $appName = null, ?string $pageUrl = null, ?string $relUrl = null, ?string $email = null, mixed $captchaToken = null) : bool
    {
        $content = $this->mode !== self::MODE_GENERAL;
        if ($content && (!self::canCreateContent() || $this->mode === self::MODE_FORUM_POST ||
            !$this->subject || $this->subject < 0 || $this->subject > 8388607 ||
            ($this->mode === self::MODE_CHARACTER && !Cfg::get('PROFILER_ENABLE'))))
        {
            $this->errorCode = self::ERR_MISCELLANEOUS;
            return false;
        }
        if ($this->mode === self::MODE_GENERAL && !Cfg::get('FEEDBACK_ENABLE'))
            return false;

        if ($this->errorCode)
            return false;

        if (!$desc)
        {
            $this->errorCode = self::ERR_NO_DESC;
            return false;
        }

        if (strlen($desc) > 2000 || !mb_check_encoding($desc, 'UTF-8') || mb_strlen($desc) > 500)
        {
            $this->errorCode = self::ERR_DESC_TOO_LONG;
            return false;
        }

        $userAgent ??= mb_substr(User::$agent, 0, 255);
        $appName ??= '';                                   // optional metadata must not invoke server browscap lookup
        foreach ([[$userAgent, 255], [$appName, 32], [$pageUrl, 255], [$relUrl, 255], [$email, 255]] as [$value, $limit])
            if ($value !== null && (strlen($value) > $limit * 4 || !mb_check_encoding($value, 'UTF-8') ||
                mb_strlen($value) > $limit || preg_match('/[\x00-\x1f\x7f]/', $value)))
            {
                $this->errorCode = self::ERR_MISCELLANEOUS;
                return false;
            }

        // clean up src url: dont use anchors, clean up query
        if ($pageUrl)
        {
            $urlParts = parse_url($pageUrl);
            if (!empty($urlParts['query']))
            {
                parse_str($urlParts['query'], $query);      // kills redundant param declarations
                unset($query['locale']);                    // locale param shouldn't be needed. more..?
                $urlParts['query'] = http_build_query($query);
            }

            $pageUrl = '';
            if (isset($urlParts['scheme']))
                $pageUrl .= $urlParts['scheme'].':';

            $pageUrl .= '//'.($urlParts['host'] ?? '').($urlParts['path'] ?? '');

            if (isset($urlParts['query']))
                $pageUrl .= '?'.$urlParts['query'];
        }

        if ($pageUrl !== null && mb_strlen($pageUrl) > 255)
        {
            $this->errorCode = self::ERR_MISCELLANEOUS;
            return false;
        }

        if ($content && !ContributionBudget::reserve('report', strlen($desc.$userAgent.$appName.$pageUrl.$relUrl.$email)))
        {
            $this->errorCode = ContributionBudget::status() === ContributionBudget::BLOCKED ? self::ERR_LIMIT : self::ERR_MISCELLANEOUS;
            return false;
        }

        if (!$content)
        {
            if (!FeedbackBudget::reserve())
            {
                $this->errorCode = FeedbackBudget::blocked() ? self::ERR_LIMIT : self::ERR_MISCELLANEOUS;
                return false;
            }
            if (!FeedbackBudget::prepareStorage(strlen($desc.$userAgent.$appName.$pageUrl.$relUrl.$email.User::$ip)))
            {
                $this->errorCode = FeedbackBudget::blocked() ? self::ERR_LIMIT : self::ERR_MISCELLANEOUS;
                return false;
            }
            if (!Turnstile::verify('feedback', $captchaToken))
            {
                $this->errorCode = self::ERR_INVALID_CAPTCHA;
                return false;
            }
        }

        $started = false;
        $committed = false;
        try
        {
            $db = DB::Aowow();
            $started = true;
            $db->query('START TRANSACTION');
            if (!$content)
                FeedbackBudget::lockStorage($db);
            else
            {
                // Serialize this account's report checks/inserts across workers without altering historical rows.
                $account = $db->query('SELECT `userGroups`, `status` FROM ::account WHERE `id` = %i FOR UPDATE', User::$id)->fetch();
                if (!$account || ($account->userGroups & U_GROUP_PENDING) ||
                    in_array((int)$account->status, [ACC_STATUS_NEW, ACC_STATUS_DELETED], true) || !$this->validTarget($db, (int)$account->userGroups))
                {
                    $this->errorCode = self::ERR_MISCELLANEOUS;
                    return false;
                }
            }

            if (!$content && !FeedbackBudget::charge($db, strlen($desc.$userAgent.$appName.$pageUrl.$relUrl.$email.User::$ip)))
            {
                $this->errorCode = self::ERR_LIMIT;
                return false;
            }

            if ($err = $this->checkTargetContext($pageUrl))
            {
                $this->errorCode = $err;
                return false;
            }

            $update = array(
                'userId'      => User::$id,
                'createDate'  => time(),
                'mode'        => $this->mode,
                'reason'      => $this->reason,
                'subject'     => $this->subject,
                'ip'          => User::$ip,
                'description' => $desc,
                'userAgent'   => $userAgent,
                'appName'     => $appName,
                'url'         => $pageUrl ?: ''
            );

            if ($relUrl)
                $update['relatedurl'] = $relUrl;

            if ($email)
                $update['email'] = $email;

            $db->query('INSERT INTO ::reports %v', $update);
            if ($db->getAffectedRows() !== 1) throw new \RuntimeException('Report insert failed.');
            $db->query('COMMIT');
            $committed = true;
            return true;
        }
        catch (\Throwable)
        {
            $this->errorCode = self::ERR_MISCELLANEOUS;
            return false;
        }
        finally
        {
            if ($started && !$committed)
                try { $db->query('ROLLBACK'); } catch (\Throwable) { }
        }
    }

    private function validTarget(DibiConnection $db, int $groups) : bool
    {
        $target = match ($this->mode) {
            self::MODE_COMMENT => $db->query('SELECT `roles`, `userId` AS `owner`, `flags` FROM ::comments WHERE `id` = %i FOR UPDATE', $this->subject)->fetch(),
            self::MODE_SCREENSHOT, self::MODE_VIDEO => $db->query('SELECT `userIdOwner` AS `owner`, `status` AS `flags` FROM %n WHERE `id` = %i FOR UPDATE',
                $this->mode === self::MODE_SCREENSHOT ? '::screenshots' : '::videos', $this->subject)->fetch(),
            self::MODE_GUIDE => $db->query('SELECT `userId` AS `owner`, `status`, `roles` FROM ::guides WHERE `id` = %i FOR UPDATE', $this->subject)->fetch(),
            self::MODE_CHARACTER => $db->query('SELECT `id` FROM ::profiler_profiles WHERE `id` = %i AND `realm` > 0 AND `custom` = 0 AND `deleted` = 0 FOR UPDATE', $this->subject)->fetch(),
            default => null
        };
        if (!$target) return false;
        if ($this->mode === self::MODE_CHARACTER) return true;
        $privileged = (int)$target->owner === User::$id || ($groups & U_GROUP_MODERATOR);
        if (!$privileged)
        {
            if ($this->mode === self::MODE_GUIDE && !in_array((int)$target->status, [GuideMgr::STATUS_APPROVED, GuideMgr::STATUS_ARCHIVED], true)) return false;
            if ($this->mode !== self::MODE_GUIDE && ($target->flags & CC_FLAG_DELETED)) return false;
            if (in_array($this->mode, [self::MODE_SCREENSHOT, self::MODE_VIDEO], true) && !($target->flags & CC_FLAG_APPROVED)) return false;
        }
        $mask = $this->context[$this->mode][$this->reason];
        if (is_int($mask))
        {
            $roles = $this->mode === self::MODE_COMMENT ? $target->roles :
                $db->query('SELECT `userGroups` FROM ::account WHERE `id` = %i', (int)$target->owner)->fetchSingle();
            // Match the dialog: these reasons cannot target moderator-authored content.
            if ((int)$roles & $mask) return false;
        }
        return true;
    }

    public function getSimilar(int ...$status) : array
    {
        if ($this->errorCode)
            return [];

        foreach ($status as &$s)
            if ($s < self::STATUS_OPEN || $s > self::STATUS_CLOSED_SOLVED)
                unset($s);

        return DB::Aowow()->selectAssoc('SELECT `id` AS ARRAY_KEY, r.* FROM ::reports r WHERE %if', $status, '`status` IN %in AND', $status, '%end `mode` = %i AND `reason` = %i AND `subject` = %i',
            $this->mode, $this->reason, $this->subject);
    }

    public function close(int $closeStatus, bool $inclAssigned = false) : bool
    {
        if ($closeStatus != self::STATUS_CLOSED_SOLVED && $closeStatus != self::STATUS_CLOSED_WONTFIX)
            return false;

        if (!User::isInGroup(U_GROUP_ADMIN | U_GROUP_BUREAU | U_GROUP_MOD))
            return false;

        $fromStatus = [self::STATUS_OPEN];
        if ($inclAssigned)
            $fromStatus[] = self::STATUS_ASSIGNED;

        if ($reports = DB::Aowow()->selectCol('SELECT `id` AS ARRAY_KEY, `userId` FROM ::reports WHERE `status` IN %in AND `mode` = %i AND `reason` = %i AND `subject` = %i',
            $fromStatus, $this->mode, $this->reason, $this->subject))
        {
            DB::Aowow()->qry('UPDATE ::reports SET `status` = %i, `assigned` = 0 WHERE `id` IN %in', $closeStatus, array_keys($reports));

            foreach ($reports as $rId => $uId)
                Util::gainSiteReputation($uId, $closeStatus == self::STATUS_CLOSED_SOLVED ? SITEREP_ACTION_GOOD_REPORT : SITEREP_ACTION_BAD_REPORT, ['id' => $rId]);

            return true;
        }

        return false;
    }

    public function reopen(int $assignedTo = 0) : bool
    {
        // assignedTo = 0 ? status = STATUS_OPEN : status = STATUS_ASSIGNED, userId = assignedTo
        return false;
    }

    public function getError() : int
    {
        return $this->errorCode;
    }
}

?>
