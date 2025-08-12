import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: NextRequest) {
    try {
        const body = await request.json();
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        console.log('Login request:', { email: body.email, remember: body.remember });

        const response = await axios.post(
            `${baseUrl}/api/login`,
            body,
            {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                timeout: 15000,
            }
        );

        console.log('Login response status:', response.status);
        console.log('Login response data:', {
            hasAccessToken: !!response.data?.access_token,
            hasUser: !!response.data?.user,
            userId: response.data?.user?.id,
            hasDataWrapper: !!response.data?.data,
            dataStructure: Object.keys(response.data || {})
        });

        // Handle different response structures from backend
        let responseData = response.data;

        // If the backend wraps response in a 'data' field, unwrap it
        if (response.data?.data && !response.data?.access_token) {
            responseData = response.data.data;
            console.log('Unwrapped nested data structure');
        }

        // Ensure response has required fields
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

        // Return the response with proper structure
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
            status: error.response?.status,
            data: error.response?.data,
        });

        const errorMessage = error.response?.data?.message || 'Login failed';
        const status = error.response?.status || 500;
        const errors = error.response?.data?.errors;

        return NextResponse.json(
            {
                message: errorMessage,
                errors: errors || {
                    general: [errorMessage]
                }
            },
            { status }
        );
    }
}