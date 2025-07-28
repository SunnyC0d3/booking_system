import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Link from 'next/link';
import { Mail, ArrowLeft, CheckCircle } from 'lucide-react';
import { Button, Input, Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { ForgotPasswordFormData } from '@/types/auth';

// Validation schema
const forgotPasswordSchema = z.object({
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Please enter a valid email address'),
});

interface ForgotPasswordFormProps {
    onSuccess?: () => void;
}

export const ForgotPasswordForm: React.FC<ForgotPasswordFormProps> = ({
                                                                          onSuccess,
                                                                      }) => {
    const { forgotPassword, isLoading, error, clearError } = useAuth();
    const [isSuccess, setIsSuccess] = React.useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        watch,
        getValues,
    } = useForm<ForgotPasswordFormData>({
        resolver: zodResolver(forgotPasswordSchema),
        defaultValues: {
            email: '',
        },
    });

    // Clear errors when email changes
    React.useEffect(() => {
        if (error) {
            clearError();
        }
    }, [watch('email')]);

    const onSubmit = async (data: ForgotPasswordFormData) => {
        try {
            clearError();

            await forgotPassword({
                email: data.email,
            });

            setIsSuccess(true);

            if (onSuccess) {
                onSuccess();
            }

        } catch (error: any) {
            // Error is handled by the store and displayed
        }
    };

    const handleResend = async () => {
        const email = getValues('email');
        if (email) {
            try {
                await forgotPassword({ email });
            } catch (error) {
                // Error handled by store
            }
        }
    };

    if (isSuccess) {
        return (
            <Card className="w-full max-w-md mx-auto">
                <CardHeader className="text-center">
                    <div className="w-16 h-16 bg-success/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <CheckCircle className="h-8 w-8 text-success" />
                    </div>
                    <CardTitle className="text-2xl font-bold text-foreground">
                        Check Your Email
                    </CardTitle>
                    <p className="text-muted-foreground">
                        We've sent password reset instructions to your email address.
                    </p>
                </CardHeader>

                <CardContent className="space-y-4">
                    <div className="p-4 rounded-lg bg-muted/50 border">
                        <p className="text-sm text-muted-foreground text-center">
                            If you don't see the email in your inbox, please check your spam folder.
                            The link will expire in 60 minutes for security.
                        </p>
                    </div>

                    <div className="flex justify-center">
                        <Button
                            variant="outline"
                            onClick={handleResend}
                            loading={isLoading}
                            disabled={isLoading}
                        >
                            Resend Email
                        </Button>
                    </div>
                </CardContent>

                <CardFooter className="justify-center">
                    <Link
                        href="/login"
                        className="inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80 transition-colors"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back to sign in
                    </Link>
                </CardFooter>
            </Card>
        );
    }

    return (
        <Card className="w-full max-w-md mx-auto">
            <CardHeader className="text-center">
                <CardTitle className="text-2xl font-bold text-foreground">
                    Forgot Password?
                </CardTitle>
                <p className="text-muted-foreground">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
            </CardHeader>

            <CardContent>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    {/* Global Error */}
                    {error && (
                        <div className="alert alert-error">
                            <p className="text-sm">{error}</p>
                        </div>
                    )}

                    {/* Email Field */}
                    <Input
                        {...register('email')}
                        type="email"
                        label="Email Address"
                        placeholder="Enter your email address"
                        leftIcon={<Mail className="h-4 w-4" />}
                        error={errors.email?.message}
                        required
                        autoComplete="email"
                        autoFocus
                    />

                    {/* Submit Button */}
                    <Button
                        type="submit"
                        variant="default"
                        size="lg"
                        className="w-full"
                        loading={isLoading || isSubmitting}
                        loadingText="Sending email..."
                    >
                        Send Reset Link
                    </Button>
                </form>
            </CardContent>

            <CardFooter className="justify-center">
                <Link
                    href="/login"
                    className="inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80 transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back to sign in
                </Link>
            </CardFooter>
        </Card>
    );
};

export default ForgotPasswordForm;