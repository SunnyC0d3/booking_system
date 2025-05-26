export function checkAccessTokenExpiry() {
    const token = localStorage.getItem('access_token');
    const expiry = localStorage.getItem('access_token_expiry');

    if (!token || !expiry || Date.now() > parseInt(expiry, 10)) {
        localStorage.removeItem('access_token');
        localStorage.removeItem('access_token_expiry');
        return false;
    }

    return true;
}

export function checkIfUserWasLoggedIn() {
    const user = localStorage.getItem('user');

    if (!user) {
        localStorage.removeItem('user');
        return false;
    }

    return true;
}
