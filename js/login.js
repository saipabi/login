/**
 * login.js
 * Handles user login using jQuery AJAX and browser localStorage.
 * Strictly decoupled from HTML and CSS.
 */

$(document).ready(function () {

    // If user is already logged in, redirect directly to profile page
    if (localStorage.getItem('auth_token')) {
        window.location.href = 'profile.html';
        return;
    }

    const $form = $('#login-form');
    const $alert = $('#login-alert');
    const $alertMessage = $('#alert-message');
    const $alertIcon = $('#alert-icon');
    const $btn = $('#btn-login');
    const $btnText = $('#btn-text');
    const $btnSpinner = $('#btn-spinner');

    // Auto-fill remembered email if available
    const savedEmail = localStorage.getItem('remembered_email');
    if (savedEmail) {
        $('#login-email').val(savedEmail);
        $('#remember-me').prop('checked', true);
    }

    /**
     * Show notification alert
     * @param {string} type - 'success', 'danger', or 'warning'
     * @param {string} message - Message text
     */
    function showAlert(type, message) {
        $alert.removeClass('alert-success alert-danger alert-warning')
              .addClass('alert-' + type);

        if (type === 'success') {
            $alertIcon.attr('class', 'bi bi-check-circle-fill me-2');
        } else {
            $alertIcon.attr('class', 'bi bi-exclamation-triangle-fill me-2');
        }

        $alertMessage.text(message);
        $alert.fadeIn(200);
    }

    function hideAlert() {
        $alert.hide();
    }

    function setLoading(isLoading) {
        if (isLoading) {
            $btn.prop('disabled', true);
            $btnSpinner.removeClass('d-none');
            $btnText.text(' Signing In...');
        } else {
            $btn.prop('disabled', false);
            $btnSpinner.addClass('d-none');
            $btnText.html('<i class="bi bi-box-arrow-in-right me-2"></i>Sign In');
        }
    }

    // Intercept form submission strictly using jQuery AJAX
    $form.on('submit', function (e) {
        e.preventDefault();
        hideAlert();

        const email = $('#login-email').val().trim();
        const password = $('#login-password').val();
        const rememberMe = $('#remember-me').is(':checked');

        // Client-side validations
        if (!email) {
            showAlert('danger', 'Please enter your email address.');
            $('#login-email').focus();
            return;
        }

        if (!password) {
            showAlert('danger', 'Please enter your password.');
            $('#login-password').focus();
            return;
        }

        setLoading(true);

        // Perform jQuery AJAX POST request
        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            dataType: 'json',
            data: {
                email: email,
                password: password
            },
            success: function (response) {
                setLoading(false);
                const token = (response.data && response.data.token) ? response.data.token : response.token;
                const user = (response.data && response.data.user) ? response.data.user : response.user;

                if (response.status === 'success' && token) {
                    // Strictly store session in browser localStorage (Requirement: No PHP session)
                    localStorage.setItem('auth_token', token);
                    if (user) {
                        localStorage.setItem('user_info', JSON.stringify(user));
                    }

                    if (rememberMe) {
                        localStorage.setItem('remembered_email', email);
                    } else {
                        localStorage.removeItem('remembered_email');
                    }

                    showAlert('success', 'Login successful! Redirecting to your profile...');

                    // Redirect to profile page
                    setTimeout(function () {
                        window.location.href = 'profile.html';
                    }, 800);
                } else {
                    showAlert('danger', response.message || 'Invalid email or password.');
                }
            },
            error: function (xhr, status, error) {
                setLoading(false);
                let errorMsg = 'Failed to authenticate. Please check your credentials and try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed.message) errorMsg = parsed.message;
                    } catch (e) {
                        // Response is not JSON
                    }
                }
                showAlert('danger', errorMsg);
            }
        });
    });

});
