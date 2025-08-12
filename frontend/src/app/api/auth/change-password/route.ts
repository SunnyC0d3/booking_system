import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function POST(request: NextRequest) {
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

        const response = await axios.post(
            `${baseUrl}/api/change-password`,
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
        console.error('Change password error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to change password';
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