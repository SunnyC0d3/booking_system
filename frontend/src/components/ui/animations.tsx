'use client';

import * as React from 'react';
import { motion, AnimatePresence, Variants, useInView, useMotionValue, useSpring } from 'framer-motion';
import { cn } from '@/lib/cn';

// Animation variants library
export const animationVariants = {
    // Page transitions
    pageTransition: {
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
        exit: { opacity: 0, y: -20 },
    },

    // Slide animations
    slideUp: {
        initial: { opacity: 0, y: 30 },
        animate: { opacity: 1, y: 0 },
        exit: { opacity: 0, y: 30 },
    },

    slideDown: {
        initial: { opacity: 0, y: -30 },
        animate: { opacity: 1, y: 0 },
        exit: { opacity: 0, y: -30 },
    },

    slideLeft: {
        initial: { opacity: 0, x: 30 },
        animate: { opacity: 1, x: 0 },
        exit: { opacity: 0, x: 30 },
    },

    slideRight: {
        initial: { opacity: 0, x: -30 },
        animate: { opacity: 1, x: 0 },
        exit: { opacity: 0, x: -30 },
    },

    // Scale animations
    scaleIn: {
        initial: { opacity: 0, scale: 0.9 },
        animate: { opacity: 1, scale: 1 },
        exit: { opacity: 0, scale: 0.9 },
    },

    scaleOut: {
        initial: { opacity: 0, scale: 1.1 },
        animate: { opacity: 1, scale: 1 },
        exit: { opacity: 0, scale: 1.1 },
    },

    // Rotation animations
    rotateIn: {
        initial: { opacity: 0, rotate: -90 },
        animate: { opacity: 1, rotate: 0 },
        exit: { opacity: 0, rotate: 90 },
    },

    // Stagger animations
    staggerContainer: {
        initial: {},
        animate: {
            transition: {
                staggerChildren: 0.1,
                delayChildren: 0.2,
            },
        },
    },

    staggerItem: {
        initial: { opacity: 0, y: 20 },
        animate: { opacity: 1, y: 0 },
    },

    // Loading animations
    pulse: {
        initial: { scale: 1 },
        animate: {
            scale: [1, 1.05, 1],
            transition: {
                duration: 2,
                repeat: Infinity,
                ease: "easeInOut",
            },
        },
    },

    bounce: {
        initial: { y: 0 },
        animate: {
            y: [0, -10, 0],
            transition: {
                duration: 0.6,
                repeat: Infinity,
                ease: "easeInOut",
            },
        },
    },

    // Hover animations
    hoverLift: {
        initial: { y: 0, boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.1)" },
        whileHover: {
            y: -4,
            boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)",
            transition: { duration: 0.2 }
        },
    },

    hoverScale: {
        whileHover: { scale: 1.02 },
        whileTap: { scale: 0.98 },
    },

    // Button animations
    buttonPress: {
        whileTap: { scale: 0.95 },
        transition: { duration: 0.1 },
    },

    // Card animations
    cardHover: {
        initial: { borderColor: "transparent" },
        whileHover: {
            borderColor: "hsl(var(--primary))",
            transition: { duration: 0.3 }
        },
    },
};

// Animated wrapper components
interface AnimatedWrapperProps {
    children: React.ReactNode;
    variant?: keyof typeof animationVariants;
    className?: string;
    delay?: number;
    duration?: number;
    once?: boolean;
}

export const AnimatedWrapper: React.FC<AnimatedWrapperProps> = ({
                                                                    children,
                                                                    variant = 'slideUp',
                                                                    className,
                                                                    delay = 0,
                                                                    duration = 0.5,
                                                                    once = true,
                                                                }) => {
    const ref = React.useRef(null);
    const isInView = useInView(ref, { once });

    const animation = animationVariants[variant];

    return (
        <motion.div
            ref={ref}
            className={className}
            variants={animation}
            initial="initial"
            animate={isInView ? "animate" : "initial"}
            transition={{ duration, delay }}
        >
            {children}
        </motion.div>
    );
};

// Stagger children animation
export const StaggerContainer: React.FC<{
    children: React.ReactNode;
    className?: string;
    staggerDelay?: number;
}> = ({ children, className, staggerDelay = 0.1 }) => {
    return (
        <motion.div
            className={className}
            variants={{
                animate: {
                    transition: {
                        staggerChildren: staggerDelay,
                    },
                },
            }}
            initial="initial"
            animate="animate"
        >
            {children}
        </motion.div>
    );
};

export const StaggerItem: React.FC<{
    children: React.ReactNode;
    className?: string;
}> = ({ children, className }) => {
    return (
        <motion.div
            className={className}
            variants={animationVariants.staggerItem}
        >
            {children}
        </motion.div>
    );
};

// Loading spinner with animation
export const AnimatedSpinner: React.FC<{
    size?: 'sm' | 'md' | 'lg';
    color?: string;
}> = ({ size = 'md', color = 'currentColor' }) => {
    const sizeClasses = {
        sm: 'w-4 h-4',
        md: 'w-6 h-6',
        lg: 'w-8 h-8',
    };

    return (
        <motion.div
            className={cn('inline-block', sizeClasses[size])}
            animate={{ rotate: 360 }}
            transition={{ duration: 1, repeat: Infinity, ease: "linear" }}
        >
            <svg
                className="w-full h-full"
                fill="none"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke={color}
                    strokeWidth="4"
                />
                <path
                    className="opacity-75"
                    fill={color}
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
        </motion.div>
    );
};

// Animated counter
export const AnimatedCounter: React.FC<{
    value: number;
    duration?: number;
    className?: string;
}> = ({ value, duration = 2, className }) => {
    const motionValue = useMotionValue(0);
    const springValue = useSpring(motionValue, { duration: duration * 1000 });
    const [displayValue, setDisplayValue] = React.useState(0);

    React.useEffect(() => {
        motionValue.set(value);
    }, [motionValue, value]);

    React.useEffect(
        () =>
            springValue.on("change", (latest) => {
                setDisplayValue(Math.round(latest));
            }),
        [springValue]
    );

    return <span className={className}>{displayValue.toLocaleString()}</span>;
};

// Animated progress bar
export const AnimatedProgressBar: React.FC<{
    progress: number;
    className?: string;
    barClassName?: string;
    showPercentage?: boolean;
}> = ({ progress, className, barClassName, showPercentage = false }) => {
    return (
        <div className={cn('w-full bg-muted rounded-full h-2', className)}>
            <motion.div
                className={cn('bg-primary h-full rounded-full', barClassName)}
                initial={{ width: 0 }}
                animate={{ width: `${Math.max(0, Math.min(100, progress))}%` }}
                transition={{ duration: 0.8, ease: "easeOut" }}
            />
            {showPercentage && (
                <motion.span
                    className="text-sm text-muted-foreground mt-1 block"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 0.5 }}
                >
                    {Math.round(progress)}%
                </motion.span>
            )}
        </div>
    );
};

// Animated notification toast
export const AnimatedToast: React.FC<{
    children: React.ReactNode;
    isVisible: boolean;
    position?: 'top' | 'bottom';
    className?: string;
}> = ({ children, isVisible, position = 'top', className }) => {
    return (
        <AnimatePresence>
            {isVisible && (
                <motion.div
                    className={cn(
                        'fixed left-1/2 z-50 w-full max-w-md -translate-x-1/2',
                        position === 'top' ? 'top-4' : 'bottom-4',
                        className
                    )}
                    variants={{
                        initial: {
                            opacity: 0,
                            y: position === 'top' ? -50 : 50,
                            scale: 0.9
                        },
                        animate: {
                            opacity: 1,
                            y: 0,
                            scale: 1
                        },
                        exit: {
                            opacity: 0,
                            y: position === 'top' ? -50 : 50,
                            scale: 0.9
                        },
                    }}
                    initial="initial"
                    animate="animate"
                    exit="exit"
                    transition={{ duration: 0.3, ease: "easeOut" }}
                >
                    {children}
                </motion.div>
            )}
        </AnimatePresence>
    );
};

// Animated modal backdrop
export const AnimatedModalBackdrop: React.FC<{
    children: React.ReactNode;
    isOpen: boolean;
    onClose: () => void;
    className?: string;
}> = ({ children, isOpen, onClose, className }) => {
    return (
        <AnimatePresence>
            {isOpen && (
                <motion.div
                    className={cn(
                        'fixed inset-0 z-50 flex items-center justify-center bg-black/50',
                        className
                    )}
                    variants={{
                        initial: { opacity: 0 },
                        animate: { opacity: 1 },
                        exit: { opacity: 0 },
                    }}
                    initial="initial"
                    animate="animate"
                    exit="exit"
                    transition={{ duration: 0.2 }}
                    onClick={onClose}
                >
                    <motion.div
                        variants={{
                            initial: { opacity: 0, scale: 0.9, y: 20 },
                            animate: { opacity: 1, scale: 1, y: 0 },
                            exit: { opacity: 0, scale: 0.9, y: 20 },
                        }}
                        transition={{ duration: 0.3, ease: "easeOut" }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        {children}
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
};

// Hover card animation
export const HoverCard: React.FC<{
    children: React.ReactNode;
    className?: string;
}> = ({ children, className }) => {
    return (
        <motion.div
            className={className}
            variants={animationVariants.hoverLift}
            initial="initial"
            whileHover="whileHover"
        >
            {children}
        </motion.div>
    );
};

// Floating action button with animation
export const FloatingActionButton: React.FC<{
    children: React.ReactNode;
    onClick?: () => void;
    className?: string;
}> = ({ children, onClick, className }) => {
    return (
        <motion.button
            className={cn(
                'fixed bottom-6 right-6 w-14 h-14 bg-primary text-primary-foreground rounded-full shadow-lg flex items-center justify-center',
                className
            )}
            variants={{
                initial: { scale: 0 },
                animate: { scale: 1 },
                whileHover: { scale: 1.1 },
                whileTap: { scale: 0.9 },
            }}
            initial="initial"
            animate="animate"
            whileHover="whileHover"
            whileTap="whileTap"
            transition={{ duration: 0.2 }}
            onClick={onClick}
        >
            {children}
        </motion.button>
    );
};

// Page transition wrapper
export const PageTransition: React.FC<{
    children: React.ReactNode;
    className?: string;
}> = ({ children, className }) => {
    return (
        <motion.div
            className={className}
            variants={animationVariants.pageTransition}
            initial="initial"
            animate="animate"
            exit="exit"
            transition={{ duration: 0.3, ease: "easeInOut" }}
        >
            {children}
        </motion.div>
    );
};

// Typing animation for text
export const TypingAnimation: React.FC<{
    text: string;
    speed?: number;
    className?: string;
}> = ({ text, speed = 50, className }) => {
    const [displayText, setDisplayText] = React.useState('');
    const [currentIndex, setCurrentIndex] = React.useState(0);

    React.useEffect(() => {
        if (currentIndex < text.length) {
            const timeout = setTimeout(() => {
                setDisplayText(prev => prev + text[currentIndex]);
                setCurrentIndex(prev => prev + 1);
            }, speed);

            return () => clearTimeout(timeout);
        }
    }, [currentIndex, text, speed]);

    return (
        <motion.span
            className={className}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
        >
            {displayText}
            <motion.span
                animate={{ opacity: [1, 0] }}
                transition={{ duration: 0.8, repeat: Infinity, ease: "easeInOut" }}
            >
                |
            </motion.span>
        </motion.span>
    );
};