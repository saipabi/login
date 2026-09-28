/**
 * register.js
 * Handles user registration using jQuery AJAX.
 * Strictly decoupled from HTML and CSS.
 */

$(document).ready(function () {

    // If user is already logged in, redirect to profile
    if (localStorage.getItem('auth_token')) {
        window.location.href = 'profile.html';
        return;
    }

    const $form = $('#register-form');
    const $alert = $('#register-alert');
    const $alertMessage = $('#alert-message');
    const $alertIcon = $('#alert-icon');
    const $btn = $('#btn-register');
    const $btnText = $('#btn-text');
    const $btnSpinner = $('#btn-spinner');

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
            $btnText.text(' Creating Account...');
        } else {
            $btn.prop('disabled', false);
            $btnSpinner.addClass('d-none');
            $btnText.html('<i class="bi bi-person-check-fill me-2"></i>Register Now');
        }
    }

    // Intercept form submission strictly using jQuery AJAX
    $form.on('submit', function (e) {
        e.preventDefault();
        hideAlert();

        const name = $('#reg-name').val().trim();
        const email = $('#reg-email').val().trim();
        const password = $('#reg-password').val();
        const confirmPassword = $('#reg-confirm-password').val();
        const termsAccepted = $('#reg-terms').is(':checked');

        // Validation checks
        if (!name) {
            showAlert('danger', 'Please enter your full name.');
            $('#reg-name').focus();
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showAlert('danger', 'Please enter a valid email address.');
            $('#reg-email').focus();
            return;
        }

        if (password.length < 6) {
            showAlert('danger', 'Password must be at least 6 characters long.');
            $('#reg-password').focus();
            return;
        }

        if (password !== confirmPassword) {
            showAlert('danger', 'Passwords do not match. Please re-enter.');
            $('#reg-confirm-password').focus();
            return;
        }

        if (!termsAccepted) {
            showAlert('warning', 'Please accept the Terms of Service to proceed.');
            return;
        }

        setLoading(true);

        // Perform jQuery AJAX POST request
        $.ajax({
            url: 'php/register.php',
            type: 'POST',
            dataType: 'json',
            data: {
                name: name,
                email: email,
                password: password
            },
            success: function (response) {
                setLoading(false);
                if (response.status === 'success') {
                    showAlert('success', response.message || 'Registration successful! Redirecting to login...');
                    $form[0].reset();
                    // Redirect after 1.5 seconds
                    setTimeout(function () {
                        window.location.href = 'login.html';
                    }, 1500);
                } else {
                    showAlert('danger', response.message || 'Registration failed. Please try again.');
                }
            },
            error: function (xhr, status, error) {
                setLoading(false);
                let errorMsg = 'An unexpected server error occurred. Please try again.';
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
