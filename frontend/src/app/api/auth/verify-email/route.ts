import { NextRequest, NextResponse } from 'next/server';
import axios from 'axios';

export async function GET(request: NextRequest) {
    try {
        const { searchParams } = new URL(request.url);
        const id = searchParams.get('id');
        const hash = searchParams.get('hash');
        const expires = searchParams.get('expires');
        const signature = searchParams.get('signature');

        if (!id || !hash || !expires || !signature) {
            return NextResponse.json(
                { message: 'Invalid verification parameters' },
                { status: 400 }
            );
        }

        const baseUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

        const response = await axios.get(
            `${baseUrl}/email/verify/${id}/${hash}?expires=${expires}&signature=${signature}`,
            {
                headers: {
                    'Accept': 'application/json',
                },
                timeout: 15000,
            }
        );

        return NextResponse.json(response.data);

    } catch (error: any) {
        console.error('Email verification error:', error);

        const errorMessage = error.response?.data?.message || 'Email verification failed';
        const status = error.response?.status || 500;

        return NextResponse.json(
            { message: errorMessage },
            { status }
        );
    }
}