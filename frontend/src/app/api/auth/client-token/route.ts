import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

interface ClientCredentialsResponse {
    access_token: string;
    token_type: 'Bearer';
    expires_in: number;
    scope?: string;
}

export async function POST(request: NextRequest) {
    try {
        const clientId = process.env.API_CLIENT_ID;
        const clientSecret = process.env.API_SECRET_KEY;
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!clientId || !clientSecret) {
            return NextResponse.json(
                { error: 'Client credentials not configured' },
                { status: 500 }
            );
        }

        const credentials = Buffer.from(`${clientId}:${clientSecret}`).toString('base64');

        const response = await axios.post<ClientCredentialsResponse>(
            `${baseUrl}/oauth/token`,
            {
                grant_type: 'client_credentials',
                client_id: clientId,
                client_secret: clientSecret,
                scope: '',
            },
            {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Basic ${credentials}`,
                },
                timeout: 10000,
            }
        );

        return NextResponse.json({
            access_token: response.data.access_token,
            expires_in: response.data.expires_in,
            token_type: response.data.token_type,
        });

    } catch (error: any) {
        console.error('Client credentials error:', error);

        const errorMessage = error.response?.data?.error_description ||
            error.response?.data?.message ||
            error.message ||
            'Authentication failed';

        return NextResponse.json(
            { error: errorMessage },
            { status: error.response?.status || 500 }
        );
    }
}