<div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="bg-white shadow-md rounded-md p-6 max-w-lg w-full">
        <!-- Heading -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-4">Email Verification</h1>
        <p class="text-gray-600 text-center mb-6">
            Please verify your email before accessing the dashboard.
        </p>

        <!-- Resend Verification Email Form -->
        <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
            @csrf
            <div class="text-center">
                <button type="submit"
                    class="w-full px-4 py-2 text-white bg-indigo-600 rounded-md 
                               hover:bg-indigo-700 focus:outline-none focus:ring-2 
                               focus:ring-indigo-500 focus:ring-offset-2">
                    Resend Verification Email
                </button>
            </div>
        </form>

        <!-- Success Message -->
        @if (session('message'))
        <div class="mt-4 p-4 text-sm bg-green-100 text-green-800 rounded-md text-center">
            {{ session('message') }}
        </div>
        @endif
    </div>
</div>