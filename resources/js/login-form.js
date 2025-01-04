import { hasErrors } from './helper';

export default function loginForm() {
    const form = document.getElementById('loginForm');

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            document.querySelectorAll('.error').forEach(element => {
                element.innerHTML = '';
            });

            const formData = {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                _token: document.querySelector('input[name="_token"]').value
            };

            if(!hasErrors(formData)) {
                form.submit();
            }
        });
    }
}