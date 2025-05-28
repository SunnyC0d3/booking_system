import { useAuthContext } from '@auth/AuthContext.jsx';

const useAuth = () => {
    const context = useAuthContext();
    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }
    return context;
};

export default useAuth;
