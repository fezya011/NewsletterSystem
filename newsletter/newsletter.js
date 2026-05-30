(function() {
    'use strict';

    let elements = {};
    let isProcessing = false;
    let currentPushEndpoint = null;
    let currentUserEmail = null;

    document.addEventListener('DOMContentLoaded', function() {
        elements = {
            overlay: document.getElementById('nl-modal-overlay'),
            options: document.querySelectorAll('.nl-option-btn'),
            emailForm: document.getElementById('nl-email-form'),
            emailInput: document.getElementById('nl-email'),
            subscribeBtn: document.getElementById('nl-subscribe-email'),
            errorMsg: document.getElementById('nl-error-msg'),
            loading: document.getElementById('nl-loading'),
            success: document.getElementById('nl-success'),
            optionsDiv: document.getElementById('nl-options')
        };

        if (!elements.overlay) return;

        // recovering email from session storage
        const savedEmail = sessionStorage.getItem('nl_user_email');
        if (savedEmail) {
            currentUserEmail = savedEmail;
            console.log('Restored email from storage:', currentUserEmail);
        }

        bindEvents();

        // register SW and get an existing subscription
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').then(async (reg) => {
                console.log('SW registered');
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    currentPushEndpoint = sub.endpoint;
                    console.log('Existing push endpoint found');
                }
            }).catch(err => {
                console.error('SW failed:', err);
            });
        }

        console.log('Newsletter: Ready');
    });

    function bindEvents() {
        elements.options.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (isProcessing) return;

                const type = this.dataset.type;
                if (type === 'no') closeModal();
                else if (type === 'email') showEmailForm();
                else if (type === 'push') handlePush();
            });
        });

        elements.overlay.addEventListener('click', function(e) {
            if (e.target === elements.overlay) closeModal();
        });

        if (elements.subscribeBtn) {
            elements.subscribeBtn.addEventListener('click', handleEmail);
        }
    }

    function showEmailForm() {
        elements.optionsDiv.style.display = 'none';
        elements.emailForm.style.display = 'block';
    }

    function getPageContext() {
        const context = {
            url: window.location.href,
            title: document.title,
            categories: [],
            keywords: []
        };

        // 1. extract the category from the URL
        const urlPath = window.location.pathname;
        const categoryMatch = urlPath.match(/\/(de|en|fr|it|ru)\/blog\/([^\/]+)/);
        if (categoryMatch && categoryMatch[2]) {
            context.categories.push(categoryMatch[2]);
        }

        // 2. extract the category from the meta tag
        const metaCategory = document.querySelector('meta[name="category"]');
        if (metaCategory && metaCategory.content) {
            context.categories.push(metaCategory.content);
        }

        // 3. extract keywords from the meta tag
        const metaKeywords = document.querySelector('meta[name="keywords"]');
        if (metaKeywords && metaKeywords.content) {
            context.keywords = metaKeywords.content.split(',').map(k => k.trim());
        }

        // 4. removing duplicates
        context.categories = [...new Set(context.categories)];

        return context;
    }

    async function handleEmail() {
        if (isProcessing) return;

        const email = elements.emailInput.value.trim();
        const context = getPageContext();
        console.log('HANDLE EMAIL');
        console.log('Email:', email);

        if (!email || !email.includes('@')) {
            showError('Please enter a valid email address.');
            return;
        }

        showLoading();
        isProcessing = true;

        try {
            const requestBody = {
                type: 'email',
                email: email,
                website: window.nlConfig.website,
                language: window.nlConfig.currentLang,
                context: context
            };

            if (currentPushEndpoint) {
                requestBody.push_endpoint = currentPushEndpoint;
                console.log('Linking with existing push endpoint');
            }

            const res = await fetch(window.nlConfig.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            });

            const data = await res.json();
            console.log('Server response:', data);

            if (data.success) {
                currentUserEmail = email;
                // save email in storage
                sessionStorage.setItem('nl_user_email', email);
                console.log('Saved email to storage:', email);
                showSuccess();
                setTimeout(() => closeModal(), 2000);
            } else {
                showError(data.message || 'Login failed');
            }
        } catch (err) {
            console.error(err);
            showError('Network error');
        } finally {
            isProcessing = false;
        }
    }

    async function handlePush() {
        if (isProcessing) return;
        isProcessing = true;

        console.log('HANDLE PUSH');
        console.log('currentUserEmail:', currentUserEmail);

        if (!('Notification' in window)) {
            showError('Notifications not supported');
            isProcessing = false;
            return;
        }

        if (Notification.permission === 'denied') {
            showError('Notifications were blocked');
            isProcessing = false;
            return;
        }

        showLoading();

        try {
            // request permission
            if (Notification.permission !== 'granted') {
                console.log('Requesting notification permission...');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    showError('Notifications not allowed');
                    isProcessing = false;
                    return;
                }
                console.log('Permission granted');
            }

            // getting a service worker with a timeout
            console.log('Getting Service Worker...');
            let registration;
            try {
                registration = await Promise.race([
                    navigator.serviceWorker.ready,
                    new Promise((_, reject) => setTimeout(() => reject(new Error('SW timeout')), 5000))
                ]);
            } catch (e) {
                console.log('SW timeout, re-registering...');
                await navigator.serviceWorker.register('/sw.js');
                registration = await navigator.serviceWorker.ready;
            }
            console.log('Service Worker ready:', registration.active ? 'active' : 'not active');

            // checking an existing subscription
            let existingSub = null;
            try {
                existingSub = await registration.pushManager.getSubscription();
            } catch (e) {
                console.warn('Could not get subscription:', e);
            }

            if (existingSub) {
                console.log('Already subscribed');
                currentPushEndpoint = existingSub.endpoint;
                showSuccess('You are already subscribed');
                setTimeout(() => closeModal(), 1500);
                isProcessing = false;
                return;
            }

            // VAPID key
            const vapidKey = window.nlConfig.vapidPublicKey;
            if (!vapidKey) {
                throw new Error('VAPID key missing');
            }
            console.log('VAPID Key length:', vapidKey.length);

            // create a subscription with a timeout
            console.log('Creating push subscription...');
            const subscribePromise = registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey)
            });

            const subscribeTimeout = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('Subscribe timeout (10s)')), 10000);
            });

            const subscription = await Promise.race([subscribePromise, subscribeTimeout]);
            console.log('Push subscription created:', subscription.endpoint.substring(0, 50));

            const context = getPageContext();

            currentPushEndpoint = subscription.endpoint;

            // email for linking
            const email = currentUserEmail || (elements.emailInput ? elements.emailInput.value.trim() : null);
            console.log('Email to send:', email);

            const requestBody = {
                type: 'push',
                subscription: {
                    endpoint: subscription.endpoint,
                    keys: {
                        auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))),
                        p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh'))))
                    }
                },
                website: window.nlConfig.website,
                language: window.nlConfig.currentLang,
                context: context
            };

            if (email && email.includes('@')) {
                requestBody.email = email;
                console.log('Linking push with email:', email);
            }

            console.log('Sending to server...');
            const response = await fetch(window.nlConfig.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestBody)
            });

            const data = await response.json();
            console.log('Server response:', data);

            if (data.success) {
                showSuccess();
                setTimeout(() => closeModal(), 2000);
            } else {
                throw new Error(data.message || 'Server error');
            }

        } catch (err) {
            console.error('Push Error:', err);
            showError(err.message || 'Push notifications could not be activated.');
        } finally {
            isProcessing = false;
        }
    }

    function showLoading() {
        if (elements.emailForm) elements.emailForm.style.display = 'none';
        if (elements.optionsDiv) elements.optionsDiv.style.display = 'none';
        if (elements.loading) elements.loading.style.display = 'block';
        if (elements.errorMsg) elements.errorMsg.style.display = 'none';
        if (elements.success) elements.success.style.display = 'none';
    }

    function showSuccess(msg) {
        if (elements.loading) elements.loading.style.display = 'none';
        if (elements.success) {
            elements.success.style.display = 'block';
            if (msg) {
                const p = elements.success.querySelector('p');
                if (p) p.innerHTML = '✓ ' + msg;
            }
        }
        setTimeout(() => {
            if (elements.success) elements.success.style.display = 'none';
            if (elements.optionsDiv) elements.optionsDiv.style.display = 'block';
        }, 2000);
    }

    function showError(msg) {
        if (elements.loading) elements.loading.style.display = 'none';
        if (elements.optionsDiv) elements.optionsDiv.style.display = 'block';
        if (elements.errorMsg) {
            elements.errorMsg.textContent = msg;
            elements.errorMsg.style.display = 'block';
        }
        setTimeout(() => {
            if (elements.errorMsg) elements.errorMsg.style.display = 'none';
        }, 4000);
    }

    function closeModal() {
        if (elements.overlay) elements.overlay.style.display = 'none';
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
})();