import React from 'react';
import {useNavigate} from 'react-router-dom';
import useAuth from '@hooks/useAuth';
import Container from '@components/Wrapper/Container';

const Home = () => {
    const {authenticated, getRedirectPath} = useAuth();
    const navigate = useNavigate();

    return (
        <Container>
            <main className="flex flex-col items-center justify-center flex-grow text-center px-6 py-12">
                <h2 className="text-4xl font-bold text-gray-800 mb-4">Professional Digital Templates</h2>
                <p className="text-lg text-gray-600 max-w-2xl mb-8">
                    Empower your design and development process with premium digital templates. Whether you're building
                    a landing page, mobile UI, or admin dashboard – we've got you covered.
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 text-left max-w-4xl w-full">
                    <Feature
                        title="UI Kits"
                        description="Modern, reusable UI components for rapid prototyping and product design."
                    />
                    <Feature
                        title="Website Templates"
                        description="Responsive HTML/CSS templates ready to launch for any industry."
                    />
                    <Feature
                        title="Dashboard Layouts"
                        description="Admin panel templates with rich components and clean code."
                    />
                </div>
                <button
                    onClick={() => navigate(authenticated ? getRedirectPath() : '/products')}
                    className="mt-10 px-6 py-3 text-white bg-indigo-600 rounded hover:bg-indigo-700 text-lg font-semibold"
                >
                    {authenticated ? 'Go to Dashboard' : 'Browse Templates'}
                </button>
            </main>
        </Container>
    );
};

const Feature = ({title, description}) => (
    <div className="bg-white p-6 rounded shadow hover:shadow-md transition">
        <h3 className="text-xl font-semibold text-indigo-700 mb-2">{title}</h3>
        <p className="text-gray-600">{description}</p>
    </div>
);

export default Home;
