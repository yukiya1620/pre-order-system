(function () {
    var container = document.querySelector('.auth-form-page');
    if (!container) {
        return;
    }

    // 誤連打防止のためのクールダウン秒数(本格的な送信レート制限ではない。
    // サーバー側スロットリングは今回のスコープ外で、将来のセキュリティ確認課題として残す)
    var RESEND_COOLDOWN_SECONDS = 60;
    var PHONE_NUMBER_PATTERN = /^0\d{9,10}$/;

    var mode = container.dataset.mode;
    var smsSendUrl = container.dataset.smsSendUrl;
    var smsVerifyUrl = container.dataset.smsVerifyUrl;
    var emailLoginUrl = container.dataset.emailLoginUrl;
    var buyerHomeUrl = container.dataset.buyerHomeUrl;
    var farmerHomeUrl = container.dataset.farmerHomeUrl;
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var generalMessageEl = document.getElementById('auth-form-message');
    var wizardEl = document.getElementById('auth-step-wizard');
    var codeSentNoteEl = document.getElementById('auth-code-sent-note');
    var resendButton = document.getElementById('auth-resend-button');
    var resendCountdownEl = document.getElementById('auth-resend-countdown');
    var verifyButton = document.getElementById('auth-verify-button');

    var switchToEmailEl = document.getElementById('auth-switch-to-email');
    var switchToEmailButton = document.getElementById('auth-switch-to-email-button');
    var emailLoginForm = document.getElementById('email-login-form');
    var switchToPhoneEl = document.getElementById('auth-switch-to-phone');
    var switchToPhoneButton = document.getElementById('auth-switch-to-phone-button');
    var emailLoginSubmitButton = document.getElementById('email-login-submit-button');

    // 会員登録(B1)は名前→電話番号→住所→メールの順で集めてから認証コードを送る。
    // ログイン(B2)は電話番号だけ。REGISTRATION_INFO_REQUIREDを受けた場合に
    // name/address/emailのステップを'code'の直前へ動的に差し込む。
    var STEP_SEQUENCES = {
        register: ['name', 'phone', 'address', 'email', 'code'],
        login: ['phone', 'code']
    };

    var steps = STEP_SEQUENCES[mode].slice();
    var currentIndex = 0;
    var collected = { name: '', phone_number: '', address: '', email: '' };
    var pendingRegistrationEscalation = false;
    var cooldownInterval = null;

    function showGeneralMessage(text) {
        generalMessageEl.textContent = text;
        generalMessageEl.hidden = false;
    }

    function clearGeneralMessage() {
        generalMessageEl.hidden = true;
    }

    function clearFieldError(fieldId) {
        var el = document.getElementById(fieldId + '-error');
        if (el) {
            el.textContent = '';
            el.hidden = true;
        }
    }

    function showFieldError(fieldId, text) {
        var el = document.getElementById(fieldId + '-error');
        if (el) {
            el.textContent = text;
            el.hidden = false;
        }
    }

    function showFieldErrors(errors) {
        Object.keys(errors).forEach(function (field) {
            // APIのフィールド名(phone_number等)と画面のid(auth-phone等)の対応表
            var idMap = { phone_number: 'auth-phone', code: 'auth-code', name: 'auth-name', address: 'auth-address', email: 'auth-email' };
            var fieldId = idMap[field];
            if (fieldId) {
                showFieldError(fieldId, errors[field][0]);
            }
        });
    }

    function nextButtonLabelFor(stepName) {
        var idx = steps.indexOf(stepName);
        var nextStepName = steps[idx + 1];

        if (nextStepName === 'code') {
            return pendingRegistrationEscalation ? '次へ(認証コード入力)' : '認証コードを送信する';
        }

        return '次へ';
    }

    function renderStep() {
        var stepName = steps[currentIndex];

        document.querySelectorAll('.auth-step').forEach(function (el) {
            el.hidden = el.dataset.stepName !== stepName;
        });

        var stepEl = document.querySelector('.auth-step[data-step-name="' + stepName + '"]');
        if (!stepEl) {
            return;
        }

        var nextButton = stepEl.querySelector('[data-next]');
        if (nextButton) {
            nextButton.textContent = nextButtonLabelFor(stepName);
        }

        var backButton = stepEl.querySelector('[data-back]');
        if (backButton) {
            backButton.hidden = currentIndex === 0;
        }
    }

    function validateStep(stepName) {
        if (stepName === 'name') {
            var name = document.getElementById('auth-name').value.trim();
            clearFieldError('auth-name');
            if (!name) {
                showFieldError('auth-name', 'お名前を入力してください。');
                return false;
            }
            collected.name = name;
            return true;
        }

        if (stepName === 'phone') {
            var phone = document.getElementById('auth-phone').value.trim();
            clearFieldError('auth-phone');
            if (!PHONE_NUMBER_PATTERN.test(phone)) {
                showFieldError('auth-phone', '電話番号は「0」から始まる10〜11桁の数字で入力してください(ハイフンなし)。');
                return false;
            }
            collected.phone_number = phone;
            return true;
        }

        if (stepName === 'address') {
            var address = document.getElementById('auth-address').value.trim();
            clearFieldError('auth-address');
            if (!address) {
                showFieldError('auth-address', 'ご住所を入力してください。');
                return false;
            }
            collected.address = address;
            return true;
        }

        if (stepName === 'email') {
            // メールアドレスは任意項目なので、空欄ならそのまま次へ進める
            collected.email = document.getElementById('auth-email').value.trim();
            clearFieldError('auth-email');
            return true;
        }

        return true;
    }

    function updateCountdownText(seconds) {
        resendCountdownEl.textContent = '再送信できるまで ' + seconds + '秒';
    }

    /**
     * SMS送信成功後だけカウントを開始する(送信失敗時はボタンを無効化しない)。
     * 60秒経過で自動的に再送信できるようになる。
     */
    function startResendCooldown() {
        var remaining = RESEND_COOLDOWN_SECONDS;
        resendButton.disabled = true;
        resendCountdownEl.hidden = false;
        updateCountdownText(remaining);

        if (cooldownInterval) {
            clearInterval(cooldownInterval);
        }

        cooldownInterval = setInterval(function () {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(cooldownInterval);
                cooldownInterval = null;
                resendButton.disabled = false;
                resendCountdownEl.hidden = true;
                return;
            }
            updateCountdownText(remaining);
        }, 1000);
    }

    /**
     * POST /auth/sms/send を呼ぶ。成功/失敗をPromiseの真偽値で返すだけで、
     * 画面遷移(コード入力ステップへ進む等)は呼び出し側の責務にする。
     */
    function sendCode() {
        return fetch(smsSendUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ phone_number: collected.phone_number })
        }).then(function (response) {
            if (response.status === 422) {
                return response.json().then(function (data) {
                    var message = (data.errors && data.errors.phone_number && data.errors.phone_number[0])
                        || '認証コードの送信に失敗しました。';
                    showGeneralMessage(message);
                    return false;
                });
            }
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return true;
        }).catch(function () {
            showGeneralMessage('認証コードの送信に失敗しました。時間をおいてもう一度お試しください。');
            return false;
        });
    }

    function goToCodeStepAfterSending() {
        clearGeneralMessage();
        sendCode().then(function (ok) {
            if (!ok) {
                return;
            }
            startResendCooldown();
            codeSentNoteEl.textContent = collected.phone_number + ' 宛てに認証コードを送信しました(5分間有効です)。';
            currentIndex = steps.indexOf('code');
            renderStep();
        });
    }

    /**
     * REGISTRATION_INFO_REQUIRED後、name/address/emailを集め終えたときに使う。
     * 電話番号・入力済みの認証コードはそのまま保持し、新しいコードは送らない。
     */
    function goToCodeStepWithoutSending() {
        currentIndex = steps.indexOf('code');
        renderStep();
    }

    function handleNext(stepName) {
        if (!validateStep(stepName)) {
            return;
        }

        var nextStepName = steps[currentIndex + 1];

        if (nextStepName === 'code') {
            if (pendingRegistrationEscalation) {
                pendingRegistrationEscalation = false;
                goToCodeStepWithoutSending();
            } else {
                goToCodeStepAfterSending();
            }
            return;
        }

        currentIndex += 1;
        renderStep();
    }

    function handleBack() {
        if (currentIndex === 0) {
            return;
        }
        currentIndex -= 1;
        renderStep();
    }

    wizardEl.addEventListener('click', function (event) {
        var nextButton = event.target.closest('[data-next]');
        if (nextButton) {
            handleNext(nextButton.dataset.step);
            return;
        }

        if (event.target.closest('[data-back]')) {
            handleBack();
        }
    });

    resendButton.addEventListener('click', function () {
        if (resendButton.disabled) {
            return;
        }
        clearGeneralMessage();
        sendCode().then(function (ok) {
            if (ok) {
                startResendCooldown();
                codeSentNoteEl.textContent = collected.phone_number + ' 宛てに新しい認証コードを送信しました(5分間有効です)。';
            }
        });
    });

    /**
     * 未登録の電話番号だった場合、name/address/emailのステップを
     * 'code'の直前に差し込み、そちらへ進む。電話番号・入力済みコードは保持する。
     */
    function escalateToRegistration(message) {
        if (steps.indexOf('name') === -1) {
            var codePosition = steps.indexOf('code');
            steps.splice(codePosition, 0, 'name', 'address', 'email');
        }
        pendingRegistrationEscalation = true;
        showGeneralMessage(message);
        currentIndex = steps.indexOf('name');
        renderStep();
    }

    function redirectAfterAuth(user) {
        window.location.href = (user.role === 'farmer') ? farmerHomeUrl : buyerHomeUrl;
    }

    function submitVerify() {
        if (verifyButton.disabled) {
            return;
        }
        verifyButton.disabled = true;
        verifyButton.textContent = '確認しています…';
        clearGeneralMessage();
        clearFieldError('auth-code');

        var payload = {
            phone_number: collected.phone_number,
            code: document.getElementById('auth-code').value.trim()
        };
        if (collected.name) {
            payload.name = collected.name;
        }
        if (collected.address) {
            payload.address = collected.address;
        }
        if (collected.email) {
            payload.email = collected.email;
        }

        fetch(smsVerifyUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (response.status === 422) {
                return response.json().then(function (data) {
                    if (data.error) {
                        if (data.error.code === 'REGISTRATION_INFO_REQUIRED') {
                            escalateToRegistration(data.error.message);
                        } else {
                            // SMS_CODE_EXPIRED・SMS_CODE_NOT_FOUND・SMS_CODE_LOCKED・SMS_CODE_MISMATCH
                            // いずれも同じ画面から再送信できるよう、コード入力欄はそのまま残す
                            showFieldError('auth-code', data.error.message);
                        }
                    } else if (data.errors) {
                        showFieldErrors(data.errors);
                    }
                    return null;
                });
            }
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (!data) {
                return;
            }
            redirectAfterAuth(data.user);
        }).catch(function () {
            showGeneralMessage('認証に失敗しました。時間をおいてもう一度お試しください。');
        }).finally(function () {
            verifyButton.disabled = false;
            verifyButton.textContent = '認証する';
        });
    }

    verifyButton.addEventListener('click', submitVerify);

    // ログイン画面(B2)だけ、メール+パスワードへの切り替えリンクを出す
    if (mode === 'login') {
        switchToEmailEl.hidden = false;
    }

    switchToEmailButton.addEventListener('click', function () {
        wizardEl.hidden = true;
        switchToEmailEl.hidden = true;
        emailLoginForm.hidden = false;
        switchToPhoneEl.hidden = false;
        clearGeneralMessage();
    });

    switchToPhoneButton.addEventListener('click', function () {
        emailLoginForm.hidden = true;
        switchToPhoneEl.hidden = true;
        wizardEl.hidden = false;
        switchToEmailEl.hidden = false;
        clearGeneralMessage();
    });

    emailLoginForm.addEventListener('submit', function (event) {
        event.preventDefault();

        if (emailLoginSubmitButton.disabled) {
            return;
        }
        emailLoginSubmitButton.disabled = true;
        emailLoginSubmitButton.textContent = 'ログインしています…';
        clearGeneralMessage();
        clearFieldError('email');
        clearFieldError('password');

        var payload = {
            email: document.getElementById('login-email').value,
            password: document.getElementById('login-password').value
        };

        fetch(emailLoginUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            if (response.status === 422) {
                return response.json().then(function (data) {
                    if (data.error) {
                        showGeneralMessage(data.error.message);
                    } else if (data.errors) {
                        showFieldErrors(data.errors);
                    }
                    return null;
                });
            }
            if (!response.ok) {
                throw new Error('unexpected status ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            if (!data) {
                return;
            }
            redirectAfterAuth(data.user);
        }).catch(function () {
            showGeneralMessage('ログインに失敗しました。時間をおいてもう一度お試しください。');
        }).finally(function () {
            emailLoginSubmitButton.disabled = false;
            emailLoginSubmitButton.textContent = 'ログインする';
        });
    });

    renderStep();
})();
