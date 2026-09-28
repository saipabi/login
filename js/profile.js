/**
 * profile.js
 * Handles profile fetching, editing, and logout using jQuery AJAX and browser localStorage.
 * Strictly decoupled from HTML and CSS.
 */

$(document).ready(function () {

    const token = localStorage.getItem('auth_token');

    // Requirement: Session maintained only using browser localStorage
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    const $alert = $('#profile-alert');
    const $alertMessage = $('#profile-alert-message');
    const $alertIcon = $('#profile-alert-icon');
    const $btnSave = $('#btn-save-profile');
    const $saveText = $('#save-btn-text');
    const $saveSpinner = $('#save-btn-spinner');

    /**
     * Show notification alert
     * @param {string} type - 'success', 'danger', or 'info'
     * @param {string} message - Message text
     */
    function showAlert(type, message) {
        $alert.removeClass('alert-success alert-danger alert-info alert-warning')
              .addClass('alert-' + type);

        if (type === 'success') {
            $alertIcon.attr('class', 'bi bi-check-circle-fill me-2');
        } else if (type === 'danger') {
            $alertIcon.attr('class', 'bi bi-exclamation-triangle-fill me-2');
        } else {
            $alertIcon.attr('class', 'bi bi-info-circle-fill me-2');
        }

        $alertMessage.text(message);
        $alert.fadeIn(200);

        // Auto-hide alert after 5 seconds if success
        if (type === 'success') {
            setTimeout(function () {
                $alert.fadeOut(300);
            }, 5000);
        }
    }

    function setSaving(isSaving) {
        if (isSaving) {
            $btnSave.prop('disabled', true);
            $saveSpinner.removeClass('d-none');
            $saveText.text(' Saving to MongoDB...');
        } else {
            $btnSave.prop('disabled', false);
            $saveSpinner.addClass('d-none');
            $saveText.html('<i class="bi bi-cloud-arrow-up-fill me-2"></i>Save to MongoDB');
        }
    }

    /**
     * Fetch user profile data via jQuery AJAX
     */
    function loadProfile() {
        $.ajax({
            url: 'php/profile.php',
            type: 'GET',
            dataType: 'json',
            data: {
                token: token
            },
            success: function (response) {
                if (response.status === 'success' && response.data) {
                    const data = response.data;
                    const user = data.user || {};
                    const profile = data.profile || {};

                    // Fill Header & Summary
                    const name = user.name || 'User';
                    const email = user.email || 'user@example.com';

                    $('#nav-user-email').text(email);
                    $('#profile-display-name').text(name);
                    $('#profile-display-email').text(email);
                    $('#avatar-initial').text(name.charAt(0).toUpperCase());

                    // Fill Form: MySQL Credentials (readonly)
                    $('#prof-name').val(name);
                    $('#prof-email').val(email);

                    // Fill Form: MongoDB Profile Data
                    $('#prof-age').val(profile.age || '');
                    $('#prof-dob').val(profile.dob || '');
                    $('#prof-contact').val(profile.contact || '');
                    $('#prof-city').val(profile.city || '');
                    $('#prof-address').val(profile.address || '');
                    $('#prof-bio').val(profile.bio || '');

                    if (profile.updated_at) {
                        $('#profile-last-updated').text(new Date(profile.updated_at).toLocaleString());
                    } else {
                        $('#profile-last-updated').text('Initial creation');
                    }
                } else {
                    // Session invalid or expired in Redis
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    alert(response.message || 'Session expired. Please log in again.');
                    window.location.href = 'login.html';
                }
            },
            error: function (xhr) {
                // If 401 Unauthorized (session expired in Redis)
                if (xhr.status === 401) {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    alert('Session expired or invalid. Please sign in again.');
                    window.location.href = 'login.html';
                } else {
                    showAlert('danger', 'Unable to fetch profile details from backend.');
                }
            }
        });
    }

    // Initial load
    loadProfile();

    // Auto-calculate age when DOB is changed
    $('#prof-dob').on('change', function () {
        const dobVal = $(this).val();
        if (dobVal) {
            const birthDate = new Date(dobVal);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            if (age >= 0 && age <= 120) {
                $('#prof-age').val(age);
            }
        }
    });

    // Reload / Reset button handler
    $('#btn-reload').on('click', function () {
        loadProfile();
        showAlert('info', 'Profile data reloaded.');
    });

    // Intercept profile form submission strictly using jQuery AJAX
    $('#profile-form').on('submit', function (e) {
        e.preventDefault();

        const age = $('#prof-age').val().trim();
        const dob = $('#prof-dob').val().trim();
        const contact = $('#prof-contact').val().trim();
        const city = $('#prof-city').val().trim();
        const address = $('#prof-address').val().trim();
        const bio = $('#prof-bio').val().trim();

        // Basic client validation
        if (age && (isNaN(age) || age < 1 || age > 120)) {
            showAlert('danger', 'Please enter a valid age between 1 and 120.');
            $('#prof-age').focus();
            return;
        }

        setSaving(true);

        // Perform jQuery AJAX POST to update MongoDB profile
        $.ajax({
            url: 'php/profile.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'update',
                token: token,
                age: age,
                dob: dob,
                contact: contact,
                city: city,
                address: address,
                bio: bio
            },
            success: function (response) {
                setSaving(false);
                if (response.status === 'success') {
                    showAlert('success', response.message || 'Profile successfully saved to MongoDB!');
                    if (response.data && response.data.updated_at) {
                        $('#profile-last-updated').text(new Date(response.data.updated_at).toLocaleString());
                    }
                } else {
                    showAlert('danger', response.message || 'Failed to update profile.');
                }
            },
            error: function (xhr) {
                setSaving(false);
                if (xhr.status === 401) {
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    alert('Session expired. Please log in again.');
                    window.location.href = 'login.html';
                } else {
                    let errMsg = 'Failed to save profile. Server error.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errMsg = xhr.responseJSON.message;
                    }
                    showAlert('danger', errMsg);
                }
            }
        });
    });

    // Logout handling: invalidates session in Redis and clears localStorage
    $('#btn-logout').on('click', function () {
        if (confirm('Are you sure you want to log out?')) {
            $.ajax({
                url: 'php/profile.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'logout',
                    token: token
                },
                complete: function () {
                    // Always clear localStorage and redirect to login
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('user_info');
                    window.location.href = 'login.html';
                }
            });
        }
    });

});
