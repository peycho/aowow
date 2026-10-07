document.addEventListener('DOMContentLoaded', function () {
    var checks=0, widgets=[];
    function check(ok,label){checks++;if(!ok)throw new Error(label);}
    function submits(form){return form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}
    try {
        check(loadedScripts.length===1,'One script for six forms');
        check(loadedScripts[0].src==='https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=aowowTurnstileLoaded','Fixed explicit-render origin');
        for(const action of g_turnstile.actions)check(!submits(document.getElementById(action)),'Pending verification blocks native '+action);
        loadedScripts[0].onerror();
        for(const action of g_turnstile.actions)check(!submits(document.getElementById(action)),'Script failure never bypasses verification');
        check(loadedScripts.length===2,'A subsequent submission retries the failed API script once');
        window.turnstile={render:function(box,opt){widgets.push({box:box,opt:opt,reset:0,removed:false});return widgets.length-1;},
            reset:function(id){widgets[id].reset++;},remove:function(id){widgets[id].removed=true;}};
        aowowTurnstileLoaded();
        check(widgets.length===6,'Each native form receives its own widget');
        for(const [i,action] of g_turnstile.actions.entries()) {
            const form=document.getElementById(action),widget=widgets[i];
            check(widget.opt.action===action && widget.opt.sitekey==='SITE_FIXTURE','Action binding');
            check(form.querySelectorAll('[name="cf-turnstile-response"]').length===1,'One hidden token per form');
            widget.opt.callback('verified-'+action);
            check(submits(form),'Verified native submission proceeds');
            check(form.elements.namedItem('cf-turnstile-response').value==='verified-'+action,'Token attached');
            widget.opt['expired-callback']();check(!submits(form),'Expired token blocks');
            widget.opt.callback('retry');widget.opt['error-callback']();check(!submits(form),'Error clears stale token');
            widget.opt.callback('retry');AowowTurnstile.reset(form);
            check(widget.reset===1 && !submits(form),'Retry resets consumed token');
            widget.opt.callback('retry');widget.opt['timeout-callback']();check(!submits(form),'Timeout clears token');
        }
        const feedback=document.createElement('form');document.body.appendChild(feedback);
        ContactTool.onShow.call({data:{mode:0}},feedback);
        check(widgets.length===7,'General feedback dialog gets a widget');
        const data=function(mode){return {mode:mode,reason:1,description:'Fixture',currenturl:location.href,comment:{id:1}};};
        ContactTool.onSubmit(data(0),null,feedback);check(requests.length===0,'No AJAX before verification');
        widgets[6].opt.callback('feedback-token');ContactTool.onSubmit(data(0),null,feedback);
        check(requests.length===1 && requests[0].opt.params.includes('cf-turnstile-response=feedback-token'),'AJAX token submitted');
        requests[0].opt.onComplete({},{});
        check(widgets[6].reset===1 && !feedback.elements.namedItem('cf-turnstile-response').value,'AJAX completion resets token');
        ContactTool.onHide(feedback);
        check(widgets[6].removed && feedback.querySelectorAll('[name="cf-turnstile-response"]').length===0,'Dialog close cleans widget');
        ContactTool.onShow.call({data:{mode:0}},feedback);check(widgets.length===8,'Reopened dialog gets fresh widget');
        ContactTool.onHide(feedback);
        const reports=document.createElement('form');document.body.appendChild(reports);
        ContactTool.onShow.call({data:{mode:1}},reports);check(widgets.length===8,'Comment reporting has no captcha');
        ContactTool.onSubmit(data(1),null,reports);
        check(requests.length===2 && !requests[1].opt.params.includes('cf-turnstile-response'),'Comment reports submit normally');
        g_turnstile.actions=[];
        const plain=document.createElement('form');document.body.appendChild(plain);
        AowowTurnstile.mount(plain,'login');check(widgets.length===8 && loadedScripts.length===2,'Disabled forms make no widget/network request');
        g_turnstile.actions=['login'];g_turnstile.siteKey='';
        AowowTurnstile.mount(plain,'login');
        check(!AowowTurnstile.token(plain,'login') && loadedScripts.length===2,'Missing keys do not bypass protection or load API');
        check(plain.querySelector('[role="status"]').textContent===g_turnstile.error,'Accessible verification error');
        document.getElementById('result').textContent='PASS: '+checks+' Turnstile native/AJAX/expiry/retry DOM checks';
    } catch(error){document.getElementById('result').textContent='FAIL: '+error.message;}
});
