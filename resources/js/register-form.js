import { hasErrors } from './helper';

export default function registerForm() {
    const form = document.getElementById('registerForm');

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            document.querySelectorAll('.error').forEach(element => {
                element.innerHTML = '';
            });

            const formData = {
                email: document.getElementById('email').value,
                name: document.getElementById('name').value,
                password: document.getElementById('password').value,
                password_confirmation: document.getElementById('password_confirmation').value,
                _token: document.querySelector('input[name="_token"]').value
            };

            if(!hasErrors(formData)) {
                form.submit();
            }
        });
    }
}