import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function GET(request: NextRequest) {
    try {
        const authHeader = request.headers.get('authorization');
        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        if (!authHeader) {
            return NextResponse.json(
                { message: 'Authorization required' },
                { status: 401 }
            );
        }

        const response = await axios.get(
            `${baseUrl}/api/user`,
            {
                headers: {
                    'Authorization': authHeader,
                    'Accept': 'application/json',
                },
                timeout: 10000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Get user profile error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to fetch user profile';
        const status = error.response?.status || 500;

        return NextResponse.json(
            { message: errorMessage },
            { status }
        );
    }
}

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
            `${baseUrl}/api/user`,
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
        console.error('Update user profile error:', error);

        const errorMessage = error.response?.data?.message || 'Failed to update profile';
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