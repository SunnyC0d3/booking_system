import React from 'react';

const UserDashboard = () => {
    return (
        <div className="min-h-screen flex items-center justify-center bg-green-100">
            <div className="bg-white p-8 rounded shadow-md text-center">
                <h1 className="text-2xl font-bold text-green-700">Welcome User!</h1>
                <p className="mt-4 text-sm text-gray-600">This is the user dashboard.</p>
            </div>
        </div>
    );
};

export default UserDashboard;
