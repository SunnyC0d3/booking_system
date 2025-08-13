import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest) {
    try {
        const body = await request.json();
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        const authHeader = request.headers.get('authorization');

        if (!authHeader) {
            return NextResponse.json(
                {
                    message: 'Client authentication required',
                    errors: { general: ['Authentication service unavailable'] }
                },
                { status: 401 }
            );
        }

        const response = await fetch(`${baseUrl}/api/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': authHeader,
            },
            body: JSON.stringify(body),
            signal: AbortSignal.timeout(15000),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));

            return NextResponse.json(
                {
                    message: errorData.message || 'Login failed',
                    errors: errorData.errors || {
                        general: [errorData.message || 'Authentication failed']
                    }
                },
                { status: response.status }
            );
        }

        let responseData = await response.json();
        let authData = responseData;

        if (responseData?.data && !responseData?.access_token) {
            authData = responseData.data;
        }

        if (!authData?.access_token || !authData?.user) {
            return NextResponse.json(
                {
                    message: 'Invalid response from authentication server',
                    errors: {
                        general: ['Authentication server returned invalid response']
                    }
                },
                { status: 500 }
            );
        }

        return NextResponse.json({
            access_token: authData.access_token,
            refresh_token: authData.refresh_token,
            user: authData.user,
            expires_in: authData.expires_in,
            expires_at: authData.expires_at,
            token_type: authData.token_type || 'Bearer',
        });

    } catch (error: any) {
        if (error.name === 'TimeoutError' || error.name === 'AbortError') {
            return NextResponse.json(
                {
                    message: 'Request timeout',
                    errors: { general: ['The request took too long to complete'] }
                },
                { status: 408 }
            );
        }

        return NextResponse.json(
            {
                message: error.message || 'Login failed',
                errors: {
                    general: [error.message || 'An unexpected error occurred']
                }
            },
            { status: 500 }
        );
    }
}