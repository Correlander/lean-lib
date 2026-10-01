(function () {
    'use strict';

    const preferences = window.leanLibraryPreferences || {};
    const appUrl = String(preferences.appUrl || '').replace(/\/$/, '');

    function simplifyInviteFlow() {
        if (!preferences.fastOnboarding) return;
        const invitePath = window.location.pathname.match(/\/auth\/userinvite\/[^/]+/i);
        if (!invitePath) return;
        const step = Number(new URLSearchParams(window.location.search).get('step') || 1);
        const form = document.querySelector('#resetPassword');
        if (!form) return;

        const progress = document.querySelector('[data-onboarding-progress], .onboardingProgress, .projectSteps');
        if (progress) progress.hidden = true;

        if (step <= 1) {
            const intro = document.createElement('p');
            intro.className = 'leantimelib-compact-onboarding-note';
            intro.append(document.createTextNode('We’ll use your current defaults for appearance and schedule. Later, change your font and colors in '));
            const appearanceLink = document.createElement('a');
            appearanceLink.href = appUrl + '/users/editOwn#theme';
            appearanceLink.textContent = 'Profile settings';
            intro.append(appearanceLink, document.createTextNode(' or adjust work hours in the '));
            const scheduleLink = document.createElement('a');
            scheduleLink.href = appUrl + '/users/editOwn#workSchedule';
            scheduleLink.textContent = 'Work schedule tab';
            intro.append(scheduleLink, document.createTextNode('.'));
            const heading = document.querySelector('.regcontent');
            if (heading && !heading.querySelector('.leantimelib-compact-onboarding-note')) heading.insertBefore(intro, heading.firstChild);
            return;
        }

        const content = document.querySelector('.regcontent');
        form.hidden = true;
        if (content) {
            const status = document.createElement('p');
            status.setAttribute('role', 'status');
            status.textContent = 'Finishing your account setup…';
            content.append(status);
        }
        if (step === 4) {
            ['workStart', 'lunch', 'workEnd'].forEach(function (name, index) {
                const field = form.querySelector('[name="daySchedule-' + name + '"]');
                const selected = form.querySelector('[name="daySchedule-' + name + '-button"]:checked');
                const defaults = ['8', '12', '16'];
                if (field) field.value = selected && selected.value ? selected.value : defaults[index];
            });
        }
        window.setTimeout(function () {
            if (form.requestSubmit) form.requestSubmit();
            else form.submit();
        }, 80);
    }

    function start() {
        simplifyInviteFlow();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
