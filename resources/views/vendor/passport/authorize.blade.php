<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Authorization</title>

    <!-- Tailwind CSS -->
    <link href="{{ asset('/css/app.css') }}" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white shadow-lg rounded-lg p-6 w-full max-w-lg">
            <h2 class="text-2xl font-semibold text-gray-900 text-center mb-4">Authorization Request</h2>
            <p class="text-gray-600 mb-4"><strong>{{ $client->name }}</strong> is requesting permission to access your account.</p>

            <!-- Scope List -->
            @if (count($scopes) > 0)
            <div class="mb-4">
                <p class="font-semibold text-gray-800">This application will be able to:</p>
                <ul class="list-disc ml-6 text-gray-600">
                    @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="flex justify-between items-center mt-6">
                <!-- Approve Button -->
                <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                    @csrf
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">

                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Authorize
                    </button>
                </form>

                <!-- Cancel Button -->
                <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1 ml-4">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">

                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>