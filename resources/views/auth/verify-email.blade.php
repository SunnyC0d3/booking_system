<h1>Email Verification</h1>
<p>Please verify your email before accessing the dashboard.</p>
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit">Resend Verification Email</button>
</form>
@if (session('message'))
    <p>{{ session('message') }}</p>
@endif
