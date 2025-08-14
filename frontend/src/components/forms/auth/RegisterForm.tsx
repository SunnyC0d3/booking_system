'use client';

import { useState, useTransition, useCallback, useMemo } from 'react';
import { useAuthUtils } from '@/hooks/useAuthUtils';
import { Button, Checkbox, Input, Label } from '@/components/ui';
import { toast } from 'sonner';
import Link from 'next/link';
import { Eye, EyeOff, Loader2, Check, X } from 'lucide-react';
import { z } from 'zod';

const registerSchema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters').max(50, 'Name must be less than 50 characters'),
    email: z.string().email('Please enter a valid email address'),
    password: z.string()
        .min(8, 'Password must be at least 8 characters')
        .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/, 'Password must contain at least one lowercase letter, one uppercase letter, and one number'),
    password_confirmation: z.string(),
    terms_accepted: z.boolean().refine(val => val === true, {
        message: 'Please accept the terms and conditions'
    }),
    marketing_consent: z.boolean().default(false)
}).refine(data => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"]
});

type RegisterFormData = z.infer<typeof registerSchema>;

interface RegisterFormProps {
    onSuccess?: () => void;
    redirectPath?: string;
    className?: string;
}

const PasswordStrengthIndicator = ({ password }: { password: string }) => {
    const strength = useMemo(() => {
        const checks = [
            { test: /.{8,}/, label: 'At least 8 characters' },
            { test: /[a-z]/, label: 'Lowercase letter' },
            { test: /[A-Z]/, label: 'Uppercase letter' },
            { test: /\d/, label: 'Number' }
        ];

        return checks.map(check => ({
            ...check,
            passed: check.test.test(password)
        }));
    }, [password]);

    if (!password) return null;

    return (
        <div className="mt-2 space-y-1">
            <p className="text-xs text-gray-600">Password requirements:</p>
            {strength.map((requirement, index) => (
                <div key={index} className="flex items-center gap-2 text-xs">
                    {requirement.passed ? (
                        <Check className="h-3 w-3 text-green-600" />
                    ) : (
                        <X className="h-3 w-3 text-gray-400" />
                    )}
                    <span className={requirement.passed ? 'text-green-600' : 'text-gray-500'}>
            {requirement.label}
          </span>
                </div>
            ))}
        </div>
    );
};

export default function RegisterForm({
                                 onSuccess,
                                 redirectPath,
                                 className
                             }: RegisterFormProps) {
    const { register, redirectAfterLogin, isLoading } = useAuthUtils();
    const [isPending, startTransition] = useTransition();
    const [formData, setFormData] = useState<RegisterFormData>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms_accepted: false,
        marketing_consent: false
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof RegisterFormData, string>>>({});
    const [touchedFields, setTouchedFields] = useState<Set<keyof RegisterFormData>>(new Set());

    const validateField = useCallback((field: keyof RegisterFormData, value: any, allData?: RegisterFormData) => {
        try {
            if (field === 'password_confirmation') {
                const dataToValidate = allData || formData;
                registerSchema.parse({ ...dataToValidate, [field]: value });
            } else {
                registerSchema.pick({ [field]: true }).parse({ [field]: value });
            }
            setFieldErrors(prev => ({ ...prev, [field]: undefined }));
            return true;
        } catch (error) {
            if (error instanceof z.ZodError) {
                const errorMessage = error.errors.find(err =>
                    err.path[0] === field || (field === 'password_confirmation' && err.path.includes('password_confirmation'))
                )?.message;

                setFieldErrors(prev => ({
                    ...prev,
                    [field]: errorMessage || error.errors[0]?.message
                }));
            }
            return false;
        }
    }, [formData]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        setTouchedFields(new Set(Object.keys(formData) as (keyof RegisterFormData)[]));

        setFieldErrors({});

        const validation = registerSchema.safeParse(formData);
        if (!validation.success) {
            const errors: Partial<Record<keyof RegisterFormData, string>> = {};
            validation.error.errors.forEach(error => {
                const field = error.path[0] as keyof RegisterFormData;
                if (field && !errors[field]) {
                    errors[field] = error.message;
                }
            });
            setFieldErrors(errors);

            const firstErrorField = Object.keys(errors)[0];
            if (firstErrorField) {
                const element = document.getElementById(firstErrorField);
                element?.focus();
            }
            return;
        }

        startTransition(async () => {
            try {
                await register(formData);

                toast.success('Account created successfully!');

                onSuccess?.();

                if (redirectPath) {
                    window.location.href = redirectPath;
                } else {
                    redirectAfterLogin();
                }
            } catch (error) {
                console.error('Registration failed:', error);
            }
        });
    };

    const handleInputChange = useCallback((field: keyof RegisterFormData, value: string | boolean) => {
        const newData = { ...formData, [field]: value };
        setFormData(newData);

        if (touchedFields.has(field) || (typeof value === 'string' && value.length > 0)) {
            if (field === 'password_confirmation' || field === 'password') {
                validateField('password_confirmation', newData.password_confirmation, newData);
            } else {
                validateField(field, value, newData);
            }
        }
    }, [formData, touchedFields, validateField]);

    const handleFieldBlur = useCallback((field: keyof RegisterFormData) => {
        setTouchedFields(prev => new Set(prev).add(field));

        if (field === 'password_confirmation') {
            validateField(field, formData[field], formData);
        } else {
            validateField(field, formData[field], formData);
        }
    }, [formData, validateField]);

    const togglePasswordVisibility = useCallback((field: 'password' | 'confirmPassword') => {
        if (field === 'password') {
            setShowPassword(prev => !prev);
        } else {
            setShowConfirmPassword(prev => !prev);
        }
    }, []);

    const isSubmitting = isLoading || isPending;
    const passwordsMatch = formData.password && formData.password_confirmation &&
        formData.password === formData.password_confirmation;

    return (
        <form onSubmit={handleSubmit} className={`space-y-6 ${className}`} noValidate>
            <div className="space-y-2">
                <Label htmlFor="name">
                    Full Name
                    <span className="text-red-500" aria-label="required">*</span>
                </Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    placeholder="Enter your full name"
                    value={formData.name}
                    onChange={(e) => handleInputChange('name', e.target.value)}
                    onBlur={() => handleFieldBlur('name')}
                    required
                    disabled={isSubmitting}
                    aria-invalid={!!fieldErrors.name}
                    aria-describedby={fieldErrors.name ? 'name-error' : undefined}
                    autoComplete="given-name family-name"
                    className={fieldErrors.name ? 'border-red-500 focus:border-red-500' : ''}
                />
                {fieldErrors.name && (
                    <p id="name-error" className="text-sm text-red-600" role="alert">
                        {fieldErrors.name}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="email">
                    Email Address
                    <span className="text-red-500" aria-label="required">*</span>
                </Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="Enter your email"
                    value={formData.email}
                    onChange={(e) => handleInputChange('email', e.target.value)}
                    onBlur={() => handleFieldBlur('email')}
                    required
                    disabled={isSubmitting}
                    aria-invalid={!!fieldErrors.email}
                    aria-describedby={fieldErrors.email ? 'email-error' : undefined}
                    autoComplete="email"
                    className={fieldErrors.email ? 'border-red-500 focus:border-red-500' : ''}
                />
                {fieldErrors.email && (
                    <p id="email-error" className="text-sm text-red-600" role="alert">
                        {fieldErrors.email}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="password">
                    Password
                    <span className="text-red-500" aria-label="required">*</span>
                </Label>
                <div className="relative">
                    <Input
                        id="password"
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        placeholder="Create a password (min. 8 characters)"
                        value={formData.password}
                        onChange={(e) => handleInputChange('password', e.target.value)}
                        onBlur={() => handleFieldBlur('password')}
                        required
                        disabled={isSubmitting}
                        aria-invalid={!!fieldErrors.password}
                        aria-describedby={fieldErrors.password ? 'password-error' : 'password-requirements'}
                        autoComplete="new-password"
                        className={`pr-10 ${fieldErrors.password ? 'border-red-500 focus:border-red-500' : ''}`}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => togglePasswordVisibility('password')}
                        disabled={isSubmitting}
                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                    >
                        {showPassword ? (
                            <EyeOff className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <Eye className="h-4 w-4" aria-hidden="true" />
                        )}
                    </Button>
                </div>
                {fieldErrors.password && (
                    <p id="password-error" className="text-sm text-red-600" role="alert">
                        {fieldErrors.password}
                    </p>
                )}
                <div id="password-requirements">
                    <PasswordStrengthIndicator password={formData.password} />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="password_confirmation">
                    Confirm Password
                    <span className="text-red-500" aria-label="required">*</span>
                </Label>
                <div className="relative">
                    <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        type={showConfirmPassword ? 'text' : 'password'}
                        placeholder="Confirm your password"
                        value={formData.password_confirmation}
                        onChange={(e) => handleInputChange('password_confirmation', e.target.value)}
                        onBlur={() => handleFieldBlur('password_confirmation')}
                        required
                        disabled={isSubmitting}
                        aria-invalid={!!fieldErrors.password_confirmation}
                        aria-describedby={fieldErrors.password_confirmation ? 'password-confirmation-error' : 'password-match-status'}
                        autoComplete="new-password"
                        className={`pr-10 ${
                            fieldErrors.password_confirmation
                                ? 'border-red-500 focus:border-red-500'
                                : passwordsMatch && formData.password_confirmation
                                    ? 'border-green-500 focus:border-green-500'
                                    : ''
                        }`}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => togglePasswordVisibility('confirmPassword')}
                        disabled={isSubmitting}
                        aria-label={showConfirmPassword ? 'Hide password confirmation' : 'Show password confirmation'}
                    >
                        {showConfirmPassword ? (
                            <EyeOff className="h-4 w-4" aria-hidden="true" />
                        ) : (
                            <Eye className="h-4 w-4" aria-hidden="true" />
                        )}
                    </Button>
                </div>
                {fieldErrors.password_confirmation && (
                    <p id="password-confirmation-error" className="text-sm text-red-600" role="alert">
                        {fieldErrors.password_confirmation}
                    </p>
                )}
                {passwordsMatch && formData.password_confirmation && (
                    <p id="password-match-status" className="text-sm text-green-600 flex items-center gap-1">
                        <Check className="h-3 w-3" />
                        Passwords match
                    </p>
                )}
            </div>

            <div className="space-y-4">
                <div className="flex items-start space-x-2">
                    <Checkbox
                        id="terms_accepted"
                        name="terms_accepted"
                        checked={formData.terms_accepted}
                        onCheckedChange={(checked) => handleInputChange('terms_accepted', checked as boolean)}
                        disabled={isSubmitting}
                        required
                        aria-invalid={!!fieldErrors.terms_accepted}
                        aria-describedby={fieldErrors.terms_accepted ? 'terms-error' : undefined}
                        className={fieldErrors.terms_accepted ? 'border-red-500' : ''}
                    />
                    <div className="space-y-1">
                        <Label htmlFor="terms_accepted" className="text-sm leading-relaxed cursor-pointer">
                            I agree to the{' '}
                            <Link
                                href="/terms"
                                className="text-blue-600 hover:text-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Terms and Conditions
                            </Link>{' '}
                            and{' '}
                            <Link
                                href="/privacy"
                                className="text-blue-600 hover:text-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Privacy Policy
                            </Link>
                            <span className="text-red-500" aria-label="required">*</span>
                        </Label>
                        {fieldErrors.terms_accepted && (
                            <p id="terms-error" className="text-sm text-red-600" role="alert">
                                {fieldErrors.terms_accepted}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex items-start space-x-2">
                    <Checkbox
                        id="marketing_consent"
                        name="marketing_consent"
                        checked={formData.marketing_consent}
                        onCheckedChange={(checked) => handleInputChange('marketing_consent', checked as boolean)}
                        disabled={isSubmitting}
                    />
                    <Label htmlFor="marketing_consent" className="text-sm leading-relaxed cursor-pointer">
                        I would like to receive marketing emails and updates (optional)
                    </Label>
                </div>
            </div>

            <Button
                type="submit"
                disabled={isSubmitting}
                className="w-full relative"
                aria-describedby="submit-status"
            >
                {isSubmitting && (
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                )}
                {isSubmitting ? 'Creating account...' : 'Create Account'}
            </Button>

            <div className="text-center text-sm">
                Already have an account?{' '}
                <Link
                    href="/login"
                    className="text-blue-600 hover:text-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    tabIndex={isSubmitting ? -1 : 0}
                >
                    Sign in
                </Link>
            </div>

            <div className="sr-only" aria-live="polite" aria-atomic="true" id="submit-status">
                {isSubmitting && 'Creating account, please wait...'}
            </div>
        </form>
    );
}