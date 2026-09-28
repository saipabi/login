// Redirect logged in user to profile page
$(document).ready(function () {
    const token = localStorage.getItem('auth_token');
    if (token) {
        window.location.href = 'profile.html';
    }
});
