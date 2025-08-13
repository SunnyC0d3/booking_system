import { NextRequest, NextResponse } from 'next/server';

interface ClientCredentialsResponse {
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
    scope?: string;
}

interface ClientCredentialsRequest {
    grant_type: 'client_credentials';
    client_id: string;
    client_secret: string;
    scope: string;
}

export async function POST(request: NextRequest) {
    try {
        const clientId = process.env.API_CLIENT_ID;
        const clientSecret = process.env.API_SECRET_KEY;
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!clientId || !clientSecret) {
            console.error('Client credentials not configured');
            return NextResponse.json(
                {
                    error: 'Client credentials not configured',
                    code: 'MISSING_CREDENTIALS'
                },
                { status: 500 }
            );
        }

        const credentials = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');

        const requestBody: ClientCredentialsRequest = {
            grant_type: 'client_credentials',
            client_id: clientId,
            client_secret: clientSecret,
            scope: '',
        };

        console.log('Requesting client credentials token...');

        const response = await fetch(`${baseUrl}/oauth/token`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Basic ${credentials}`,
                'User-Agent': 'CreativeBusiness-Frontend/1.0',
            },
            body: JSON.stringify(requestBody),
            signal: AbortSignal.timeout(10000),
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));

            console.error('Client credentials request failed:', {
                status: response.status,
                statusText: response.statusText,
                error: errorData,
            });

            const errorMessage = errorData.error_description ||
                errorData.message ||
                errorData.error ||
                `HTTP ${response.status}: ${response.statusText}`;

            return NextResponse.json(
                {
                    error: errorMessage,
                    code: errorData.error || 'CLIENT_CREDENTIALS_FAILED',
                    status: response.status
                },
                { status: response.status }
            );
        }

        const tokenData: ClientCredentialsResponse = await response.json();

        // Validate response structure
        if (!tokenData.access_token || !tokenData.expires_in) {
            console.error('Invalid client credentials response:', {
                hasAccessToken: !!tokenData.access_token,
                hasExpiresIn: !!tokenData.expires_in,
                responseKeys: Object.keys(tokenData)
            });

            return NextResponse.json(
                {
                    error: 'Invalid token response from OAuth server',
                    code: 'INVALID_TOKEN_RESPONSE'
                },
                { status: 502 }
            );
        }

        console.log('Client credentials token obtained successfully', {
            tokenType: tokenData.token_type,
            expiresIn: tokenData.expires_in,
            hasScope: !!tokenData.scope
        });

        return NextResponse.json({
            access_token: tokenData.access_token,
            expires_in: tokenData.expires_in,
            token_type: tokenData.token_type,
            scope: tokenData.scope,
            issued_at: Math.floor(Date.now() / 1000),
        });

    } catch (error: any) {
        console.error('Client credentials error:', {
            message: error.message,
            name: error.name,
            stack: process.env.NODE_ENV === 'development' ? error.stack : undefined,
        });

        if (error.name === 'TimeoutError' || error.name === 'AbortError') {
            return NextResponse.json(
                {
                    error: 'OAuth server timeout',
                    code: 'OAUTH_TIMEOUT'
                },
                { status: 408 }
            );
        }

        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            return NextResponse.json(
                {
                    error: 'Unable to connect to OAuth server',
                    code: 'OAUTH_CONNECTION_FAILED'
                },
                { status: 503 }
            );
        }

        return NextResponse.json(
            {
                error: error.message || 'Authentication service unavailable',
                code: 'INTERNAL_ERROR'
            },
            { status: 500 }
        );
    }
}