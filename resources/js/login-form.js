import { hasErrors } from './helper';

export default function loginForm() {
    if (document.getElementById('loginForm')) {
        document.getElementById('loginForm').addEventListener('submit', function (event) {
            event.preventDefault();

            document.querySelectorAll('.error').forEach(element => {
                element.innerHTML = '';
            });

            const formData = {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                _token: document.querySelector('input[name="_token"]').value
            };

            if (!hasErrors(formData)) {
                fetch("{{ route('auth.login') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Something went wrong!');
                        }

                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 200) {
                            window.location.href = data['data'].redirectUrl;
                        } else {
                            document.querySelectorAll('.error').forEach(element => {
                                element.innerHTML = '';
                            });

                            if (data['errors']) {
                                for (const field in data['errors']) {
                                    const errorElement = document.getElementById(`error-${field}`);
                                    if (errorElement) {
                                        errorElement.innerHTML = data['errors'][field].join('<br>');
                                    }
                                }
                            }
                        }
                    })
                    .then(error => {
                        console.log(error);
                    });
            }
        });
    }
}