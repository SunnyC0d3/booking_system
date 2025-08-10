'use client';

import { motion } from 'framer-motion';
import { cn } from '@/lib/cn';
import { useMemo } from 'react';

interface AuthFormContainerProps {
    children: React.ReactNode;
    className?: string;
}

export function AuthFormContainer({ children, className }: AuthFormContainerProps) {
    const variants = useMemo(() => ({
        initial: { opacity: 0, x: 20 },
        animate: { opacity: 1, x: 0 },
        transition: { duration: 0.6, delay: 0.2 }
    }), []);

    return (
        <motion.div
            {...variants}
            className={cn("flex justify-center", className)}
        >
            <div className="w-full max-w-md">
                {children}
            </div>
        </motion.div>
    );
}
