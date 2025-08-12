import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function PATCH(request: NextRequest) {
    try {
        const body = await request.json();
        const authHeader = request.headers.get('authorization');
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!authHeader) {
            return NextResponse.json(
                { message: 'Authorization required' },
                { status: 401 }
            );
        }

        const response = await axios.patch(
            `${baseUrl}/api/user/preferences`,
            body,
            {
                headers: {
                    'Authorization': authHeader,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                timeout: 15000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Update preferences error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to update preferences';
        const status = error.response?.status || 500;
        const errors = error.response?.data?.errors;

        return NextResponse.json(
            {
                message: errorMessage,
                errors: errors
            },
            { status }
        );
    }
}