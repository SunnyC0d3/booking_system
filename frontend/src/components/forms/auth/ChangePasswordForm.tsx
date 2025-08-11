'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Lock, Eye, EyeOff, CheckCircle2, AlertCircle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { useAuth } from '@/stores/authStore';
import { ChangePasswordFormData } from '@/types/auth';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

// Password strength validation
const passwordStrength = {
    minLength: (password: string) => password.length >= 8,
    hasUppercase: (password: string) => /[A-Z]/.test(password),
    hasLowercase: (password: string) => /[a-z]/.test(password),
    hasNumber: (password: string) => /\d/.test(password),
    hasSpecial: (password: string) => /[!@#$%^&*(),.?":{}|<>]/.test(password),
    notCommon: (password: string) => {
        const common = ['password', '123456', 'qwerty', 'abc123', 'password123'];
        return !common.some(p => password.toLowerCase().includes(p));
    }
};

// Validation schema
const changePasswordSchema = z.object({
    current_password: z
        .string()
        .min(1, 'Current password is required'),
    password: z
        .string()
        .min(8, 'Password must be at least 8 characters')
        .refine(
            (password) => Object.values(passwordStrength).every(check => check(password)),
            'Password must meet all security requirements'
        ),
    password_confirmation: z
        .string()
        .min(1, 'Password confirmation is required'),
}).refine((data) => data.password === data.password_confirmation, {
    message: "New passwords don't match",
    path: ["password_confirmation"],
}).refine((data) => data.current_password !== data.password, {
    message: "New password must be different from current password",
    path: ["password"],
});

interface ChangePasswordFormProps {
    className?: string;
    onSuccess?: () => void;
    onCancel?: () => void;
    standalone?: boolean;
}

export const ChangePasswordForm: React.FC<ChangePasswordFormProps> = ({
                                                                          className,
                                                                          onSuccess,
                                                                          onCancel,
                                                                          standalone = false
                                                                      }) => {
    const { changePassword, isLoading, error, clearError } = useAuth();
    const [showCurrentPassword, setShowCurrentPassword] = React.useState(false);
    const [showNewPassword, setShowNewPassword] = React.useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = React.useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        watch,
        reset,
    } = useForm<ChangePasswordFormData>({
        resolver: zodResolver(changePasswordSchema),
        defaultValues: {
            current_password: '',
            password: '',
            password_confirmation: '',
        },
    });

    const watchedPassword = watch('password', '');

    // Clear errors when form changes
    React.useEffect(() => {
        if (error) {
            clearError();
        }
    }, [watch('current_password'), watch('password'), error, clearError]);

    const onSubmit = async (data: ChangePasswordFormData) => {
        try {
            clearError();

            await changePassword({
                current_password: data.current_password,
                password: data.password,
                password_confirmation: data.password_confirmation,
            });

            toast.success('Password changed successfully!');
            reset();

            if (onSuccess) {
                onSuccess();
            }

        } catch (error: any) {
            // Error is handled by the store and displayed
            toast.error(error.message || 'Failed to change password');
        }
    };

    // Calculate password strength
    const getPasswordStrength = (password: string) => {
        if (!password) return { score: 0, checks: [] };

        const checks = Object.entries(passwordStrength).map(([key, checkFn]) => ({
            key,
            passed: checkFn(password),
            label: {
                minLength: 'At least 8 characters',
                hasUppercase: 'One uppercase letter',
                hasLowercase: 'One lowercase letter',
                hasNumber: 'One number',
                hasSpecial: 'One special character',
                notCommon: 'Not a common password'
            }[key]
        }));

        const score = (checks.filter(c => c.passed).length / checks.length) * 100;
        return { score, checks };
    };

    const { score: passwordScore, checks: passwordChecks } = getPasswordStrength(watchedPassword);

    const getStrengthColor = (score: number) => {
        if (score >= 80) return 'text-green-600';
        if (score >= 60) return 'text-yellow-600';
        if (score >= 40) return 'text-orange-600';
        return 'text-red-600';
    };

    const getStrengthLabel = (score: number) => {
        if (score >= 80) return 'Strong';
        if (score >= 60) return 'Good';
        if (score >= 40) return 'Fair';
        if (score > 0) return 'Weak';
        return '';
    };

    const formContent = (
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Global Error */}
            {error && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {/* Current Password */}
            <div className="space-y-2">
                <label className="text-sm font-medium">Current Password</label>
                <div className="relative">
                    <Input
                        {...register('current_password')}
                        type={showCurrentPassword ? 'text' : 'password'}
                        placeholder="Enter your current password"
                        className={cn(errors.current_password && "border-destructive")}
                        autoComplete="current-password"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                    >
                        {showCurrentPassword ? (
                            <EyeOff className="h-4 w-4" />
                        ) : (
                            <Eye className="h-4 w-4" />
                        )}
                    </Button>
                </div>
                {errors.current_password && (
                    <p className="text-sm text-destructive">{errors.current_password.message}</p>
                )}
            </div>

            {/* New Password */}
            <div className="space-y-2">
                <label className="text-sm font-medium">New Password</label>
                <div className="relative">
                    <Input
                        {...register('password')}
                        type={showNewPassword ? 'text' : 'password'}
                        placeholder="Enter your new password"
                        className={cn(errors.password && "border-destructive")}
                        autoComplete="new-password"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => setShowNewPassword(!showNewPassword)}
                    >
                        {showNewPassword ? (
                            <EyeOff className="h-4 w-4" />
                        ) : (
                            <Eye className="h-4 w-4" />
                        )}
                    </Button>
                </div>
                {errors.password && (
                    <p className="text-sm text-destructive">{errors.password.message}</p>
                )}

                {/* Password Strength Indicator */}
                {watchedPassword && (
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground">Password strength</span>
                            <span className={cn("text-xs font-medium", getStrengthColor(passwordScore))}>
                                {getStrengthLabel(passwordScore)}
                            </span>
                        </div>
                        <Progress value={passwordScore} className="h-2" />

                        {/* Password Requirements */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-1 text-xs">
                            {passwordChecks.map(({ key, passed, label }) => (
                                <div key={key} className="flex items-center gap-2">
                                    <CheckCircle2
                                        className={cn("h-3 w-3", passed ? "text-green-600" : "text-muted-foreground")}
                                    />
                                    <span className={passed ? "text-green-600" : "text-muted-foreground"}>
                                        {label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Confirm New Password */}
            <div className="space-y-2">
                <label className="text-sm font-medium">Confirm New Password</label>
                <div className="relative">
                    <Input
                        {...register('password_confirmation')}
                        type={showConfirmPassword ? 'text' : 'password'}
                        placeholder="Confirm your new password"
                        className={cn(errors.password_confirmation && "border-destructive")}
                        autoComplete="new-password"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    >
                        {showConfirmPassword ? (
                            <EyeOff className="h-4 w-4" />
                        ) : (
                            <Eye className="h-4 w-4" />
                        )}
                    </Button>
                </div>
                {errors.password_confirmation && (
                    <p className="text-sm text-destructive">{errors.password_confirmation.message}</p>
                )}
            </div>

            {/* Security Note */}
            <Alert>
                <Lock className="h-4 w-4" />
                <AlertDescription>
                    Your new password should be unique and not used on other websites.
                    After changing your password, you'll need to sign in again on all devices.
                </AlertDescription>
            </Alert>

            {/* Submit Buttons */}
            <div className="flex gap-3">
                <Button
                    type="submit"
                    disabled={isLoading || isSubmitting || passwordScore < 60}
                    className="flex-1"
                >
                    {isLoading || isSubmitting ? 'Changing Password...' : 'Change Password'}
                </Button>
                {onCancel && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={isLoading || isSubmitting}
                        className="flex-1"
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );

    if (standalone) {
        return (
            <Card className={className}>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Lock className="h-5 w-5" />
                        Change Password
                    </CardTitle>
                    <CardDescription>
                        Update your password to keep your account secure
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {formContent}
                </CardContent>
            </Card>
        );
    }

    return <div className={className}>{formContent}</div>;
};