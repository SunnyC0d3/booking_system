import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest) {
    try {
        const body = await request.json();
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        console.log('Login request:', { email: body.email, remember: body.remember });

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

        console.log('Login response status:', response.status);

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));

            console.error('Login failed:', {
                status: response.status,
                data: errorData,
            });

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

        const responseData = await response.json();

        console.log('Login response data:', {
            hasAccessToken: !!responseData?.access_token,
            hasUser: !!responseData?.user,
            userId: responseData?.user?.id,
            dataStructure: Object.keys(responseData || {})
        });

        if (!responseData?.access_token || !responseData?.user) {
            console.error('Invalid login response format:', {
                hasAccessToken: !!responseData?.access_token,
                hasUser: !!responseData?.user,
                responseStructure: Object.keys(responseData || {})
            });

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
            access_token: responseData.access_token,
            refresh_token: responseData.refresh_token,
            user: responseData.user,
            expires_in: responseData.expires_in,
            expires_at: responseData.expires_at,
            token_type: responseData.token_type || 'Bearer',
        });

    } catch (error: any) {
        console.error('Login error:', {
            message: error.message,
            name: error.name,
        });

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