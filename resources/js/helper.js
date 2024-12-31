export function displayError(id, message) {
    const errorElement = document.getElementById(id);
    if (errorElement) {
        errorElement.innerHTML = message;
    }
}

export function isValidEmail(email) {
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailPattern.test(email);
}

export function hasErrors(formData) {
    let hasErrors = false;

    const validationRules = {
        name: {
            required: 'The name field is required.',
            max: [255, 'The name may not be greater than 255 characters.'],
        },
        email: {
            required: 'The email field is required.',
            email: 'The email must be a valid email address.',
            max: [255, 'The email may not be greater than 255 characters.'],
        },
        password: {
            required: 'The password field is required.',
            min: [8, 'The password must be at least 8 characters.'],
        },
        passwordConfirmation: {
            required: 'The password confirmation field is required.',
            match: ['password', 'The password confirmation does not match.'],
        },
    };

    for (const [field, rules] of Object.entries(validationRules)) {
        const value = formData[field];

        if (value === undefined) {
            continue;
        }

        if (rules.required && !value) {
            displayError(`error-${field}`, rules.required);
            hasErrors = true;
            continue;
        }

        if (rules.max && value.length > rules.max[0]) {
            displayError(`error-${field}`, rules.max[1]);
            hasErrors = true;
        }

        if (rules.min && value.length < rules.min[0]) {
            displayError(`error-${field}`, rules.min[1]);
            hasErrors = true;
        }

        if (rules.email && !isValidEmail(value)) {
            displayError(`error-${field}`, rules.email);
            hasErrors = true;
        }

        if (rules.match) {
            const [otherField, message] = rules.match;
            if (value !== formData[otherField]) {
                displayError(`error-${field}`, message);
                hasErrors = true;
            }
        }
    }

    return hasErrors;
}

export async function hmac(data) {
    const secretKey = process.env.HMAC_SECRET_KEY;

    const hmac = crypto
        .createHmac('sha256', secretKey)
        .update(JSON.stringify({ data, timestamp: Date.now() }))
        .digest('hex');

    return hmac;
}