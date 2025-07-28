import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Combines class names with tailwind-merge to handle conflicts
 * and clsx for conditional classes
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}