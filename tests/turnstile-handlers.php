<?php

namespace Aowow {
    // Only replace the page-generation parent; real POST validation and endpoint methods execute.
    class TemplateResponse extends BaseResponse {
        protected function generate() : void {}
        protected function display() : void {}
        protected function onUserGroupMismatch() : never { throw new \RuntimeException('Unexpected role check'); }
    }
}
namespace {
    foreach (['signup','signin','forgot-password','forgot-username','resend'] as $route)
        require __DIR__.'/../endpoints/account/'.$route.'.php';
    require __DIR__.'/../endpoints/contactus/contactus.php';
    $cases = [
        ['registration', Aowow\AccountSignupResponse::class, 'doSignUp', 'DB_REACHED'],
        ['login', Aowow\AccountSigninResponse::class, 'doSignIn', 'AUTH_REACHED'],
        ['password_recovery', Aowow\AccountforgotpasswordResponse::class, 'processMailForm', 'DB_REACHED'],
        ['username_recovery', Aowow\AccountforgotusernameResponse::class, 'processMailForm', 'DB_REACHED'],
        ['resend', Aowow\AccountResendResponse::class, 'resend', 'DB_REACHED'],
        ['feedback', Aowow\ContactusBaseResponse::class, 'generate', 'REPORT_REACHED']
    ];
    $payload = ['username'=>'Fixtureuser','password'=>'fixture-password-long','c_password'=>'fixture-password-long',
        'email'=>'fixture@example.test','mode'=>0,'reason'=>1,'id'=>0];
    foreach ($cases as [$action,$class,$method,$sentinel]) {
        $_POST = [];
        $empty = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($empty, '_post'))->setValue($empty, array_fill_keys(array_keys($payload), null));
        $calls = Aowow\Fixture::$calls; $error = '';
        $result = (new ReflectionMethod($empty, $method))->invokeArgs($empty, $action === 'login' ? [&$error] : []);
        check(Aowow\Fixture::$calls === $calls && ($action === 'feedback' ?
            (new ReflectionProperty($empty, 'result'))->getValue($empty) === 4 : $result === ($action === 'login' ? false : '')),
            'Initial '.$action.' form view preserves behavior without Siteverify');
        foreach (['missing','expired','valid','disabled'] as $case) {
            Aowow\Cfg::$flags['TURNSTILE_'.strtoupper($action).'_ENABLE'] = $case === 'disabled' ? 0 : 1;
            $_POST = $case === 'missing' ? [] : ['cf-turnstile-response'=>$case === 'valid' ? 'v-'.$action : 'expired'];
            $response = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            (new ReflectionProperty($response, '_post'))->setValue($response, $payload);
            $error = ''; $caught = ''; $result = null; $calls = Aowow\Fixture::$calls;
            try { $result = (new ReflectionMethod($response, $method))->invokeArgs($response, $action === 'login' ? [&$error] : []); }
            catch (RuntimeException $e) { $caught = $e->getMessage(); }
            if (in_array($case, ['valid','disabled'], true)) check($caught === $sentinel, 'Verified/disabled '.$action.' retains its original work path');
            else {
                if ($action === 'feedback') $result = (new ReflectionProperty($response, 'result'))->getValue($response);
                check(!$caught && ($action === 'login' ? $result === false && $error === 'captchaError' : $result === 'captchaError'), 'Missing/expired token blocks '.$action.' before authentication/database/mail/report work');
            }
            if (in_array($case, ['disabled','missing'], true)) check(Aowow\Fixture::$calls === $calls, 'Disabled/missing-token '.$action.' makes no network call');
        }
        Aowow\Cfg::$flags['TURNSTILE_'.strtoupper($action).'_ENABLE'] = 1;
    }
    foreach ([1,2,3,4,5,6] as $mode) {
        $_POST = []; $payload['mode'] = $mode;
        $response = (new ReflectionClass(Aowow\ContactusBaseResponse::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($response, '_post'))->setValue($response, $payload);
        $calls = Aowow\Fixture::$calls; $caught = '';
        try { (new ReflectionMethod($response, 'generate'))->invoke($response); } catch (RuntimeException $e) { $caught = $e->getMessage(); }
        check($caught === 'REPORT_REACHED' && Aowow\Fixture::$calls === $calls, 'Content reports bypass general feedback captcha');
    }
}
