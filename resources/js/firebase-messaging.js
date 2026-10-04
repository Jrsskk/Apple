const configuration = window.EduSyncFirebaseConfiguration;
const enableButton = document.getElementById('enable-push-notifications');
const statusMessage = document.getElementById('push-notification-status');

if (configuration && window.firebase && enableButton && 'serviceWorker' in navigator) {
    const firebaseApp = window.firebase.initializeApp(configuration);
    const messaging = firebaseApp.messaging();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const updateStatus = (message, isError = false) => {
        if (!statusMessage) return;
        statusMessage.textContent = message;
        statusMessage.classList.toggle('text-red-600', isError);
        statusMessage.classList.toggle('text-emerald-700', !isError);
    };

    const registerToken = async (token) => {
        const response = await fetch('/student/device-tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                token,
                platform: 'web',
                device_name: navigator.userAgent.slice(0, 255),
            }),
        });

        if (!response.ok) {
            throw new Error('The device token could not be registered with EduSync.');
        }
    };

    const removeToken = async (token) => {
        const response = await fetch('/student/device-tokens', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ token }),
        });

        if (!response.ok) {
            throw new Error('The device token could not be removed from EduSync.');
        }
    };

    const serviceWorkerRegistration = () => navigator.serviceWorker.register('/firebase-messaging-sw.js');

    const getCurrentToken = async () => {
        const registration = await serviceWorkerRegistration();
        return window.firebase.messaging().getToken({
            vapidKey: configuration.vapidKey,
            serviceWorkerRegistration: registration,
        });
    };

    const enableNotifications = async () => {
        if (!('Notification' in window)) {
            throw new Error('This browser does not support push notifications.');
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error('Push notification permission was not granted.');
        }

        const token = await getCurrentToken();
        if (!token) {
            throw new Error('Firebase did not return a browser device token.');
        }

        await registerToken(token);
        localStorage.setItem('edusync-push-enabled', 'true');
        enableButton.textContent = 'Disable push notifications';
        updateStatus('Push notifications are enabled on this browser.');
    };

    enableButton.addEventListener('click', async () => {
        enableButton.disabled = true;
        try {
            if (localStorage.getItem('edusync-push-enabled') === 'true') {
                const token = await getCurrentToken();
                if (token) {
                    await removeToken(token);
                    await window.firebase.messaging().deleteToken();
                }
                localStorage.removeItem('edusync-push-enabled');
                enableButton.textContent = 'Enable push notifications';
                updateStatus('Push notifications are disabled on this browser.');
            } else {
                await enableNotifications();
            }
        } catch (error) {
            updateStatus(error instanceof Error ? error.message : 'Push notifications could not be updated.', true);
        } finally {
            enableButton.disabled = false;
        }
    });

    if (Notification.permission === 'granted') {
        getCurrentToken()
            .then((token) => token && registerToken(token))
            .then(() => {
                if (localStorage.getItem('edusync-push-enabled') === 'true') {
                    enableButton.textContent = 'Disable push notifications';
                }
                updateStatus('Push notifications are enabled on this browser.');
            })
            .catch((error) => updateStatus(error.message, true));
    }

    messaging.onMessage((payload) => {
        if (Notification.permission === 'granted' && payload.notification) {
            const notification = new Notification(payload.notification.title || 'EduSync', {
                body: payload.notification.body || '',
                icon: '/icons/icon-192.png',
            });
            notification.onclick = () => {
                window.location.assign(payload.data?.url || '/student/notifications');
            };
        }
        window.dispatchEvent(new CustomEvent('edusync:push-message', { detail: payload }));
    });
}
